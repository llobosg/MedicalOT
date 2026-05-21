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
     * Procesa el archivo CSV/XLSX de la Planilla de Mantención (Hoja NEW BD)
     */
    public function processFile(string $filePath): void
    {
        if (!file_exists($filePath)) {
            throw new Exception("El archivo no existe: $filePath");
        }

        // Detectar si es CSV plano o si necesitamos librería para XLSX
        // Para simplicidad en demo, asumimos CSV exportado desde Excel
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new Exception("No se pudo abrir el archivo.");
        }

        // Saltar encabezados
        fgetcsv($handle); 

        // Preparar statements
        $historicoStmt = $this->db->prepare("
            INSERT INTO ot_historico (
                codigo_ot, id_prevision_sic, fecha_carga, fuente, 
                fecha_programada, estado, hh_planificadas, hh_reales, 
                observaciones, id_vertical, id_especialidad, id_equipo, 
                nombre_equipo, nombre_protocolo
            ) VALUES (?, ?, NOW(), 'MANTENCION_PLAN', ?, ?, ?, 0, ?, ?, ?, ?, ?, ?)
        ");

        $resumenStmt = $this->db->prepare("
            INSERT INTO ot_resumen_actual (
                codigo_ot, id_prevision_sic, primera_fecha_programada, primera_carga,
                ultima_fecha_programada, ultimo_estado, ultima_carga,
                total_hh_planificadas, total_hh_reales_acumuladas,
                veces_reprogramadas, dias_retraso,
                id_vertical, id_especialidad, nombre_equipo, tipo_mantenimiento
            ) VALUES (?, ?, ?, NOW(), ?, ?, NOW(), ?, 0, 0, 0, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                total_hh_planificadas = VALUES(total_hh_planificadas),
                ultima_fecha_programada = VALUES(ultima_fecha_programada),
                tipo_mantenimiento = VALUES(tipo_mantenimiento)
        ");

        $rowNum = 1;
        
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $rowNum++;
            
            try {
                // Limpieza básica
                $idPrevisionSic = trim($data[0] ?? '');
                if (empty($idPrevisionSic) || !is_numeric($idPrevisionSic)) continue;

                $codigoProtocolo = trim($data[1] ?? '');
                $nombreEquipo = trim($data[2] ?? '');
                $fechaRaw = trim($data[5] ?? ''); // Formato MM/DD/YY
                $hhPlanificadas = floatval(str_replace(',', '.', $data[10] ?? '0')); // Manejo decimal europeo
                $tipo = strtoupper(trim($data[11] ?? 'INTERNA'));
                $estadoRaw = trim($data[12] ?? '');
                
                // Normalizar Fecha (MM/DD/YY -> YYYY-MM-DD)
                $fechaProgramada = null;
                if (!empty($fechaRaw)) {
                    $parts = explode('/', $fechaRaw);
                    if (count($parts) === 3) {
                        $year = strlen($parts[2]) == 2 ? '20' . $parts[2] : $parts[2];
                        $fechaProgramada = sprintf("%s-%s-%s", $year, $parts[0], $parts[1]);
                    }
                }

                // Normalizar Estado
                $estadoNormalizado = empty($estadoRaw) ? 'pendiente' : $this->normalizeEstado($estadoRaw);

                // Buscar OT vinculada por ID Previsión
                // Nota: En la carga inicial de SIC, ya debemos tener ordenes_trabajo con id_prevision_sic
                $otCheck = $this->db->prepare("SELECT codigo_ot FROM ordenes_trabajo WHERE id_prevision_sic = ? LIMIT 1");
                $otCheck->execute([$idPrevisionSic]);
                $otData = $otCheck->fetch(PDO::FETCH_ASSOC);

                if (!$otData) {
                    // Opcional: Registrar error si no encuentra la OT en SIC
                    // $this->stats['errors']++;
                    continue;
                }

                $codigoOt = $otData['codigo_ot'];

                // 1. Insertar en Histórico (Snapshot de la planificación)
                $historicoStmt->execute([
                    $codigoOt,
                    $idPrevisionSic,
                    $fechaProgramada,
                    $estadoNormalizado,
                    $hhPlanificadas,
                    '', // Observaciones
                    null, // Vertical (se puede mejorar trayéndola del master si se tiene)
                    null, // Especialidad ID
                    null, // Equipo ID
                    $nombreEquipo,
                    $codigoProtocolo
                ]);

                // 2. Actualizar Resumen Actual
                // Calculamos días de retraso si ya pasó la fecha y sigue pendiente
                $diasRetraso = 0;
                if ($fechaProgramada && $estadoNormalizado === 'pendiente') {
                    $dateObj = new DateTime($fechaProgramada);
                    $today = new DateTime();
                    if ($dateObj < $today) {
                        $diasRetraso = $today->diff($dateObj)->days;
                    }
                }

                $resumenStmt->execute([
                    $codigoOt,
                    $idPrevisionSic,
                    $fechaProgramada,
                    $fechaProgramada,
                    $estadoNormalizado,
                    $hhPlanificadas,
                    $diasRetraso,
                    null, // Vertical
                    null, // Especialidad
                    $nombreEquipo,
                    $tipo === 'EXT' ? 'EXTERNA' : 'INTERNA'
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