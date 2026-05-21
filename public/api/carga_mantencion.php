<?php
// public/api/carga_mantencion.php
header('Content-Type: application/json; charset=utf-8');
while (ob_get_level()) ob_end_clean();

try {
    define('APP_ENTRY_POINT', true);
    
    $docRoot     = $_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__);
    $projectRoot = dirname($docRoot);
    $configPath = file_exists("$projectRoot/config.php") ? "$projectRoot/config.php" : null;
    if (!$configPath) throw new Exception("Config no encontrado");
    require_once $configPath;

    if (session_status() === PHP_SESSION_NONE) session_start();
    
    // Autenticación
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'No autorizado']);
        exit;
    }

    // Autorización (Solo Admin)
    $rolUsuario = $_SESSION['user_role'] ?? '';
    if (!in_array($rolUsuario, ['admin', 'admin_hospital', 'admin_hosp'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Acceso denegado. Solo Administradores.']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Método no permitido");
    }

    if (!isset($_FILES['mantencion_file'])) {
        throw new Exception("No se recibió archivo");
    }

    $file = $_FILES['mantencion_file'];
    
    // Validar extensión
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    if ($ext !== 'csv') {
        throw new Exception("Solo se permiten archivos CSV");
    }

    // Mover archivo temporalmente
    $tempPath = sys_get_temp_dir() . '/mantencion_' . uniqid() . '.csv';
    if (!move_uploaded_file($file['tmp_name'], $tempPath)) {
        throw new Exception("Error al subir el archivo al servidor temporal");
    }

    // Incluir el Servicio de Importación
    require_once $projectRoot . '/src/Services/MantencionImportService.php';
    
    // Instanciar y ejecutar
    $service = new \App\Services\MantencionImportService($pdo);
    $service->processFile($tempPath);

    // Limpiar archivo temporal
    unlink($tempPath);

    echo json_encode([
        'success' => true,
        'message' => 'Carga completada',
        'stats' => $service->stats
    ]);

} catch (\Throwable $e) {
    error_log("Error Carga Mantención: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}