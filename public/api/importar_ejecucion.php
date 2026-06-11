<?php
header('Content-Type: application/json; charset=utf-8');
define('APP_ENTRY_POINT', true);
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/Services/ImportarEjecucionOT.php';

use App\Services\ImportarEjecucionOT;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

try {
    if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("No se recibió archivo válido.");
    }
    
    $file = $_FILES['archivo'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    // ✅ Aceptar Excel y CSV
    if (!in_array($ext, ['xlsx', 'xls', 'csv'])) {
        throw new Exception("Formato inválido. Se permiten archivos .xlsx, .xls o .csv");
    }
    
    $tmpPath = sys_get_temp_dir() . '/' . uniqid('ejecucion_') . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $tmpPath)) {
        throw new Exception("Error al mover archivo temporal.");
    }
    
    global $pdo;
    $importer = new ImportarEjecucionOT($pdo);
    $result = $importer->procesarArchivo($tmpPath);
    
    @unlink($tmpPath);
    echo json_encode($result);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}