<?php
// Forzar respuesta JSON y limpiar buffers
header('Content-Type: application/json; charset=utf-8');
if (ob_get_level()) ob_end_clean();

try {
    define('APP_ENTRY_POINT', true);
    
    // Verificar y cargar dependencias de forma segura
    $autoload = __DIR__ . '/../../vendor/autoload.php';
    if (!file_exists($autoload)) {
        throw new Exception('Composer no instalado. Verifique el despliegue en Railway.');
    }
    require_once $autoload;
    
    $config = __DIR__ . '/../../config.php';
    if (!file_exists($config)) {
        throw new Exception('Archivo config.php no encontrado.');
    }
    require_once $config;
    
    // Iniciar sesión sin redirecciones HTML
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Validar autenticación y rol
    if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin_hosp') {
        throw new Exception('Acceso no autorizado. Se requiere sesión de Admin Hospital.', 403);
    }
    
    // Validar petición y archivo
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
    
    // Cargar servicio
    $servicePath = __DIR__ . '/../../src/Services/SICImportService.php';
    if (!file_exists($servicePath)) {
        throw new Exception('Servicio de importación SIC no encontrado.');
    }
    require_once $servicePath;
    
    // Ejecutar importación
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