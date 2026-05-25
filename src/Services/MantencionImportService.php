<?php
// src/Services/MantencionImportService.php
namespace App\Services;

use PDO;
use Exception;
use DateTime;
use PhpOffice\PhpSpreadsheet\IOFactory;

class MantencionImportService
{
    private PDO $db;
    public array $stats = [
        'processed' => 0, 'updated' => 0, 'inserted' => 0,
        'not_found' => 0, 'errors' => 0, 'logs' => []
    ];

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
        ini_set('max_execution_time', 600);
        set_time_limit(600);
        ini_set('memory_limit', '512M');
    }

    public function processFile(string $filePath, string $fileName): void
    {
        $rows = $this->readFile($filePath);
        if (empty($rows)) throw new Exception('No se encontraron datos válidos.');

        $this->stats['logs'][] = "📄 {$fileName} | Filas: " . count($rows);

        $this->db->beginTransaction();
        try {
            $historicoBatch = [];
            $resumenBatch   = [];
            $today = new DateTime();
            $batchSize = 500;

            foreach ($rows as $idx => $row) {
                $this->processRow($row, $today, $historicoBatch, $resumenBatch, $idx + 2);
                $this->stats['processed']++;

                if (count($resumenBatch) >= $batchSize) {
                    $this->flushBatch($historicoBatch, $resumenBatch);
                    $historicoBatch = [];
                    $resumenBatch   = [];
                }
            }

            if (!empty($resumenBatch)) $this->flushBatch($historicoBatch, $resumenBatch);

            $this->db->commit();
            $this->stats['logs'][] = "✅ Actualizadas: {$this->stats['updated']} | Nuevas: {$this->stats['inserted']}";

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function readFile(string $path): array
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        
        if ($ext === 'xlsx') {
            if (!class_exists(IOFactory::class)) {
                throw new Exception('PhpSpreadsheet no instalada.');
            }
            $spreadsheet = IOFactory::load($path);
            $sheet = $spreadsheet->getSheetByName('NEW BD');
            if (!$sheet) throw new Exception('Hoja "NEW BD" no encontrada.');
            
            // ✅ FIX CRÍTICO: Calcular fórmulas y formatear valores
            // Parámetros: $nullValue, $calculateFormulas, $formatData
            $data = $sheet->toArray(null, true, true);
            array_shift($data);
            return $data;
        }

        $handle = fopen($path, 'r');
        if (!$handle) throw new Exception('No se pudo abrir');
        fgetcsv($handle, 0, ';');
        $rows = [];
        while (($row = fgetcsv($handle, 0, ';')) !== false) $rows[] = $row;
        fclose($handle);
        return $rows;
    }

    private function processRow(array $row, DateTime $today, array &$historicoBatch, array &$resumenBatch, int $lineNum): void
    {
        try {
            if (count($row) < 13) return;

            // ✅ Mapeo de columnas del Excel (índices 0-based)
            // A=0: ID PREVISION SIC | B=1: CODIGO PROTOCOLO | C=2: NOMBRE
            // D=3: PROGRAMACION    | E=4: PERIODICIDAD      | F=5: FECHA
            // G=6: MES             | H=7: N SEMANA          | I=8: CODIGO ESPECIALIDAD
            // J=9: ESPECIALIDAD    | K=10: HORAS            | L=11: TIPO
            // M=12: ESTADO
            $idPrevRaw     = trim((string)($row[0] ?? ''));
            $codigoProt    = trim((string)($row[1] ?? ''));
            $nombre        = trim((string)($row[2] ?? ''));
            $programacion  = trim((string)($row[3] ?? ''));  // ✅ NUEVO: Para periodicidad
            $fechaRaw      = $row[5] ?? '';
            $mesRaw        = trim((string)($row[6] ?? ''));  // ✅ Columna G (ya viene como "enero")
            $semanaRaw     = $row[7] ?? null;
            $idEspRaw      = $row[8] ?? null;
            $hhRaw         = trim((string)($row[10] ?? '0'));
            $tipoRaw       = strtoupper(trim((string)($row[11] ?? 'INTERNA')));
            $estadoRaw     = strtolower(trim((string)($row[12] ?? '')));

            // Validar ID
            $idPrev = preg_match('/^\d+$/', $idPrevRaw) ? (int)$idPrevRaw : null;
            if (!$idPrev) return;

            // ✅ Parsear periodicidad desde PROGRAMACION
            $periodicidad = $this->parsePeriodicidad($programacion);

            // Especialidad
            $idEspecialidad = !empty($idEspRaw) && is_numeric($idEspRaw) ? (int)$idEspRaw : 0;
            
            $hhPlan = floatval(str_replace(',', '.', $hhRaw)) ?: 0.0;
            $tipo   = ($tipoRaw === 'EXT') ? 'EXTERNA' : 'INTERNA';
            $estado = $this->normalizeEstado($estadoRaw);
            $fecha  = $this->parseDate($fechaRaw);
            $semana = is_numeric($semanaRaw) ? (int)$semanaRaw : null;

            // Normalizar mes (si viene de fórmula, ya vendrá como "enero")
            $mes = $this->normalizeMes($mesRaw);

            $stmt = $this->db->prepare("SELECT id_prevision_sic FROM ot_resumen_actual WHERE id_prevision_sic = ?");
            $stmt->execute([$idPrev]);
            $current = $stmt->fetch(PDO::FETCH_ASSOC);

            $isInsert = !$current;
            if ($isInsert) $this->stats['inserted']++;
            else $this->stats['updated']++;

            // Calcular días de retraso
            $diasRetraso = 0;
            if ($fecha && in_array($estado, ['pendiente', 'asignada', 'en_ejecucion'])) {
                try {
                    $dateObj = new DateTime($fecha);
                    if ($dateObj < $today) $diasRetraso = $today->diff($dateObj)->days;
                } catch (Exception $e) {}
            }

            // Batch data: 14 elementos
            $historicoBatch[] = [$idPrev, $codigoProt, $nombre, $fecha, $estado, $hhPlan, 0.0, $tipo, $tipoRaw, $lineNum];
            $resumenBatch[] = [
                $idPrev, $codigoProt, $nombre, $fecha, $estado,
                $hhPlan, 0.0, $diasRetraso, $tipo, $idEspecialidad,
                $mes, $semana, $periodicidad, $isInsert ? 'NUEVO' : 'ACTUALIZACION'
            ];

        } catch (Exception $e) {
            $this->stats['errors']++;
            if ($this->stats['errors'] <= 3) {
                $this->stats['logs'][] = "⚠️ Línea {$lineNum}: " . $e->getMessage();
            }
        }
    }

    private function flushBatch(array $historico, array $resumen): void
    {
        if (!empty($resumen)) {
            $sqlResumen = "INSERT INTO ot_resumen_actual (
                codigo_ot, id_prevision_sic, primera_fecha_programada, primera_carga,
                ultima_fecha_programada, ultimo_estado, ultima_carga,
                total_hh_planificadas, total_hh_reales_acumuladas, veces_reprogramadas,
                dias_retraso, id_vertical, id_especialidad, nombre_equipo, tipo_mantenimiento,
                mes_carga, semana_carga, periodicidad
            ) VALUES (
                ?, ?, ?, NOW(), ?, ?, NOW(), ?, 0, 0, ?, ?, ?, ?, ?, ?, ?, ?
            ) ON DUPLICATE KEY UPDATE
                ultima_fecha_programada = VALUES(ultima_fecha_programada),
                ultimo_estado = VALUES(ultimo_estado),
                ultima_carga = NOW(),
                total_hh_planificadas = VALUES(total_hh_planificadas),
                total_hh_reales_acumuladas = VALUES(total_hh_reales_acumuladas),
                dias_retraso = VALUES(dias_retraso),
                nombre_equipo = VALUES(nombre_equipo),
                tipo_mantenimiento = VALUES(tipo_mantenimiento),
                mes_carga = VALUES(mes_carga),
                semana_carga = VALUES(semana_carga),
                periodicidad = VALUES(periodicidad),
                veces_reprogramadas = IF(
                    VALUES(ultima_fecha_programada) IS NOT NULL 
                    AND VALUES(ultima_fecha_programada) != ultima_fecha_programada,
                    veces_reprogramadas + 1,
                    veces_reprogramadas
                )";
            
            $stmtResumen = $this->db->prepare($sqlResumen);
            foreach ($resumen as $r) {
                $stmtResumen->execute([
                    $r[1],  // 1. codigo_ot
                    $r[0],  // 2. id_prevision_sic
                    $r[3],  // 3. primera_fecha_programada
                    $r[3],  // 4. ultima_fecha_programada
                    $r[4],  // 5. ultimo_estado
                    $r[5],  // 6. total_hh_planificadas
                    $r[7],  // 7. dias_retraso
                    null,   // 8. id_vertical
                    $r[9],  // 9. id_especialidad
                    $r[2],  // 10. nombre_equipo
                    $r[8],  // 11. tipo_mantenimiento
                    $r[10], // 12. mes_carga
                    $r[11], // 13. semana_carga
                    $r[12]  // 14. periodicidad
                ]);
            }
        }

        if (!empty($historico)) {
            $sqlHist = "INSERT INTO ot_historico (
                codigo_ot, id_prevision_sic, fecha_carga, fuente, fecha_programada,
                estado, hh_planificadas, hh_reales, observaciones, id_vertical,
                id_especialidad, id_equipo, nombre_equipo, nombre_protocolo
            ) VALUES (?, ?, NOW(), 'MANTENCION', ?, ?, ?, 0, '', ?, ?, ?, ?, ?)";
            
            $stmtHist = $this->db->prepare($sqlHist);
            foreach ($historico as $h) {
                $stmtHist->execute([
                    $h[1], $h[0], $h[3], $h[4], $h[5], $h[6],
                    null, null, null, $h[2], $h[1]
                ]);
            }
        }
    }

    // ============================================================
    // HELPERS MEJORADOS
    // ============================================================
    
    /**
     * ✅ Parsea fechas en múltiples formatos: M/D/YY, DD-MM-YYYY, YYYY-MM-DD
     * PhpSpreadsheet ya devuelve fechas formateadas con toArray(true, true)
     */
    private function parseDate($raw): ?string
    {
        if (empty($raw)) return null;
        $raw = trim((string)$raw);
        
        // 1. Ya viene en formato MySQL (YYYY-MM-DD)
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) return $raw;
        
        // 2. PhpSpreadsheet puede devolver timestamp serial de Excel
        if (is_numeric($raw) && $raw > 20000 && $raw < 100000) {
            // Convertir serial Excel a fecha
            $unix = ($raw - 25569) * 86400;
            return date('Y-m-d', $unix);
        }
        
        // 3. Separar por / - .
        $parts = preg_split('/[\/\-.]/', $raw);
        if (count($parts) === 3) {
            $p1 = (int)$parts[0];
            $p2 = (int)$parts[1];
            $p3 = (int)$parts[2];
            
            // Año de 2 dígitos → 20xx
            if ($p3 < 100) $p3 = $p3 > 50 ? 1900 + $p3 : 2000 + $p3;
            
            // Formato M/D/YYYY (US) - el MÁS común en tu Excel: 7/27/26
            if ($p1 >= 1 && $p1 <= 12 && $p2 >= 1 && $p2 <= 31 && $p3 >= 2000) {
                $dateStr = sprintf('%04d-%02d-%02d', $p3, $p1, $p2);
                $dt = DateTime::createFromFormat('Y-m-d', $dateStr);
                if ($dt && $dt->format('Y-m-d') === $dateStr) return $dateStr;
            }
            
            // Formato D/M/YYYY (EU)
            if ($p1 >= 1 && $p1 <= 31 && $p2 >= 1 && $p2 <= 12 && $p3 >= 2000) {
                $dateStr = sprintf('%04d-%02d-%02d', $p3, $p2, $p1);
                $dt = DateTime::createFromFormat('Y-m-d', $dateStr);
                if ($dt && $dt->format('Y-m-d') === $dateStr) return $dateStr;
            }
        }
        
        // 4. Fallback: strtotime
        $ts = strtotime($raw);
        return ($ts && $ts > 0) ? date('Y-m-d', $ts) : null;
    }

    private function normalizeEstado(string $raw): string
    {
        if (strpos($raw, 'complet') !== false || strpos($raw, 'cerrad') !== false) return 'completada';
        if (strpos($raw, 'ejecuc') !== false || strpos($raw, 'proceso') !== false) return 'en_ejecucion';
        if (strpos($raw, 'asignad') !== false) return 'asignada';
        if (strpos($raw, 'reprog') !== false) return 'reprogramada';
        if (strpos($raw, 'cancel') !== false) return 'cancelada';
        return 'pendiente';
    }

    // ✅ NUEVO: Parsea periodicidad desde la columna PROGRAMACION
    private function parsePeriodicidad(string $raw): string
    {
        $raw = strtoupper($raw);
        if (strpos($raw, 'SEMANAL') !== false) return 'Semanal';
        if (strpos($raw, 'MENSUAL') !== false) return 'Mensual';
        if (strpos($raw, 'BIMESTRAL') !== false) return 'Bimestral';
        if (strpos($raw, 'TRIMESTRAL') !== false) return 'Trimestral';
        if (strpos($raw, 'SEMESTRAL') !== false) return 'Semestral';
        if (strpos($raw, 'ANUAL') !== false) return 'Anual';
        if (strpos($raw, 'BIENAL') !== false) return 'Bienal';
        if (strpos($raw, 'QUINQUENAL') !== false) return 'Quinquenal';
        return 'Otro';
    }

    // ✅ NUEVO: Normaliza nombre de mes (por si viene con mayúsculas o abreviaturas)
    private function normalizeMes(string $raw): string
    {
        $raw = strtolower(trim($raw));
        $map = [
            'january' => 'enero', 'jan' => 'enero',
            'february' => 'febrero', 'feb' => 'febrero',
            'march' => 'marzo', 'mar' => 'marzo',
            'april' => 'abril', 'apr' => 'abril',
            'may' => 'mayo',
            'june' => 'junio', 'jun' => 'junio',
            'july' => 'julio', 'jul' => 'julio',
            'august' => 'agosto', 'aug' => 'agosto',
            'september' => 'septiembre', 'sep' => 'septiembre', 'set' => 'septiembre',
            'october' => 'octubre', 'oct' => 'octubre',
            'november' => 'noviembre', 'nov' => 'noviembre',
            'december' => 'diciembre', 'dec' => 'diciembre'
        ];
        return $map[$raw] ?? $raw;
    }
}