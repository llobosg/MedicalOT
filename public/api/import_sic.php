<?php
// 1. FORZAR RESPONSE JSON (evita que PHP devuelva HTML en errores)
header('Content-Type: application/json; charset=utf-8');
set_error_handler(function($severity, $message, $file, $line) {
    throw new Error($message, 0, $severity, $file, $line);
});

try {
    define('APP_ENTRY_POINT', true);
    require_once __DIR__ . '/../../vendor/autoload.php';
    require_once __DIR__ . '/../../config.php';
    
    // Iniciar sesión sin redirección
    if (session_status() === PHP_SESSION_NONE) session_start();
    
    // Validar rol SIN redirigir a HTML
    if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin_hosp') {
        throw new Exception('Acceso no autorizado. Se requiere sesión de Admin Hospital.', 403);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['sicFile'])) {
        throw new Exception('Método o archivo inválido.', 400);
    }

    $file = $_FILES['sicFile'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($ext, ['csv'])) {
        throw new Exception('Solo se aceptan archivos .csv');
    }
    if ($file['size'] > 50 * 1024 * 1024) {
        throw new Exception('Archivo demasiado grande (máx 50MB)');
    }

    // Cargar servicio dinámicamente
    if (!class_exists(\MedicalOT\Services\SICImportService::class)) {
        require_once __DIR__ . '/../../src/Services/SICImportService.php';
    }

    $service = new \MedicalOT\Services\SICImportService($pdo);
    $result = $service->import($file['tmp_name'], basename($file['name']));
    
    http_response_code(200);
    echo json_encode($result);

} catch (\Throwable $e) {
    error_log("❌ API import_sic Error: " . $e->getMessage() . " | " . $e->getTraceAsString());
    http_response_code($e->getCode() ?: 500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}