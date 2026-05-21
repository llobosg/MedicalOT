<?php
// src/Services/MantencionImportService.php

namespace App\Services;

use PDO;
use Exception;

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
    }

    /**
     * Procesa el archivo CSV de la Planilla de Mantención (Hoja NEW BD)
     */
    public function processFile(string $filePath): void
    {
        if (!file_exists($filePath)) {
            throw new Exception("El archivo no existe: $filePath");
        }

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new Exception("No se pudo abrir el archivo.");
        }

        // Saltar encabezados
        fgetcsv($handle); 

        // Preparar statements
        
        // TABLA: ot_historico
        // Columnas a insertar (14): codigo_ot, id_prevision_sic, fecha_carga, fuente, fecha_programada, estado, hh_planificadas, hh_reales, observaciones, id_vertical, id_especialidad, id_equipo, nombre_equipo, nombre_protocolo
        $historicoStmt = $this->db->prepare("
            INSERT INTO ot_historico (
                codigo_ot, 
                id_prevision_sic, 
                fecha_carga, 
                fuente, 
                fecha_programada, 
                estado, 
                hh_planificadas, 
                hh_reales, 
                observaciones, 
                id_vertical, 
                id_especialidad, 
                id_equipo, 
                nombre_equipo, 
                nombre_protocolo
            ) VALUES (?, ?, NOW(), 'MANTENCION', ?, ?, ?, 0, ?, ?, ?, ?, ?, ?)
        ");

        // TABLA: ot_resumen_actual
        // Columnas a insertar (16): codigo_ot, id_prevision_sic, primera_fecha_programada, primera_carga, ultima_fecha_programada, ultimo_estado, ultima_carga, total_hh_planificadas, total_hh_reales_acumuladas, veces_reprogramadas, dias_retraso, id_vertical, id_especialidad, nombre_equipo, tipo_mantenimiento
        // Nota: updated_at es auto-generado por MySQL, no lo incluimos en el INSERT.
        $resumenStmt = $this->db->prepare("
            INSERT INTO ot_resumen_actual (
                codigo_ot, 
                id_prevision_sic, 
                primera_fecha_programada, 
                primera_carga,
                ultima_fecha_programada, 
                ultimo_estado, 
                ultima_carga,
                total_hh_planificadas, 
                total_hh_reales_acumuladas,
                veces_reprogramadas, 
                dias_retraso,
                id_vertical, 
                id_especialidad, 
                nombre_equipo, 
                tipo_mantenimiento
            ) VALUES (?, ?, ?, NOW(), ?, ?, NOW(), ?, 0, 0, 0, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                total_hh_planificadas = VALUES(total_hh_planificadas),
                ultima_fecha_programada = VALUES(ultima_fecha_programada),
                ultimo_estado = VALUES(ultimo_estado),
                ultima_carga = VALUES(ultima_carga),
                tipo_mantenimiento = VALUES(tipo_mantenimiento),
                dias_retraso = VALUES(dias_retraso)
        ");

        $rowNum = 1;
        
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $rowNum++;
            
            try {
                // Limpieza básica según hoja "NEW BD"
                // Col 0: ID PREVISION SIC
                $idPrevisionSic = trim($data[0] ?? '');
                if (empty($idPrevisionSic) || !is_numeric($idPrevisionSic)) continue;

                // Col 1: CODIGO PROTOCOLO
                $codigoProtocolo = trim($data[1] ?? '');
                
                // Col 2: NOMBRE (Equipo/Tarea)
                $nombreEquipo = trim($data[2] ?? '');
                
                // Col 5: FECHA (Formato MM/DD/YY)
                $fechaRaw = trim($data[5] ?? '');
                
                // Col 10: HORAS
                $hhPlanificadas = floatval(str_replace(',', '.', $data[10] ?? '0'));
                
                // Col 11: TIPO (INTERNA/EXTERNA)
                $tipoRaw = strtoupper(trim($data[11] ?? 'INTERNA'));
                $tipo = ($tipoRaw === 'EXT') ? 'EXTERNA' : 'INTERNA';
                
                // Col 12: ESTADO (Vacío en esta hoja, asumimos pendiente)
                $estadoRaw = trim($data[12] ?? '');
                $estadoNormalizado = empty($estadoRaw) ? 'pendiente' : $this->normalizeEstado($estadoRaw);

                // Normalizar Fecha (MM/DD/YY -> YYYY-MM-DD)
                $fechaProgramada = null;
                if (!empty($fechaRaw)) {
                    $parts = explode('/', $fechaRaw);
                    if (count($parts) === 3) {
                        $year = strlen($parts[2]) == 2 ? '20' . $parts[2] : $parts[2];
                        $month = str_pad($parts[0], 2, '0', STR_PAD_LEFT);
                        $day = str_pad($parts[1], 2, '0', STR_PAD_LEFT);
                        $fechaProgramada = sprintf("%s-%s-%s", $year, $month, $day);
                    }
                }

                // Buscar OT vinculada por ID Previsión en ordenes_trabajo
                $otCheck = $this->db->prepare("SELECT codigo_ot FROM ordenes_trabajo WHERE id_prevision_sic = ? LIMIT 1");
                $otCheck->execute([$idPrevisionSic]);
                $otData = $otCheck->fetch(PDO::FETCH_ASSOC);

                if (!$otData) {
                    // Si no encuentra la OT en SIC, saltamos pero registramos log si quieres
                    // $this->stats['logs'][] = "Fila $rowNum: No se encontró OT para ID Previsión $idPrevisionSic";
                    continue;
                }

                $codigoOt = $otData['codigo_ot'];

                // 1. Insertar en Histórico (Snapshot de la planificación)
                // Valores: 14
                $historicoStmt->execute([
                    $codigoOt,                  // 1. codigo_ot
                    $idPrevisionSic,            // 2. id_prevision_sic
                    $fechaProgramada,           // 3. fecha_programada
                    $estadoNormalizado,         // 4. estado
                    $hhPlanificadas,            // 5. hh_planificadas
                    '',                         // 6. observaciones
                    null,                       // 7. id_vertical
                    null,                       // 8. id_especialidad
                    null,                       // 9. id_equipo
                    $nombreEquipo,              // 10. nombre_equipo
                    $codigoProtocolo            // 11. nombre_protocolo
                ]);

                // 2. Actualizar Resumen Actual
                // Calculamos días de retraso si ya pasó la fecha y sigue pendiente
                $diasRetraso = 0;
                if ($fechaProgramada && $estadoNormalizado === 'pendiente') {
                    try {
                        $dateObj = new DateTime($fechaProgramada);
                        $today = new DateTime();
                        if ($dateObj < $today) {
                            $diasRetraso = $today->diff($dateObj)->days;
                        }
                    } catch (\Exception $e) {
                        // Error de fecha, ignoramos
                    }
                }

                // Valores: 15 (Nota: el SQL tiene 15 placeholders, verificamos abajo)
                // SQL Placeholders: ?, ?, ?, NOW(), ?, ?, NOW(), ?, 0, 0, 0, ?, ?, ?, ?, ?
                // Total Placeholders: 15
                
                $resumenStmt->execute([
                    $codigoOt,                  // 1. codigo_ot
                    $idPrevisionSic,            // 2. id_prevision_sic
                    $fechaProgramada,           // 3. primera_fecha_programada
                    $fechaProgramada,           // 4. ultima_fecha_programada
                    $estadoNormalizado,         // 5. ultimo_estado
                    $hhPlanificadas,            // 6. total_hh_planificadas
                    $diasRetraso,               // 7. dias_retraso
                    null,                       // 8. id_vertical
                    null,                       // 9. id_especialidad
                    $nombreEquipo,              // 10. nombre_equipo
                    $tipo                       // 11. tipo_mantenimiento
                ]);

                $this->stats['updated']++;

            } catch (Exception $e) {
                $this->stats['errors']++;
                $this->stats['logs'][] = "Error Fila $rowNum: " . $e->getMessage();
            }
            
            $this->stats['processed']++;
        }

        fclose($handle);
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