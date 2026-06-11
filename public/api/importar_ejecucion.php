<?php
/**
 * API Endpoint - Importar Ejecución Real (Cierre de OTs)
 */
header('Content-Type: application/json; charset=utf-8');
define('APP_ENTRY_POINT', true);

// Manejo de errores para asegurar respuesta JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    require_once __DIR__ . '/../../config.php';
    
    // Verificar si la clase existe antes de usarla
    if (!class_exists('App\Services\ImportarEjecucionOT')) {
        require_once __DIR__ . '/../../vendor/autoload.php';
    }
    
    use App\Services\ImportarEjecucionOT;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Método no permitido", 405);
    }

    if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
        $errCode = $_FILES['archivo']['error'] ?? -1;
        throw new Exception("Error al subir archivo (Código: $errCode)", 400);
    }

    $file = $_FILES['archivo'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // ✅ Aceptar CSV y Excel
    if (!in_array($ext, ['xlsx', 'xls', 'csv'])) {
        throw new Exception("Formato inválido. Se permiten .xlsx, .xls o .csv", 400);
    }

    $tmpPath = sys_get_temp_dir() . '/' . uniqid('ejecucion_') . '.' . $ext;
    
    if (!move_uploaded_file($file['tmp_name'], $tmpPath)) {
        throw new Exception("Error interno al mover archivo temporal", 500);
    }

    global $pdo;
    if (!$pdo) {
        throw new Exception("Conexión a BD fallida");
    }

    $importer = new ImportarEjecucionOT($pdo);
    $result = $importer->procesarArchivo($tmpPath);
    
    // Limpiar temporal
    @unlink($tmpPath);
    
    echo json_encode($result);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    // Asegurar que no haya salida previa que rompa el JSON
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