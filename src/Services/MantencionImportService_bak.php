<?php

namespace App\Services;

use PDO;
use Exception;
use DateTime;

class MantencionImportService
{
    private PDO $db;

    public array $stats = [
        'processed' => 0,
        'inserted' => 0,
        'errors' => 0,
        'logs' => []
    ];

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;

        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', 0);
        set_time_limit(0);
    }

    public function processFile(string $filePath, int $batchSize = 2000): void
    {
        if (!file_exists($filePath)) {
            throw new Exception("Archivo no encontrado");
        }

        $delimiter = $this->detectDelimiter($filePath);
        $this->stats['logs'][] = "Delimiter: $delimiter";

        $mapping = $this->loadOtMapping();
        $this->stats['logs'][] = "Mapping cargado: " . count($mapping);

        $handle = fopen($filePath, 'r');
        fgetcsv($handle, 0, $delimiter); // header

        $historicoBatch = [];
        $resumenBatch = [];

        $this->db->beginTransaction();

        $rowNum = 1;
        $today = new DateTime();

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rowNum++;

            try {
                if (count($row) < 12) continue;

                $idPrevision = $this->parseId($row[0]);
                if (!$idPrevision) continue;

                // 🔥 FIX CLAVE: fallback si no existe mapping
                $codigoOt = $mapping[$idPrevision] ?? ('OT-' . $idPrevision);

                $fecha = $this->parseFecha($row[5]);
                $hh = $this->parseFloat($row[10]);
                $estado = $this->normalizeEstado($row[12] ?? '');
                $tipo = $this->parseTipo($row[11] ?? '');
                $nombreEquipo = trim($row[2] ?? '');
                $protocolo = trim($row[1] ?? '');

                $diasRetraso = $this->calcDelay($fecha, $estado, $today);

                // === HISTORICO ===
                $historicoBatch[] = [
                    $codigoOt,
                    $idPrevision,
                    $fecha,
                    $estado,
                    $hh,
                    null,
                    null,
                    null,
                    $nombreEquipo,
                    $protocolo
                ];

                // === RESUMEN ===
                $resumenBatch[] = [
                    $codigoOt,
                    $idPrevision,
                    $fecha,
                    $fecha,
                    $estado,
                    $hh,
                    $diasRetraso,
                    null,
                    null,
                    $nombreEquipo,
                    $tipo
                ];

                // === FLUSH ===
                if (count($historicoBatch) >= $batchSize) {
                    $this->flush($historicoBatch, $resumenBatch);
                    $historicoBatch = [];
                    $resumenBatch = [];
                }

                if ($this->stats['processed'] % 5000 === 0) {
                    $this->db->commit();
                    $this->db->beginTransaction();
                }

                $this->stats['inserted']++;

            } catch (Exception $e) {
                $this->stats['errors']++;
            }

            $this->stats['processed']++;
        }

        if (!empty($historicoBatch)) {
            $this->flush($historicoBatch, $resumenBatch);
        }

        fclose($handle);
        $this->db->commit();

        $this->stats['logs'][] = "Proceso OK";
    }

    private function flush(array $hist, array $res): void
    {
        // === HISTORICO ===
        if ($hist) {
            $placeholders = [];
            $values = [];

            foreach ($hist as $r) {
                $placeholders[] = "(?, ?, NOW(), 'MANTENCION', ?, ?, ?, 0, NULL, ?, ?, ?, ?, ?)";
                $values = array_merge($values, $r);
            }

            $sql = "INSERT INTO ot_historico (
                codigo_ot, id_prevision_sic, fecha_carga, fuente,
                fecha_programada, estado, hh_planificadas,
                hh_reales, observaciones,
                id_vertical, id_especialidad, id_equipo,
                nombre_equipo, nombre_protocolo
            ) VALUES " . implode(',', $placeholders);

            $this->db->prepare($sql)->execute($values);
        }

        // === RESUMEN ===
        if ($res) {
            $placeholders = [];
            $values = [];

            foreach ($res as $r) {
                $placeholders[] = "(?, ?, ?, NOW(), ?, ?, NOW(), ?, 0, 0, ?, ?, ?, ?, ?)";
                $values = array_merge($values, $r);
            }

            $sql = "INSERT INTO ot_resumen_actual (
                codigo_ot, id_prevision_sic, primera_fecha_programada, primera_carga,
                ultima_fecha_programada, ultimo_estado, ultima_carga,
                total_hh_planificadas, total_hh_reales_acumuladas,
                veces_reprogramadas, dias_retraso,
                id_vertical, id_especialidad,
                nombre_equipo, tipo_mantenimiento
            ) VALUES " . implode(',', $placeholders) . "
            ON DUPLICATE KEY UPDATE
                ultima_fecha_programada = VALUES(ultima_fecha_programada),
                ultimo_estado = VALUES(ultimo_estado),
                total_hh_planificadas = VALUES(total_hh_planificadas),
                dias_retraso = VALUES(dias_retraso),
                tipo_mantenimiento = VALUES(tipo_mantenimiento)";

            $this->db->prepare($sql)->execute($values);
        }
    }

    private function loadOtMapping(): array
    {
        $stmt = $this->db->query("SELECT id_prevision_sic, codigo_ot FROM ordenes_trabajo WHERE id_prevision_sic IS NOT NULL");
        $map = [];
        foreach ($stmt as $r) {
            $map[(int)$r['id_prevision_sic']] = $r['codigo_ot'];
        }
        return $map;
    }

    private function parseId($v): ?int
    {
        $v = preg_replace('/\D/', '', $v);
        return $v ? (int)$v : null;
    }

    private function parseFecha($v): ?string
    {
        if (!$v) return null;

        $parts = explode('-', trim($v));
        if (count($parts) !== 3) return null;

        return "20{$parts[2]}-{$parts[1]}-{$parts[0]}";
    }

    private function parseFloat($v): float
    {
        return (float)str_replace(',', '.', $v);
    }

    private function parseTipo($v): string
    {
        return strtoupper(trim($v)) === 'EXT' ? 'EXTERNA' : 'INTERNA';
    }

    private function calcDelay($fecha, $estado, DateTime $today): int
    {
        if (!$fecha || $estado !== 'pendiente') return 0;

        $f = new DateTime($fecha);
        return ($f < $today) ? $today->diff($f)->days : 0;
    }

    private function detectDelimiter(string $file): string
    {
        $line = fgets(fopen($file, 'r'));
        $delims = [',' => substr_count($line, ','), ';' => substr_count($line, ';')];
        arsort($delims);
        return key($delims);
    }

    private function normalizeEstado(string $v): string
    {
        $v = strtolower($v);

        if (str_contains($v, 'complet')) return 'completada';
        if (str_contains($v, 'ejec')) return 'en_ejecucion';
        if (str_contains($v, 'reprog')) return 'reprogramada';
        if (str_contains($v, 'cancel')) return 'no_realizada';

        return 'pendiente';
    }
}