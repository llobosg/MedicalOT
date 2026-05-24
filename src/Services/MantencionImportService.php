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
        'processed' => 0,
        'updated'   => 0,
        'inserted'  => 0,
        'not_found' => 0,
        'errors'    => 0,
        'logs'      => []
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
        if (empty($rows)) throw new Exception('No se encontraron datos válidos en el archivo.');

        $this->stats['logs'][] = "📄 Archivo: {$fileName} | Filas leídas: " . count($rows);

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

            if (!empty($resumenBatch)) {
                $this->flushBatch($historicoBatch, $resumenBatch);
            }

            $this->db->commit();
            $this->stats['logs'][] = "✅ Finalizado. Actualizadas: {$this->stats['updated']} | Nuevas: {$this->stats['inserted']}";

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
                throw new Exception('PhpSpreadsheet no instalada. Ejecuta: composer require phpoffice/phpspreadsheet');
            }
            $spreadsheet = IOFactory::load($path);
            $sheet = $spreadsheet->getSheetByName('NEW BD');
            if (!$sheet) throw new Exception('Hoja "NEW BD" no encontrada.');
            
            $data = $sheet->toArray(null, false, false);
            array_shift($data);
            return $data;
        }

        // Fallback CSV
        $handle = fopen($path, 'r');
        if (!$handle) throw new Exception('No se pudo abrir el archivo');
        fgetcsv($handle, 0, ';'); // Saltar encabezados
        $rows = [];
        while (($row = fgetcsv($handle, 0, ';')) !== false) $rows[] = $row;
        fclose($handle);
        return $rows;
    }

    private function processRow(array $row, DateTime $today, array &$historicoBatch, array &$resumenBatch, int $lineNum): void
    {
        try {
            if (count($row) < 12) return;

            $idPrevRaw   = trim($row[0] ?? '');
            $codigoProt  = trim($row[1] ?? '');
            $nombre      = trim($row[2] ?? '');
            $fechaRaw    = trim($row[5] ?? '');
            $hhRaw       = trim($row[10] ?? '0');
            $tipoRaw     = strtoupper(trim($row[11] ?? 'INTERNA'));
            $estadoRaw   = strtolower(trim($row[12] ?? ''));

            $idPrev = preg_match('/^\d+$/', $idPrevRaw) ? (int)$idPrevRaw : null;
            if (!$idPrev) return;

            $hhPlan  = floatval(str_replace(',', '.', $hhRaw)) ?: 0.0;
            $tipo    = ($tipoRaw === 'EXT') ? 'EXTERNA' : 'INTERNA';
            $estado  = $this->normalizeEstado($estadoRaw);
            $fecha   = $this->parseDate($fechaRaw);

            // Verificar existencia en ot_resumen_actual
            $stmt = $this->db->prepare("SELECT codigo_ot, total_hh_planificadas, total_hh_reales_acumuladas, ultimo_estado FROM ot_resumen_actual WHERE id_prevision_sic = ?");
            $stmt->execute([$idPrev]);
            $current = $stmt->fetch(PDO::FETCH_ASSOC);

            $isInsert = !$current;
            if ($isInsert) $this->stats['inserted']++;
            else $this->stats['updated']++;

            $diasRetraso = 0;
            if ($fecha && in_array($estado, ['pendiente', 'asignada', 'en_proceso'])) {
                $dateObj = new DateTime($fecha);
                if ($dateObj < $today) $diasRetraso = $today->diff($dateObj)->days;
            }

            // Armar batches (estructura fija de 10 elementos)
            $historicoBatch[] = [$idPrev, $codigoProt, $nombre, $fecha, $estado, $hhPlan, 0.0, $tipo, $tipoRaw, $lineNum];
            
            $resumenBatch[] = [
                $idPrev, $codigoProt, $nombre, $fecha, $estado, 
                $hhPlan, 0.0, $diasRetraso, $tipo, $isInsert ? 'NUEVO' : 'ACTUALIZACION'
            ];

        } catch (Exception $e) {
            $this->stats['errors']++;
            if ($this->stats['errors'] <= 3) {
                $this->stats['logs'][] = "⚠️ Error línea {$lineNum}: " . $e->getMessage();
            }
        }
    }

    private function flushBatch(array $historico, array $resumen): void
    {
        if (empty($resumen)) return;

        // 1. UPSERT en ot_resumen_actual (tabla que alimenta KPIs)
        $colsResumen = "codigo_ot, id_prevision_sic, primera_fecha_programada, primera_carga, ultima_fecha_programada, ultimo_estado, ultima_carga, total_hh_planificadas, total_hh_reales_acumuladas, veces_reprogramadas, dias_retraso, id_vertical, id_especialidad, nombre_equipo, tipo_mantenimiento";
        $valsResumen = implode(', ', array_fill(0, 15, '?'));
        $updateFields = "ultima_fecha_programada=VALUES(ultima_fecha_programada), 
                 ultimo_estado=VALUES(ultimo_estado), 
                 ultima_carga=VALUES(ultima_carga), 
                 total_hh_planificadas=VALUES(total_hh_planificadas), 
                 total_hh_reales_acumuladas=VALUES(total_hh_reales_acumuladas), 
                 dias_retraso=VALUES(dias_retraso), 
                 nombre_equipo=VALUES(nombre_equipo), 
                 tipo_mantenimiento=VALUES(tipo_mantenimiento),
                 veces_reprogramadas = IF(VALUES(ultima_fecha_programada) != ultima_fecha_programada, veces_reprogramadas + 1, veces_reprogramadas)";

        $sqlResumen = "INSERT INTO ot_resumen_actual ($colsResumen) VALUES ($valsResumen) ON DUPLICATE KEY UPDATE $updateFields";
        $stmtResumen = $this->db->prepare($sqlResumen);

        foreach ($resumen as $row) {
            // $row = [id_prev, codigo_prot, nombre, fecha, estado, hhPlan, hhReal, diasRetraso, tipo, origen]
            $stmtResumen->execute([
                $row[1],                 // codigo_ot
                $row[0],                 // id_prevision_sic
                $row[3],                 // primera_fecha_programada
                date('Y-m-d H:i:s'),     // primera_carga
                $row[3],                 // ultima_fecha_programada
                $row[4],                 // ultimo_estado
                date('Y-m-d H:i:s'),     // ultima_carga
                $row[5],                 // total_hh_planificadas
                $row[6],                 // total_hh_reales_acumuladas
                0,                       // veces_reprogramadas
                $row[7],                 // dias_retraso
                null,                    // id_vertical
                null,                    // id_especialidad
                $row[2],                 // nombre_equipo
                $row[8]                  // tipo_mantenimiento
            ]);
        }

        // 2. INSERT en ot_historico (bitácora de cargas)
        if (!empty($historico)) {
            $colsHist = "codigo_ot, id_prevision_sic, fecha_carga, fuente, fecha_programada, estado, hh_planificadas, hh_reales, observaciones, id_vertical, id_especialidad, id_equipo, nombre_equipo, nombre_protocolo";
            $valsHist = implode(', ', array_fill(0, 14, '?'));
            $sqlHist = "INSERT INTO ot_historico ($colsHist) VALUES ($valsHist)";
            $stmtHist = $this->db->prepare($sqlHist);

            foreach ($historico as $hRow) {
                $stmtHist->execute([
                    $hRow[1], $hRow[0], date('Y-m-d H:i:s'), 'MANTENCION',
                    $hRow[3], $hRow[4], $hRow[5], $hRow[6], '', null, null, null, $hRow[2], $hRow[9] ?? ''
                ]);
            }
        }
    }

    private function parseDate(?string $raw): ?string
    {
        if (empty($raw)) return null;
        $parts = explode('/', trim($raw));
        if (count($parts) !== 3) return null;
        $m = str_pad($parts[0], 2, '0', STR_PAD_LEFT);
        $d = str_pad($parts[1], 2, '0', STR_PAD_LEFT);
        $y = strlen($parts[2]) == 2 ? '20' . $parts[2] : $parts[2];
        return "$y-$m-$d";
    }

    private function normalizeEstado(string $raw): string
    {
        if (strpos($raw, 'complet') !== false || strpos($raw, 'cerrad') !== false) return 'completada';
        if (strpos($raw, 'ejecuc') !== false || strpos($raw, 'proceso') !== false) return 'en_ejecucion';
        if (strpos($raw, 'asignad') !== false) return 'asignada';
        return 'pendiente';
    }
}