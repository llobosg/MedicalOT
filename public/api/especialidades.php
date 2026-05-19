<?php
// public/api/especialidades.php
header('Content-Type: application/json; charset=utf-8');
while (ob_get_level()) ob_end_clean();

try {
    // 🔐 Seguridad y Rutas
    if (session_status() === PHP_SESSION_NONE) session_start();

    // 🔐 Configuración de Seguridad y Rutas
    define('APP_ENTRY_POINT', true);
    
    $docRoot     = $_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__);
    $projectRoot = dirname($docRoot);
    $configPath = file_exists("$projectRoot/config.php") ? "$projectRoot/config.php" : null;
    if (!$configPath) throw new Exception("Config no encontrado");
    require_once $configPath;

    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'No autorizado']);
        exit;
    }

    $action = $_GET['action'] ?? 'list';

    if ($action === 'list') {
        $stmt = $pdo->query("SELECT id, codigo, nombre FROM especialidades ORDER BY nombre ASC");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $data]);
    } else {
        throw new Exception("Acción no válida");
    }

} catch (\Throwable $e) {
    error_log("❌ API Especialidades Fatal: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno del servidor.']);
    exit;
}