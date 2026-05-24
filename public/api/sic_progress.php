<?php
// public/api/sic_progress.php
header('Content-Type: application/json; charset=utf-8');
while (ob_get_level()) ob_end_clean();

try {
    define('APP_ENTRY_POINT', true);
    
    // 🔧 Ruta dinámica para config.php
    $docRoot     = $_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__);
    $projectRoot = dirname($docRoot);
    $configPath = file_exists("$projectRoot/config.php") ? "$projectRoot/config.php" : null;
    
    if (!$configPath) {
        throw new Exception("Archivo de configuración no encontrado");
    }
    require_once $configPath;

    // Validar sesión
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'No autorizado']);
        exit;
    }

    // Simular progreso (ajustar según tu lógica real)
    // Idealmente, usarías una tabla de progreso o cache en BD
    echo json_encode([
        'status' => 'completed', // o 'processing'
        'current' => 100,
        'inserted' => 50,
        'skipped' => 5,
        'total_estimated' => 100
    ]);
    exit;

} catch (Throwable $e) {
    error_log("❌ sic_progress.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    exit;
}