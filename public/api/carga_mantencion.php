<?php
// api/carga_mantencion.php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !hasRole(['admin_hosp', 'admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acceso denegado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['mantencion_file'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Archivo no proporcionado']);
    exit;
}

try {
    $file = $_FILES['mantencion_file'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if ($ext !== 'csv') {
        throw new Exception("Solo se permiten archivos CSV");
    }
    
    $tempPath = sys_get_temp_dir() . '/mant_' . uniqid() . '.csv';
    if (!move_uploaded_file($file['tmp_name'], $tempPath)) {
        throw new Exception("Error al guardar archivo");
    }
    
    $handle = fopen($tempPath, 'r');
    if (!$handle) throw new Exception("No se pudo abrir el archivo");
    
    $headers = fgetcsv($handle, 0, ';');
    if (!$headers) throw new Exception("CSV vacío o formato inválido");
    
    $colIndex = array_flip($headers);
    
    // === MAPEO DE CAMPOS CRÍTICOS ===
    $fieldMap = [
        'id_prevision' => ['ID PREVISION SIC', 'id_prevision_sic', 'ID_SIC'],
        'codigo_ot' => ['OT', 'codigo_ot', 'CÓDIGO OT', 'CODIGO_OT'],
        'hh_reales' => ['HORAS', 'HH_REALES', 'HH EJECUTADAS', 'Horas Reales'],
        'fecha_ejecucion' => ['FECHA', 'FECHA EJECUCIÓN', 'fecha_realizacion'],
        'estado_mant' => ['ESTADO', 'ESTADO_MANT', 'Situación'],
        'observaciones' => ['OBSERVACIONES', 'OBS', 'Notas']
    ];
    
    // Encontrar columnas reales en el archivo
    $mapping = [];
    foreach ($fieldMap as $key => $possibles) {
        foreach ($possibles as $colName) {
            if (isset($colIndex[$colName])) {
                $mapping[$key] = $colIndex[$colName];
                break;
            }
        }
    }
    
    if (!isset($mapping['id_prevision'])) {
        throw new Exception("No se encontró columna de ID de previsión SIC. Columnas disponibles: " . implode(', ', array_slice($headers, 0, 15)));
    }
    
    $pdo = getDBConnection();
    $pdo->beginTransaction();
    
    $stmtUpdate = $pdo->prepare("
        UPDATE ots_current 
        SET 
            hh_reales = COALESCE(:hh_real, hh_reales),
            fecha_ejecucion = COALESCE(:fecha_ejec, fecha_ejecucion),
            observaciones_mant = :obs,
            fecha_ultima_actualizacion = NOW()
        WHERE id_prevision_sic = :id_prev
    ");
    
    $stmtHistory = $pdo->prepare("
        INSERT INTO ots_historico (
            id_prevision_sic, codigo_ot, estado_anterior, estado_nuevo,
            hh_reales_anterior, hh_reales_nueva, motivo_cambio, archivo_origen
        ) VALUES (
            :id_prev, :codigo_ot, :estado_ant, :estado_new,
            :hh_real_ant, :hh_real_new, :motivo, :archivo
        )
    ");
    
    $stats = [
        'processed' => 0,
        'updated' => 0,
        'not_found' => 0,
        'errors' => 0,
        'logs' => []
    ];
    
    $fileName = basename($file['name']);
    $lineNumber = 1;
    
    while (($data = fgetcsv($handle, 0, ';')) !== false) {
        $lineNumber++;
        if (count($data) < count($headers)) continue;
        
        $row = array_combine($headers, $data);
        
        // Obtener ID de previsión
        $idPrev = null;
        if (isset($mapping['id_prevision']) && !empty($data[$mapping['id_prevision']])) {
            $idPrev = (int)preg_replace('/[^0-9]/', '', $data[$mapping['id_prevision']]);
        }
        
        if (!$idPrev) {
            $stats['errors']++;
            continue;
        }
        
        // Parsear HH reales (manejar formato con puntos como miles)
        $hhReal = null;
        if (isset($mapping['hh_reales']) && !empty($data[$mapping['hh_reales']])) {
            $hhRaw = str_replace('.', '', trim($data[$mapping['hh_reales']]));
            $hhReal = floatval($hhRaw) ?: null;
        }
        
        // Parsear fecha
        $fechaEjec = null;
        if (isset($mapping['fecha_ejecucion']) && !empty($data[$mapping['fecha_ejecucion']])) {
            $fechaEjec = parseDate($data[$mapping['fecha_ejecucion']]);
        }
        
        // Obtener observaciones
        $obs = isset($mapping['observaciones']) ? trim($data[$mapping['observaciones']] ?? '') : '';
        
        try {
            // Verificar si la OT existe en ots_current
            $stmtCheck = $pdo->prepare("SELECT id_prevision_sic, hh_reales, estado FROM ots_current WHERE id_prevision_sic = ?");
            $stmtCheck->execute([$idPrev]);
            $current = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            
            if (!$current) {
                $stats['not_found']++;
                // Opcional: registrar log para OTs no encontradas
                if ($stats['not_found'] <= 5) {
                    $stats['logs'][] = "⚠️ OT ID {$idPrev} no encontrada en base actual (línea {$lineNumber})";
                }
                continue;
            }
            
            // Actualizar solo si hay HH reales nuevas o diferentes
            if ($hhReal !== null && $hhReal != $current['hh_reales']) {
                $stmtUpdate->execute([
                    ':id_prev' => $idPrev,
                    ':hh_real' => $hhReal,
                    ':fecha_ejec' => $fechaEjec,
                    ':obs' => substr($obs, 0, 500) // Limitar longitud
                ]);
                
                // Registrar cambio de HH en histórico
                $stmtHistory->execute([
                    ':id_prev' => $idPrev,
                    ':codigo_ot' => $current['codigo_ot'] ?? '',
                    ':estado_ant' => $current['estado'],
                    ':estado_new' => $current['estado'], // Estado no cambia por mantención
                    ':hh_real_ant' => $current['hh_reales'],
                    ':hh_real_new' => $hhReal,
                    ':motivo' => 'actualizacion_hh_reales',
                    ':archivo' => $fileName
                ]);
                
                $stats['updated']++;
            }
            
            $stats['processed']++;
            
        } catch (Exception $e) {
            $stats['errors']++;
            if ($stats['errors'] <= 3) {
                $stats['logs'][] = "❌ Error línea {$lineNumber}: " . $e->getMessage();
            }
        }
    }
    
    fclose($handle);
    $pdo->commit();
    
    @unlink($tempPath);
    
    // Agregar resumen a logs si hay espacio
    if (count($stats['logs']) < 20) {
        $stats['logs'][] = "✅ Procesadas: {$stats['processed']} | Actualizadas: {$stats['updated']} | No encontradas: {$stats['not_found']}";
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Carga de mantención completada',
        'stats' => $stats
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function parseDate($dateStr) {
    if (empty($dateStr)) return null;
    $formats = ['Y-m-d', 'd/m/Y', 'm/d/Y', 'Y/m/d'];
    foreach ($formats as $fmt) {
        $dt = DateTime::createFromFormat($fmt, $dateStr);
        if ($dt && $dt->format($fmt) === $dateStr) return $dt->format('Y-m-d');
    }
    $ts = strtotime($dateStr);
    return $ts ? date('Y-m-d', $ts) : null;
}
?>