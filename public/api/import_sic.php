<?php
// 1. FORZAR JSON Y LIMPIAR BUFFERS ANTES DE CUALQUIER INCLUDE
header('Content-Type: application/json; charset=utf-8');
if (ob_get_level()) ob_end_clean();

// 2. AUMENTAR LÍMITES PHP PARA ARCHIVOS GRANDES
ini_set('post_max_size', '50M');
ini_set('upload_max_filesize', '50M');
ini_set('max_execution_time', '120');
ini_set('memory_limit', '256M');

try {
    define('APP_ENTRY_POINT', true);
    require_once __DIR__ . '/../../vendor/autoload.php';
    require_once __DIR__ . '/../../config.php';
    
    if (session_status() === PHP_SESSION_NONE) session_start();
    
    if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin_hosp') {
        throw new Exception('Acceso no autorizado. Se requiere sesión de Admin Hospital.', 403);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['sicFile']) || $_FILES['sicFile']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Método o archivo inválido. Error upload: ' . ($_FILES['sicFile']['error'] ?? 'unknown'));
    }

    $file = $_FILES['sicFile'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if ($ext !== 'csv') {
        throw new Exception('Solo se aceptan archivos .csv');
    }

    $servicePath = __DIR__ . '/../../src/Services/SICImportService.php';
    if (!file_exists($servicePath)) {
        throw new Exception('Servicio de importación SIC no encontrado.');
    }
    require_once $servicePath;

    $service = new \MedicalOT\Services\SICImportService($pdo);
    $result = $service->import($file['tmp_name'], basename($file['name']));
    
    http_response_code(200);
    echo json_encode($result);
    exit;
    
} catch (\Throwable $e) {
    error_log("❌ API import_sic Fatal: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code($e->getCode() ?: 500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}