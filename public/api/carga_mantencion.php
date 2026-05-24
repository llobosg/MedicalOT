<?php
// public/api/carga_mantencion.php
header('Content-Type: application/json; charset=utf-8');
while (ob_get_level()) ob_end_clean();

try {
    define('APP_ENTRY_POINT', true);
    
    $docRoot     = $_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__);
    $projectRoot = dirname($docRoot);
    $configPath  = file_exists("$projectRoot/config.php") ? "$projectRoot/config.php" : null;
    if (!$configPath) throw new Exception("Archivo de configuración no encontrado");
    require_once $configPath;

    // 🔌 Cargar autoloader de Composer (CRÍTICO para PhpSpreadsheet)
    $autoloadPath = file_exists("$projectRoot/vendor/autoload.php") ? "$projectRoot/vendor/autoload.php" : null;
    if ($autoloadPath) {
        require_once $autoloadPath;
    } else {
        throw new Exception("vendor/autoload.php no encontrado. Ejecuta 'composer install'");
    }

    // Ahora sí incluir el servicio
    require_once "$projectRoot/src/Services/MantencionImportService.php";

    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['admin', 'admin_hospital', 'admin_hosp'])) {
        http_response_code(403);
        throw new Exception('Acceso denegado. Solo administradores.');
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['mantencion_file'])) {
        throw new Exception('No se recibió archivo en la petición.');
    }

    $file = $_FILES['mantencion_file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Error al subir archivo (Código: ' . $file['error'] . ')');
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['xlsx', 'csv'])) {
        throw new Exception('Solo se permiten archivos .xlsx o .csv');
    }

    $tempPath = sys_get_temp_dir() . '/mant_' . uniqid() . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $tempPath)) {
        throw new Exception('Error al guardar archivo temporal');
    }

    require_once "$projectRoot/src/Services/MantencionImportService.php";
    $service = new \App\Services\MantencionImportService($pdo);
    $service->processFile($tempPath, $file['name']);

    unlink($tempPath);

    echo json_encode([
        'success' => true,
        'message' => 'Carga de mantenimiento completada',
        'stats'   => $service->stats
    ]);

} catch (Throwable $e) {
    error_log("❌ Carga Mantención: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}