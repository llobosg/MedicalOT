<?php
namespace MedicalOT\Services;

use PDO;
use Exception;

class SICImportService
{
    private PDO $db;
    public array $stats = ['total' => 0, 'inserted' => 0, 'skipped' => 0, 'errors' => [], 'warnings' => []];

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Procesa archivo CSV y retorna estadísticas
     */
    public function import(string $filePath, string $originalName): array
    {
        $this->stats = ['total' => 0, 'inserted' => 0, 'skipped' => 0, 'errors' => [], 'warnings' => []];

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if ($extension !== 'csv') {
            throw new Exception("Formato no válido. Solo se aceptan archivos .csv");
        }

        $rows = $this->readCSV($filePath);
        $this->stats['total'] = count($rows);

        if (empty($rows)) {
            throw new Exception("El archivo no contiene datos procesables");
        }

        $this->db->beginTransaction();
        try {
            $this->syncCatalogs($rows);
            $this->processOrders($rows, $originalName);
            $this->db->commit();
            return ['success' => true] + $this->stats;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("SIC Import Error: " . $e->getMessage());
            throw new Exception("Error crítico en importación: " . $e->getMessage());
        }
    }

    private function readCSV(string $path): array
    {
        $handle = fopen($path, 'r');
        if (!$handle) throw new Exception("No se pudo abrir el archivo");
        
        fgetcsv($handle); // Saltar cabecera
        $data = [];
        while ($row = fgetcsv($handle, 0, ',')) {
            $data[] = array_map('trim', $row);
        }
        fclose($handle);
        return $data;
    }

    private function syncCatalogs(array $rows): void
    {
        $stmtEsp  = $this->db->prepare("INSERT IGNORE INTO especialidades (codigo, nombre) VALUES (?, ?)");
        $stmtProt = $this->db->prepare("INSERT IGNORE INTO protocolos (codigo, nombre, familia, periodicidad) VALUES (?, ?, ?, ?)");
        $stmtArea = $this->db->prepare("INSERT IGNORE INTO areas (codigo, nombre) VALUES (?, ?)");
        $stmtProv = $this->db->prepare("INSERT IGNORE INTO proveedores (rut, razon_social) VALUES (?, ?)");
        $stmtRuta = $this->db->prepare("INSERT IGNORE INTO rutas (codigo, nombre) VALUES (?, ?)");
        $stmtEq   = $this->db->prepare("INSERT IGNORE INTO equipos (codigo, nombre, marca, modelo, serie, umdns, id_area) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmtGetArea = $this->db->prepare("SELECT id FROM areas WHERE codigo = ? LIMIT 1");

        foreach ($rows as $r) {
            if (!empty($r[22])) $stmtEsp->execute([$r[22], $r[23] ?? '']);
            if (!empty($r[6]))  $stmtProt->execute([$r[6], $r[7] ?? '', $r[8] ?? '', $r[10] ?? '']);
            if (!empty($r[12])) $stmtArea->execute([$r[12], $r[13] ?? '']);
            if (!empty($r[24])) $stmtProv->execute([$r[24], $r[25] ?? '']);
            if (!empty($r[27])) $stmtRuta->execute([$r[27], $r[28] ?? '']);

            if (!empty($r[14])) {
                $stmtGetArea->execute([$r[12] ?? '']);
                $areaId = $stmtGetArea->fetchColumn() ?: null;
                $stmtEq->execute([$r[14], $r[15] ?? '', $r[17] ?? '', $r[18] ?? '', $r[19] ?? '', $r[20] ?? '', $areaId]);
            }
        }
    }

    private function processOrders(array $rows, string $fileName): void
    {
        // Crear lote de carga
        $hash = md5_file($rows[0][0] ?? tempnam(sys_get_temp_dir(), 'sic'));
        $loteStmt = $this->db->prepare("INSERT INTO lote_carga_sic (nombre_archivo, hash_md5, registros_totales, registros_omision) VALUES (?, ?, 0, 0)");
        $loteStmt->execute([$fileName, $hash]);
        $loteId = (int)$this->db->lastInsertId();

        $otStmt = $this->db->prepare("
            INSERT IGNORE INTO ordenes_trabajo (
                codigo_ot, fecha_programada, turno, semana_num, dia_semana,
                id_protocolo, id_equipo, id_area, id_especialidad, rut_proveedor, id_ruta,
                hh_programadas, estado, id_lote
            ) VALUES (
                ?, ?, ?, ?, ?,
                (SELECT id FROM protocolos WHERE codigo=?),
                (SELECT id FROM equipos WHERE codigo=?),
                (SELECT id FROM areas WHERE codigo=?),
                (SELECT id FROM especialidades WHERE codigo=?),
                ?,
                (SELECT id FROM rutas WHERE codigo=?),
                0, 'pendiente', ?
            )
        ");

        $skipped = 0;
        foreach ($rows as $r) {
            $ot = $r[1] ?? '';
            if (empty($ot)) {
                $this->stats['errors'][] = "Fila sin código OT válido";
                continue;
            }

            // Verificar duplicado
            $check = $this->db->prepare("SELECT 1 FROM ordenes_trabajo WHERE codigo_ot = ?");
            $check->execute([$ot]);
            if ($check->fetchColumn()) {
                $skipped++;
                continue;
            }

            $fecha = !empty($r[0]) ? date('Y-m-d', strtotime($r[0])) : null;
            try {
                $otStmt->execute([
                    $ot, $fecha, $r[5] ?? 'Mañana', (int)($r[3] ?? 0), $r[4] ?? '',
                    $r[6] ?? '', $r[14] ?? '', $r[12] ?? '', $r[22] ?? '', $r[24] ?? '', $r[27] ?? '', $loteId
                ]);
                $this->stats['inserted']++;
            } catch (Exception $e) {
                $this->stats['errors'][] = "Error procesando $ot: " . $e->getMessage();
            }
        }

        $this->stats['skipped'] = $skipped;
        $updateLote = $this->db->prepare("UPDATE lote_carga_sic SET registros_totales = ?, registros_omision = ? WHERE id = ?");
        $updateLote->execute([$this->stats['inserted'] + $skipped, $skipped, $loteId]);
    }
}