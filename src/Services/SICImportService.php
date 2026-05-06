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

    public function import(string $filePath, string $originalName): array
    {
        $this->stats = ['total' => 0, 'inserted' => 0, 'skipped' => 0, 'errors' => [], 'warnings' => []];
        
        // ✅ VALIDACIÓN DE EXTENSIÓN YA SE HIZO EN import_sic.php. No repetir aquí.
        
        // Crear registro de lote
        $hash = md5_file($filePath);
        $stmtLote = $this->db->prepare("INSERT INTO lote_carga_sic (nombre_archivo, hash_md5, registros_totales, registros_omision) VALUES (?, ?, 0, 0)");
        $stmtLote->execute([$originalName, $hash]);
        $loteId = (int)$this->db->lastInsertId();

        $this->db->beginTransaction();
        try {
            $this->processStreaming($filePath, $loteId);
            $this->db->commit();
            return ['success' => true] + $this->stats;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("SIC Import Fatal: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            throw new Exception("Error crítico en importación: " . $e->getMessage());
        }
    }

    private function processStreaming(string $filePath, int $loteId): void
    {
        $_SESSION['sic_progress'] = ['current' => 0, 'total' => 0, 'status' => 'processing'];
        
        $handle = fopen($filePath, 'r');
        if (!$handle) throw new Exception("No se pudo abrir el archivo temporal");
        fgetcsv($handle, 0, ',', '"', "\\"); // Saltar cabecera

        // Preparar statements (igual que antes)
        $stmtEsp  = $this->db->prepare("INSERT IGNORE INTO especialidades (codigo, nombre) VALUES (?, ?)");
        $stmtProt = $this->db->prepare("INSERT IGNORE INTO protocolos (codigo, nombre, familia, periodicidad) VALUES (?, ?, ?, ?)");
        $stmtArea = $this->db->prepare("INSERT IGNORE INTO areas (codigo, nombre) VALUES (?, ?)");
        $stmtProv = $this->db->prepare("INSERT IGNORE INTO proveedores (rut, razon_social) VALUES (?, ?)");
        $stmtRuta = $this->db->prepare("INSERT IGNORE INTO rutas (codigo, nombre) VALUES (?, ?)");
        $stmtEq   = $this->db->prepare("INSERT IGNORE INTO equipos (codigo, nombre, marca, modelo, serie, umdns, id_area) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmtGetArea = $this->db->prepare("SELECT id FROM areas WHERE codigo = ? LIMIT 1");
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

        $rowNum = 1;
        while (($row = fgetcsv($handle, 0, ',', '"', "\\")) !== false) {
            $rowNum++;
            $this->stats['total']++;
            $_SESSION['sic_progress']['total'] = $this->stats['total'];
            $row = array_pad($row, 30, '');
            $ot = trim($row[1] ?? '');
            if (empty($ot)) continue;

            if (!empty($row[22])) $stmtEsp->execute([trim($row[22]), trim($row[23] ?? '')]);
            if (!empty($row[6]))  $stmtProt->execute([trim($row[6]), trim($row[7] ?? ''), trim($row[8] ?? ''), trim($row[10] ?? '')]);
            if (!empty($row[12])) $stmtArea->execute([trim($row[12]), trim($row[13] ?? '')]);
            if (!empty($row[24])) $stmtProv->execute([trim($row[24]), trim($row[25] ?? '')]);
            if (!empty($row[27])) $stmtRuta->execute([trim($row[27]), trim($row[28] ?? '')]);
            if (!empty($row[14])) {
                $stmtGetArea->execute([trim($row[12] ?? '')]);
                $areaId = $stmtGetArea->fetchColumn() ?: null;
                $stmtEq->execute([trim($row[14]), trim($row[15] ?? ''), trim($row[17] ?? ''), trim($row[18] ?? ''), trim($row[19] ?? ''), trim($row[20] ?? ''), $areaId]);
            }

            $fecha = !empty($row[0]) ? date('Y-m-d', strtotime(trim($row[0]))) : null;
            try {
                $otStmt->execute([
                    $ot, $fecha, trim($row[5] ?? 'Mañana'), (int)($row[3] ?? 0), trim($row[4] ?? ''),
                    trim($row[6] ?? ''), trim($row[14] ?? ''), trim($row[12] ?? ''), trim($row[22] ?? ''), trim($row[24] ?? ''), trim($row[27] ?? ''), $loteId
                ]);
                $this->stats['inserted']++;
            } catch (Exception $e) {
                $this->stats['errors'][] = "Fila $rowNum (OT: $ot): " . $e->getMessage();
                $this->stats['skipped']++;
            }

            // Actualizar progreso cada 50 filas
            if ($rowNum % 50 === 0) {
                $_SESSION['sic_progress']['current'] = $this->stats['inserted'] + $this->stats['skipped'];
                session_write_close(); session_start(); // Forzar guardado sin bloquear
            }
        }
        fclose($handle);
        $_SESSION['sic_progress']['status'] = 'completed';
        
        $this->db->prepare("UPDATE lote_carga_sic SET registros_totales = ?, registros_omision = ? WHERE id = ?")
            ->execute([$this->stats['inserted'] + $this->stats['skipped'], $this->stats['skipped'], $loteId]);
    }
}