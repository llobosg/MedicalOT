<?php
/**
 * API Endpoint - Importar Ejecución Real (Optimizado para Railway)
 */
header('Content-Type: application/json; charset=utf-8');
define('APP_ENTRY_POINT', true);

// Configurar límites agresivos
ini_set('memory_limit', '1024M');
set_time_limit(300);
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    require_once __DIR__ . '/../../config.php';
    require_once __DIR__ . '/../../vendor/autoload.php';
    require_once __DIR__ . '/../../src/Services/ImportarEjecucionOT.php';

    use App\Services\ImportarEjecucionOT;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Método no permitido", 405);
    }

    if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Error al subir archivo", 400);
    }

    $file = $_FILES['archivo'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($ext, ['xlsx', 'xls', 'csv'])) {
        throw new Exception("Formato inválido. Use .xlsx, .xls o .csv", 400);
    }

    $tmpPath = sys_get_temp_dir() . '/' . uniqid('ejecucion_') . '.' . $ext;
    
    if (!move_uploaded_file($file['tmp_name'], $tmpPath)) {
        throw new Exception("Error moviendo archivo temporal", 500);
    }

    global $pdo;
    if (!$pdo) {
        throw new Exception("Conexión BD fallida");
    }

    // ✅ PROCESAMIENTO CON LIBERACIÓN DE MEMORIA
    $importer = new ImportarEjecucionOT($pdo);
    
    // Deshabilitar autocommit para mejorar velocidad de inserción masiva
    $pdo->beginTransaction();
    
    try {
        $result = $importer->procesarArchivo($tmpPath);
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
    @unlink($tmpPath);
    
    // Forzar limpieza de memoria antes de responder
    gc_collect_cycles();
    
    echo json_encode($result);
    exit;

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    ob_clean();
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage(),
        'debug' => [
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ]
    ]);
    exit;
}