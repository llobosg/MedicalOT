<?php
// api/import_sic.php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !hasRole(['admin_hosp', 'admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acceso denegado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['sicFile'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Archivo no proporcionado']);
    exit;
}

$sessionId = session_id();
$uploadDir = __DIR__ . '/../uploads/temp/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

// === INICIALIZAR PROGRESO ===
$progress = [
    'session_id' => $sessionId,
    'total_rows' => 0,
    'processed_rows' => 0,
    'inserted_count' => 0,
    'updated_count' => 0,
    'skipped_count' => 0,
    'error_count' => 0,
    'status' => 'processing',
    'started_at' => date('Y-m-d H:i:s')
];
saveProgress($progress);

try {
    $file = $_FILES['sicFile'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if ($ext !== 'csv') {
        throw new Exception("Extensión inválida: {$ext}. Solo se permiten archivos .csv");
    }
    
    $tempPath = $uploadDir . 'sic_' . uniqid() . '.csv';
    if (!move_uploaded_file($file['tmp_name'], $tempPath)) {
        throw new Exception("Error al guardar archivo temporal");
    }
    
    // === LEER Y VALIDAR CSV ===
    $handle = fopen($tempPath, 'r');
    if (!$handle) throw new Exception("No se pudo abrir el archivo");
    
    $headers = fgetcsv($handle, 0, ';');
    if (!$headers || count($headers) < 10) {
        throw new Exception("Formato de CSV inválido: cabeceras incorrectas");
    }
    
    // Mapeo de columnas esperadas
    $expectedColumns = [
        'ID PREVISION SIC', 'CODIGO PROTOCOLO', 'NOMBRE', 'PROGRAMACION',
        'PERIODICIDAD', 'FECHA', 'MES', 'N SEMANA', 'CODIGO ESPECIALIDAD',
        'ESPECIALIDAD', 'HORAS', 'TIPO', 'ESTADO'
    ];
    
    // Validar columnas mínimas
    $missingCols = array_diff($expectedColumns, $headers);
    if (!empty($missingCols)) {
        throw new Exception("Columnas faltantes en CSV: " . implode(', ', $missingCols));
    }
    
    $colIndex = array_flip($headers);
    
    // === PRIMERA PASADA: Detectar duplicados INTERNOS ===
    $seenIds = [];
    $rowsToProcess = [];
    $lineNumber = 1;
    
    while (($data = fgetcsv($handle, 0, ';')) !== false) {
        $lineNumber++;
        if (count($data) < count($headers)) continue;
        
        $row = array_combine($headers, $data);
        $idPrevision = trim($row['ID PREVISION SIC'] ?? '');
        
        if (empty($idPrevision) || !is_numeric($idPrevision)) {
            $progress['error_count']++;
            continue;
        }
        
        // ✅ VALIDACIÓN: Duplicado dentro del MISMO archivo
        if (isset($seenIds[$idPrevision])) {
            $progress['skipped_count']++;
            continue; // Saltar duplicado interno, no lanzar error
        }
        $seenIds[$idPrevision] = true;
        
        $rowsToProcess[] = [
            'line' => $lineNumber,
            'data' => $row,
            'id_prevision' => (int)$idPrevision
        ];
    }
    fclose($handle);
    
    $progress['total_rows'] = count($rowsToProcess);
    saveProgress($progress);
    
    // === SEGUNDA PASADA: Procesar e Insertar/Actualizar ===
    $pdo = getDBConnection();
    $pdo->beginTransaction();
    
    $stmtUpsert = $pdo->prepare("
        INSERT INTO ots_current (
            id_prevision_sic, codigo_ot, estado, hh_programadas, hh_reales,
            fecha_programada, mes_carga, semana_carga, archivo_origen,
            fecha_primer_registro, estado_anterior, fecha_cambio_estado_anterior
        ) VALUES (
            :id_prevision, :codigo_ot, :estado, :hh_prog, :hh_real,
            :fecha_prog, :mes, :semana, :archivo,
            COALESCE((SELECT fecha_primer_registro FROM ots_current WHERE id_prevision_sic = :id_prevision), NOW()),
            (SELECT estado FROM ots_current WHERE id_prevision_sic = :id_prevision),
            (SELECT fecha_ultima_actualizacion FROM ots_current WHERE id_prevision_sic = :id_prevision)
        )
        ON DUPLICATE KEY UPDATE
            codigo_ot = VALUES(codigo_ot),
            estado = VALUES(estado),
            hh_programadas = VALUES(hh_programadas),
            hh_reales = VALUES(hh_reales),
            fecha_programada = VALUES(fecha_programada),
            mes_carga = VALUES(mes_carga),
            semana_carga = VALUES(semana_carga),
            archivo_origen = VALUES(archivo_origen),
            fecha_ultima_actualizacion = NOW(),
            estado_anterior = CASE WHEN estado != VALUES(estado) THEN estado ELSE estado_anterior END,
            fecha_cambio_estado_anterior = CASE WHEN estado != VALUES(estado) THEN fecha_ultima_actualizacion ELSE fecha_cambio_estado_anterior END
    ");
    
    $stmtHistory = $pdo->prepare("
        INSERT INTO ots_historico (
            id_prevision_sic, codigo_ot, estado_anterior, estado_nuevo,
            hh_programadas_anterior, hh_programadas_nueva,
            hh_reales_anterior, hh_reales_nueva,
            motivo_cambio, archivo_origen
        ) VALUES (
            :id_prevision, :codigo_ot, :estado_ant, :estado_new,
            :hh_prog_ant, :hh_prog_new, :hh_real_ant, :hh_real_new,
            :motivo, :archivo
        )
    ");
    
    $fileName = basename($file['name']);
    
    foreach ($rowsToProcess as $idx => $item) {
        $row = $item['data'];
        $idPrev = $item['id_prevision'];
        
        try {
            // Parsear fecha (soporta múltiples formatos)
            $fechaRaw = trim($row['FECHA'] ?? '');
            $fechaProg = parseDate($fechaRaw);
            
            // Parsear HH (soporta formato con puntos como separador de miles)
            $hhRaw = str_replace('.', '', trim($row['HORAS'] ?? '0'));
            $hhProg = floatval($hhRaw) ?: 0;
            
            $estado = strtolower(trim($row['ESTADO'] ?? 'pendiente'));
            $estado = in_array($estado, ['pendiente','asignada','en_proceso','cerrada']) ? $estado : 'pendiente';
            
            // Obtener estado actual para comparar
            $stmtCheck = $pdo->prepare("SELECT estado, hh_programadas, hh_reales, fecha_ultima_actualizacion FROM ots_current WHERE id_prevision_sic = ?");
            $stmtCheck->execute([$idPrev]);
            $current = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            
            $isInsert = !$current;
            $estadoChanged = $current && $current['estado'] !== $estado;
            $hhChanged = $current && (
                $current['hh_programadas'] != $hhProg || 
                $current['hh_reales'] != 0 // Las HH reales vienen de mantención, no de SIC
            );
            
            // Ejecutar UPSERT
            $stmtUpsert->execute([
                ':id_prevision' => $idPrev,
                ':codigo_ot' => trim($row['CODIGO PROTOCOLO'] ?? ''),
                ':estado' => $estado,
                ':hh_prog' => $hhProg,
                ':hh_real' => 0, // SIC no trae HH reales, eso viene de mantención
                ':fecha_prog' => $fechaProg,
                ':mes' => trim($row['MES'] ?? ''),
                ':semana' => (int)($row['N SEMANA'] ?? 0),
                ':archivo' => $fileName
            ]);
            
            // Registrar en histórico si hubo cambio de estado
            if ($estadoChanged) {
                $stmtHistory->execute([
                    ':id_prevision' => $idPrev,
                    ':codigo_ot' => trim($row['CODIGO PROTOCOLO'] ?? ''),
                    ':estado_ant' => $current['estado'],
                    ':estado_new' => $estado,
                    ':hh_prog_ant' => $current['hh_programadas'],
                    ':hh_prog_new' => $hhProg,
                    ':hh_real_ant' => $current['hh_reales'],
                    ':hh_real_new' => 0,
                    ':motivo' => 'cambio_estado_sic',
                    ':archivo' => $fileName
                ]);
                $progress['updated_count']++;
            } elseif ($isInsert) {
                $progress['inserted_count']++;
            } else {
                $progress['updated_count']++; // Actualización sin cambio de estado
            }
            
        } catch (Exception $e) {
            $progress['error_count']++;
            error_log("Error línea {$item['line']}: " . $e->getMessage());
        }
        
        // Actualizar progreso cada 50 registros para polling
        if ($idx % 50 === 0) {
            $progress['processed_rows'] = $idx + 1;
            saveProgress($progress);
        }
    }
    
    $pdo->commit();
    
    // === FINALIZAR ===
    $progress['processed_rows'] = count($rowsToProcess);
    $progress['status'] = 'completed';
    $progress['completed_at'] = date('Y-m-d H:i:s');
    saveProgress($progress);
    
    // Limpiar archivo temporal
    @unlink($tempPath);
    
    echo json_encode([
        'success' => true,
        'message' => 'Importación completada',
        'total' => count($rowsToProcess),
        'inserted' => $progress['inserted_count'],
        'updated' => $progress['updated_count'],
        'skipped' => $progress['skipped_count'],
        'errors' => $progress['error_count']
    ]);
    
} catch (Exception $e) {
    $progress['status'] = 'error';
    $progress['last_error'] = $e->getMessage();
    $progress['completed_at'] = date('Y-m-d H:i:s');
    saveProgress($progress);
    
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

// === FUNCIONES AUXILIARES ===

function saveProgress($progress) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        INSERT INTO import_progress (
            session_id, total_rows, processed_rows, inserted_count, 
            updated_count, skipped_count, error_count, status, 
            last_error, started_at, completed_at
        ) VALUES (
            :sid, :total, :processed, :inserted, :updated, 
            :skipped, :errors, :status, :error, :started, :completed
        )
        ON DUPLICATE KEY UPDATE
            total_rows = VALUES(total_rows),
            processed_rows = VALUES(processed_rows),
            inserted_count = VALUES(inserted_count),
            updated_count = VALUES(updated_count),
            skipped_count = VALUES(skipped_count),
            error_count = VALUES(error_count),
            status = VALUES(status),
            last_error = VALUES(last_error),
            completed_at = VALUES(completed_at)
    ");
    $stmt->execute([
        ':sid' => $progress['session_id'],
        ':total' => $progress['total_rows'],
        ':processed' => $progress['processed_rows'],
        ':inserted' => $progress['inserted_count'],
        ':updated' => $progress['updated_count'],
        ':skipped' => $progress['skipped_count'],
        ':errors' => $progress['error_count'],
        ':status' => $progress['status'],
        ':error' => $progress['last_error'] ?? null,
        ':started' => $progress['started_at'],
        ':completed' => $progress['completed_at'] ?? null
    ]);
}

function parseDate($dateStr) {
    if (empty($dateStr)) return null;
    
    // Intentar múltiples formatos
    $formats = ['Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y', 'Y/m/d'];
    
    foreach ($formats as $fmt) {
        $dt = DateTime::createFromFormat($fmt, $dateStr);
        if ($dt && $dt->format($fmt) === $dateStr) {
            return $dt->format('Y-m-d');
        }
    }
    
    // Fallback: intentar parsear con strtotime
    $ts = strtotime($dateStr);
    return $ts ? date('Y-m-d', $ts) : null;
}
?>