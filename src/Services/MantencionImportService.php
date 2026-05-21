<?php
// src/Services/MantencionImportService.php

namespace App\Services;

use PDO;
use Exception;
use DateTime;

class MantencionImportService
{
    private PDO $db;
    public array $stats = [
        'processed' => 0,
        'updated' => 0,
        'errors' => 0,
        'logs' => []
    ];

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
        
        // ✅ Configuraciones MOVIDAS DENTRO del constructor
        ini_set('max_execution_time', 600);
        set_time_limit(600);
        ini_set('memory_limit', '512M');
    }

    /**
     * Procesa el archivo CSV de la Planilla de Mantención (Hoja NEW BD)
     */
    public function processFile(string $filePath, int $batchSize = 500): void
    {
        if (!file_exists($filePath)) {
            throw new Exception("El archivo no existe: $filePath");
        }

        // 1. DETECTAR DELIMITADOR
        $content = file_get_contents($filePath);
        $firstLine = explode("\n", $content)[0];
        $delimiter = $this->detectDelimiter($firstLine);
        $this->stats['logs'][] = "Separador detectado: " . ($delimiter === ';' ? ';' : ($delimiter === "\t" ? 'TAB' : ','));

        // 2. PRE-CARGAR MAPEO DE OTs
        $otMapping = $this->loadOtMapping();
        $this->stats['logs'][] = "OTs precargadas: " . count($otMapping);

        // 3. INICIAR TRANSACCIÓN GLOBAL
        $this->db->beginTransaction();
        
        try {
            $handle = fopen($filePath, 'r');
            if (!$handle) {
                throw new Exception("No se pudo abrir el archivo.");
            }

            // Saltar encabezados
            fgetcsv($handle, 1000, $delimiter);

            // Buffers para batch insert
            $historicoBatch = [];
            $resumenBatch = [];
            $rowNum = 1;
            $validRows = 0;
            $today = new DateTime();

            while (($data = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
                $rowNum++;
                
                if (count($data) < 13) continue;

                try {
                    $idPrevisionSic = $this->parseIdPrevision($data[0] ?? '');
                    if (!$idPrevisionSic) continue;

                    if (!isset($otMapping[$idPrevisionSic])) continue;
                    
                    $codigoOt = $otMapping[$idPrevisionSic];
                    $validRows++;

                    $fechaProgramada = $this->parseFecha($data[5] ?? '');
                    $hhPlanificadas = $this->parseHoras($data[10] ?? '0');
                    $tipo = $this->parseTipo($data[11] ?? 'INTERNA');
                    $estadoNormalizado = $this->normalizeEstado($data[12] ?? '');
                    $codigoProtocolo = trim($data[1] ?? '');
                    $nombreEquipo = trim($data[2] ?? '');

                    $diasRetraso = $this->calcularDiasRetraso($fechaProgramada, $estadoNormalizado, $today);

                    $historicoBatch[] = [
                        $codigoOt, $idPrevisionSic, $fechaProgramada, $estadoNormalizado,
                        $hhPlanificadas, null, null, null, $nombreEquipo, $codigoProtocolo
                    ];

                    $resumenBatch[] = [
                        $codigoOt, $idPrevisionSic, $fechaProgramada, $fechaProgramada,
                        $estadoNormalizado, $hhPlanificadas, $diasRetraso,
                        null, null, $nombreEquipo, $tipo
                    ];

                    if (count($historicoBatch) >= $batchSize) {
                        $this->flushBatch($historicoBatch, $resumenBatch);
                        $historicoBatch = [];
                        $resumenBatch = [];
                        
                        if ($validRows % ($batchSize * 10) === 0) {
                            $this->db->commit();
                            $this->db->beginTransaction();
                            $this->stats['logs'][] = "Checkpoint: $validRows filas procesadas";
                        }
                    }

                    $this->stats['updated']++;

                } catch (Exception $e) {
                    $this->stats['errors']++;
                    $this->stats['logs'][] = "Error Fila $rowNum: " . $e->getMessage();
                }
                
                $this->stats['processed']++;
            }

            if (!empty($historicoBatch)) {
                $this->flushBatch($historicoBatch, $resumenBatch);
            }

            fclose($handle);
            $this->db->commit();
            
            $this->stats['logs'][] = "✅ Proceso completado. Filas válidas: $validRows";
            
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Ejecuta inserciones masivas con multi-row INSERT
     */
    private function flushBatch(array $historicoBatch, array $resumenBatch): void
    {
        if (empty($historicoBatch)) return;

        if (!empty($historicoBatch)) {
            $placeholders = [];
            $values = [];
            
            foreach ($historicoBatch as $row) {
                $placeholders[] = "(?, ?, NOW(), 'MANTENCION', ?, ?, ?, 0, '', ?, ?, ?, ?, ?)";
                $values = array_merge($values, $row);
            }
            
            $sql = "INSERT INTO ot_historico (
                codigo_ot, id_prevision_sic, fecha_carga, fuente, fecha_programada, 
                estado, hh_planificadas, hh_reales, observaciones, id_vertical, 
                id_especialidad, id_equipo, nombre_equipo, nombre_protocolo
            ) VALUES " . implode(', ', $placeholders);
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($values);
        }

        if (!empty($resumenBatch)) {
            $placeholders = [];
            $values = [];
            $updateFields = [];
            
            foreach ($resumenBatch as $i => $row) {
                $placeholders[] = "(?, ?, ?, NOW(), ?, ?, NOW(), ?, 0, 0, 0, ?, ?, ?, ?)";
                $values = array_merge($values, $row);
                
                if ($i === 0) {
                    $fields = [
                        'total_hh_planificadas', 'ultima_fecha_programada', 'ultimo_estado',
                        'ultima_carga', 'tipo_mantenimiento', 'dias_retraso'
                    ];
                    foreach ($fields as $field) {
                        $updateFields[] = "$field = VALUES($field)";
                    }
                }
            }
            
            $sql = "INSERT INTO ot_resumen_actual (
                codigo_ot, id_prevision_sic, primera_fecha_programada, primera_carga,
                ultima_fecha_programada, ultimo_estado, ultima_carga,
                total_hh_planificadas, total_hh_reales_acumuladas, veces_reprogramadas,
                dias_retraso, id_vertical, id_especialidad, nombre_equipo, tipo_mantenimiento
            ) VALUES " . implode(', ', $placeholders) . 
            " ON DUPLICATE KEY UPDATE " . implode(', ', $updateFields);
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($values);
        }
    }

    private function loadOtMapping(): array
    {
        $stmt = $this->db->query("SELECT id_prevision_sic, codigo_ot FROM ordenes_trabajo WHERE id_prevision_sic IS NOT NULL");
        $mapping = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $mapping[(int)$row['id_prevision_sic']] = $row['codigo_ot'];
        }
        
        return $mapping;
    }

    // === HELPERS DE PARSEO ===
    
    private function parseIdPrevision(?string $raw): ?int
    {
        $clean = preg_replace('/[^0-9]/', '', trim($raw ?? ''));
        return (!empty($clean) && is_numeric($clean)) ? (int)$clean : null;
    }

    private function parseFecha(?string $raw): ?string
    {
        if (empty($raw)) return null;
        $parts = explode('/', trim($raw));
        if (count($parts) !== 3) return null;
        
        $month = str_pad($parts[0], 2, '0', STR_PAD_LEFT);
        $day = str_pad($parts[1], 2, '0', STR_PAD_LEFT);
        $year = strlen($parts[2]) == 2 ? '20' . $parts[2] : $parts[2];
        
        return "$year-$month-$day";
    }

    private function parseHoras(string $raw): float
    {
        return floatval(str_replace(',', '.', trim($raw)));
    }

    private function parseTipo(string $raw): string
    {
        return (strtoupper(trim($raw)) === 'EXT') ? 'EXTERNA' : 'INTERNA';
    }

    private function calcularDiasRetraso(?string $fecha, string $estado, DateTime $today): int
    {
        if (!$fecha || $estado !== 'pendiente') return 0;
        
        try {
            $dateObj = new DateTime($fecha);
            return ($dateObj < $today) ? $today->diff($dateObj)->days : 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function detectDelimiter(string $firstLine): string
    {
        $counts = [
            ',' => substr_count($firstLine, ','),
            ';' => substr_count($firstLine, ';'),
            "\t" => substr_count($firstLine, "\t")
        ];
        arsort($counts);
        return key($counts);
    }

    private function normalizeEstado(string $raw): string
    {
        $lower = strtolower(trim($raw));
        if (strpos($lower, 'complet') !== false || strpos($lower, 'cerrad') !== false || strpos($lower, 'hecho') !== false) {
            return 'completada';
        } elseif (strpos($lower, 'ejecuc') !== false || strpos($lower, 'proceso') !== false) {
            return 'en_ejecucion';
        } elseif (strpos($lower, 'reprog') !== false) {
            return 'reprogramada';
        } elseif (strpos($lower, 'no realiz') !== false || strpos($lower, 'cancel') !== false) {
            return 'no_realizada';
        }
        return 'pendiente';
    }
}