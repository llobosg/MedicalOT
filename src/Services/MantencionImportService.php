<?php
// src/Services/MantencionImportService.php
namespace App\Services;

use PDO;
use Exception;
use DateTime;
use PhpOffice\PhpSpreadsheet\IOFactory; // Composer: phpoffice/phpspreadsheet

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
                $this->processRow($row, $today, &$historicoBatch, &$resumenBatch, $idx + 2);
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

    // =========================================================
    // LECTURA DE ARCHIVO (XLSX o CSV)
    // =========================================================
    private function readFile(string $path): array
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        
        if ($ext === 'xlsx') {
            // Requiere: composer require phpoffice/phpspreadsheet
            if (!class_exists(IOFactory::class)) {
                throw new Exception('La librería PhpSpreadsheet no está instalada. Ejecuta: composer require phpoffice/phpspreadsheet');
            }
            $spreadsheet = IOFactory::load($path);
            $sheet = $spreadsheet->getSheetByName('NEW BD');
            if (!$sheet) throw new Exception('Hoja "NEW BD" no encontrada en el Excel.');
            
            $data = $sheet->toArray(null, false, false);
            array_shift($data); // Saltar encabezados
            return $data;
        }

        // Fallback CSV
        $handle = fopen($path, 'r');
        if (!$handle) throw new Exception('No se pudo abrir el archivo');
        fgetcsv($handle, 0, ','); // Saltar encabezados CSV
        
        $rows = [];
        while (($row = fgetcsv($handle, 0, ',')) !== false) $rows[] = $row;
        fclose($handle);
        return $rows;
    }

    // =========================================================
    // PROCESAMIENTO DE FILA
    // =========================================================
    private function processRow(array $row, DateTime $today, array &$historicoBatch, array &$resumenBatch, int $lineNum): void
    {
        try {
            if (count($row) < 12) return;

            // 1. Parsear Columnas (0-based según Excel NEW BD)
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

            // 2. Verificar si existe en ots_current
            $stmt = $this->db->prepare("SELECT id_prevision_sic, hh_programadas, hh_reales, estado FROM ots_current WHERE id_prevision_sic = ?");
            $stmt->execute([$idPrev]);
            $current = $stmt->fetch(PDO::FETCH_ASSOC);

            $isInsert = !$current;
            $hhChanged = $current && abs($current['hh_programadas'] - $hhPlan) > 0.001;
            $stateChanged = $current && $current['estado'] !== $estado;

            if ($isInsert) $this->stats['inserted']++;
            else $this->stats['updated']++;

            // 3. Calcular retraso
            $diasRetraso = 0;
            if ($fecha && in_array($estado, ['pendiente', 'asignada', 'en_proceso'])) {
                $dateObj = new DateTime($fecha);
                if ($dateObj < $today) $diasRetraso = $today->diff($dateObj)->days;
            }

            // 4. Armar batches
            $historicoBatch[] = [$idPrev, $codigoProt, $nombre, $fecha, $estado, $hhPlan, 0.0, $tipo, $tipoRaw, $lineNum];
            
            $resumenBatch[] = [
                $idPrev, $codigoProt, $nombre, $fecha, $estado, 
                $hhPlan, 0.0, $diasRetraso, $tipo, $isInsert ? 'NUEVO_SIC' : 'ACTUALIZACION'
            ];

            // Log condicional para errores/ausentes
            if (!$current && !$isInsert) $this->stats['not_found']++;

        } catch (Exception $e) {
            $this->stats['errors']++;
            if ($this->stats['errors'] <= 3) {
                $this->stats['logs'][] = "⚠️ Error línea {$lineNum}: " . $e->getMessage();
            }
        }
    }

    // =========================================================
    // EJECUCIÓN MASIVA (BATCH)
    // =========================================================
    private function flushBatch(array $historico, array $resumen): void
    {
        if (empty($historico)) return;

        // UPSERT ots_current
        $colsResumen = implode(', ', [
            'id_prevision_sic', 'codigo_ot', 'nombre_equipo', 'fecha_programada', 'estado',
            'hh_programadas', 'hh_reales', 'dias_retraso', 'tipo_mantenimiento', 'archivo_origen'
        ]);
        $valsResumen = implode(', ', array_fill(0, count($resumen[0]), '?'));
        $updateFields = "estado=VALUES(estado), hh_programadas=VALUES(hh_programadas), fecha_programada=VALUES(fecha_programada), 
                         dias_retraso=VALUES(dias_retraso), tipo_mantenimiento=VALUES(tipo_mantenimiento), archivo_origen=VALUES(archivo_origen)";

        $sqlResumen = "INSERT INTO ots_current ($colsResumen) VALUES ($valsResumen) ON DUPLICATE KEY UPDATE $updateFields";
        $stmtResumen = $this->db->prepare($sqlResumen);
        foreach ($resumen as $row) $stmtResumen->execute($row);

        // INSERT ot_historico
        $colsHist = "id_prevision_sic, codigo_ot, nombre_equipo, fecha_programada, estado, hh_planificadas, hh_reales, tipo_mantenimiento, archivo_origen";
        $valsHist = implode(', ', array_fill(0, count($historico[0]), '?'));
        $sqlHist = "INSERT INTO ot_historico ($colsHist) VALUES ($valsHist)";
        $stmtHist = $this->db->prepare($sqlHist);
        foreach ($historico as $row) $stmtHist->execute($row);
    }

    // =========================================================
    // HELPERS
    // =========================================================
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
        if (strpos($raw, 'complet') !== false || strpos($raw, 'cerrad') !== false) return 'cerrada';
        if (strpos($raw, 'ejecuc') !== false || strpos($raw, 'proceso') !== false) return 'en_proceso';
        if (strpos($raw, 'asignad') !== false) return 'asignada';
        return 'pendiente';
    }
}