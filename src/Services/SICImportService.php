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
           // ✅ HASH ÚNICO POR CARGA (incluye timestamp para permitir rehacargas al mismo archivo)
        $fileHash = md5_file($filePath);
        $uploadHash = $fileHash . '_' . time();
        
        $stmtLote = $this->db->prepare("INSERT INTO lote_carga_sic (nombre_archivo, hash_md5, registros_totales, registros_omision) VALUES (?, ?, 0, 0)");
        $stmtLote->execute([$originalName, $uploadHash]);
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

     /**
     * Extrae el ID numérico de la cadena HTML <div id="cambios_XXXXX"...>
     */
    private function extractPrevisionId(string $htmlString): ?int
    {
        if (empty($htmlString)) return null;
        
        // Busca el patrón cambios_NUMERO usando Regex
        // Ejemplo: <div id="cambios_358121" ...> -> retorna 358121
        if (preg_match('/cambios_(\d+)/', $htmlString, $matches)) {
            return (int)$matches[1];
        }
        return null;
    }

    private function processStreaming(string $filePath, int $loteId): void
    {
        // Inicializar estado en sesión
        $_SESSION['sic_progress'] = [
            'status' => 'processing',
            'current' => 0,
            'total' => 0,
            'percent' => 0,
            'inserted' => 0,
            'skipped' => 0
        ];

        $handle = fopen($filePath, 'r');
        if (!$handle) throw new Exception("No se pudo abrir el archivo temporal");

        // Saltar cabecera
        fgetcsv($handle, 0, ',', '"', "\\");

        // Preparar statements para catálogos (Igual que antes)
        $stmtEsp  = $this->db->prepare("INSERT IGNORE INTO especialidades (codigo, nombre) VALUES (?, ?)");
        $stmtProt = $this->db->prepare("INSERT IGNORE INTO protocolos (codigo, nombre, familia, periodicidad) VALUES (?, ?, ?, ?)");
        $stmtArea = $this->db->prepare("INSERT IGNORE INTO areas (codigo, nombre) VALUES (?, ?)");
        $stmtRuta = $this->db->prepare("INSERT IGNORE INTO rutas (codigo, nombre) VALUES (?, ?)");
        $stmtEq   = $this->db->prepare("INSERT IGNORE INTO equipos (codigo, nombre, marca, modelo, serie, umdns, id_area) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmtGetArea = $this->db->prepare("SELECT id FROM areas WHERE codigo = ? LIMIT 1");
        
        // Statement para verificar duplicados RÁPIDO
        $stmtCheckDup = $this->db->prepare("SELECT 1 FROM ordenes_trabajo WHERE codigo_ot = ? LIMIT 1");

        // Statement principal de inserción
        $otStmt = $this->db->prepare("
            INSERT INTO ordenes_trabajo (
                codigo_ot, fecha_programada, turno, semana_num, dia_semana,
                id_protocolo, id_equipo, id_area, id_especialidad, rut_proveedor, id_ruta,
                hh_programadas, estado, id_lote, id_prevision_sic
            ) VALUES (
                ?, ?, ?, ?, ?,
                (SELECT id FROM protocolos WHERE codigo=?),
                (SELECT id FROM equipos WHERE codigo=?),
                (SELECT id FROM areas WHERE codigo=?),
                (SELECT id FROM especialidades WHERE codigo=?),
                ?,
                (SELECT id FROM rutas WHERE codigo=?),
                0, 'pendiente', ?, ?
            )
        ");

        $rowNum = 1;
        $processedCount = 0;
        $updateInterval = 50; // Actualizar sesión cada 50 filas

        while (($row = fgetcsv($handle, 0, ',', '"', "\\")) !== false) {
            $rowNum++;
            $this->stats['total']++;
            $row = array_pad($row, 30, '');
            $ot = trim($row[1] ?? '');
            
            if (empty($ot)) continue;

            // Sincronizar catálogos
            if (!empty($row[22])) $stmtEsp->execute([trim($row[22]), trim($row[23] ?? '')]);
            if (!empty($row[6]))  $stmtProt->execute([trim($row[6]), trim($row[7] ?? ''), trim($row[8] ?? ''), trim($row[10] ?? '')]);
            if (!empty($row[12])) $stmtArea->execute([trim($row[12]), trim($row[13] ?? '')]);
            
            // ❌ ELIMINADO: Inserción en proveedores
            // if (!empty($row[24])) $stmtProv->execute([trim($row[24]), trim($row[25] ?? '')]);
            
            if (!empty($row[27])) $stmtRuta->execute([trim($row[27]), trim($row[28] ?? '')]);

            if (!empty($row[14])) {
                $stmtGetArea->execute([trim($row[12] ?? '')]);
                $areaId = $stmtGetArea->fetchColumn() ?: null;
                $stmtEq->execute([trim($row[14]), trim($row[15] ?? ''), trim($row[17] ?? ''), trim($row[18] ?? ''), trim($row[19] ?? ''), trim($row[20] ?? ''), $areaId]);
            }

            $fecha = !empty($row[0]) ? date('Y-m-d', strtotime(trim($row[0]))) : null;
            // NUEVO: Extraer ID Previsión de la Columna C (índice 2 usualmente, verificar tu CSV)
            // Asumiendo que la columna C es el índice 2 en el array $row
            $rawColumnC = trim($row[2] ?? ''); 
            $previsionId = $this->extractPrevisionId($rawColumnC);
            
            try {
                // Pasamos el rut_proveedor como string vacío o null si la columna existe en BD
                // Si la columna rut_proveedor fue eliminada de ordenes_trabajo, ajusta la query arriba quitando ese campo
                 $rutProv = trim($row[24] ?? ''); 
                
                // AGREGAR $previsionId AL FINAL DEL ARRAY
                $otStmt->execute([
                    $ot, $fecha, trim($row[5] ?? 'Mañana'), (int)($row[3] ?? 0), trim($row[4] ?? ''),
                    trim($row[6] ?? ''), trim($row[14] ?? ''), trim($row[12] ?? ''), trim($row[22] ?? ''), 
                    $rutProv, 
                    trim($row[27] ?? ''), $loteId, $previsionId // <-- NUEVO PARÁMETRO
                ]);
                $this->stats['inserted']++;
            } catch (Exception $e) {
                $this->stats['errors'][] = "Fila $rowNum (OT: $ot): " . $e->getMessage();
                $this->stats['skipped']++;
            }

            $processedCount++;

            // Actualizar progreso...
            if ($processedCount % $updateInterval === 0) {
                 $_SESSION['sic_progress']['current'] = $this->stats['inserted'] + $this->stats['skipped'];
                 $_SESSION['sic_progress']['total'] = $this->stats['total'];
                 session_write_close(); 
                 session_start(); 
            }
        }
        
        fclose($handle);
        
        // Finalizar
        $_SESSION['sic_progress']['status'] = 'completed';
        $_SESSION['sic_progress']['current'] = $this->stats['total'];
        $_SESSION['sic_progress']['percent'] = 100;
        $_SESSION['sic_progress']['inserted'] = $this->stats['inserted'];
        $_SESSION['sic_progress']['skipped'] = $this->stats['skipped'];

        // Actualizar estadísticas del lote en BD
        $this->db->prepare("UPDATE lote_carga_sic SET registros_totales = ?, registros_omision = ? WHERE id = ?")
            ->execute([$this->stats['inserted'] + $this->stats['skipped'], $this->stats['skipped'], $loteId]);
    }
}