<?php
header('Content-Type: application/json; charset=utf-8');
if (ob_get_level()) ob_end_clean();

try {
    define('APP_ENTRY_POINT', true);
    require_once __DIR__ . '/../../vendor/autoload.php';
    require_once __DIR__ . '/../../config.php';
    
    if (session_status() === PHP_SESSION_NONE) session_start();
    
    if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin_hosp') {
        throw new Exception('Acceso no autorizado.', 403);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método inválido.', 405);
    }

    if (!isset($_FILES['sicFile'])) {
        throw new Exception('No se recibió archivo en la petición.', 400);
    }

    $file = $_FILES['sicFile'];
    $uploadErrors = [
        UPLOAD_ERR_INI_SIZE => 'El archivo supera el límite del servidor (máx 50MB)',
        UPLOAD_ERR_FORM_SIZE => 'El archivo supera el límite del formulario',
        UPLOAD_ERR_PARTIAL => 'La subida se interrumpió o falló parcialmente',
        UPLOAD_ERR_NO_FILE => 'No se seleccionó ningún archivo',
        UPLOAD_ERR_NO_TMP_DIR => 'Falta directorio temporal en el servidor',
        UPLOAD_ERR_CANT_WRITE => 'Error al escribir el archivo en disco',
        UPLOAD_ERR_EXTENSION => 'Una extensión de PHP bloqueó la subida'
    ];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        error_log("📤 Upload Error: " . ($uploadErrors[$file['error']] ?? "Código: {$file['error']}"));
        throw new Exception($uploadErrors[$file['error']] ?? "Error de subida: " . $file['error']);
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext !== 'csv') {
        throw new Exception('Solo se aceptan archivos .csv. Se detectó: .' . $ext);
    }

    $servicePath = __DIR__ . '/../../src/Services/SICImportService.php';
    if (!file_exists($servicePath)) {
        throw new Exception('Servicio SICImportService.php no encontrado en el servidor.');
    }
    require_once $servicePath;

    $service = new \MedicalOT\Services\SICImportService($pdo);
    $result = $service->import($file['tmp_name'], basename($file['name']));
    
    http_response_code(200);
    // 🛡️ Limpieza total de buffers antes de responder
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8', true);
    
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
    
} catch (\PDOException $e) {
    error_log("❌ DB Error: " . $e->getMessage());
    while (ob_get_level()) ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Error de base de datos: ' . $e->getMessage(),
        'debug' => $e->getCode()
    ], JSON_UNESCAPED_UNICODE);
    exit;
} catch (\Throwable $e) {
    error_log("❌ FATAL: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    while (ob_get_level()) ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}