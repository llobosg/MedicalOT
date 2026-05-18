<?php
// public/api/get_incidences.php
header('Content-Type: application/json; charset=utf-8');
while (ob_get_level()) ob_end_clean();

try {
    // 🔐 1. PROTECCIÓN DE SESIÓN (Evita el 403/Acceso Denegado)
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Verificar si el usuario está logueado
    if (!isset($_SESSION['user_id'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Acceso denegado. Sesión expirada o inválida.']);
        exit;
    }

    // 🔍 2. RESOLUCIÓN DE RUTAS SEGURA
    $docRoot     = $_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__);
    $projectRoot = dirname($docRoot);
    
    // Buscar config.php en la raíz del proyecto (según tu corrección anterior)
    $configPath = file_exists("$projectRoot/config.php") 
                ? "$projectRoot/config.php" 
                : (file_exists("$docRoot/config.php") ? "$docRoot/config.php" : null);
                
    if (!$configPath) {
        throw new Exception("config.php no encontrado.");
    }
    
    require_once $configPath;

    // Obtener parámetro OT
    $otCode = $_GET['ot'] ?? '';

    if (empty($otCode)) {
        echo json_encode(['success' => true, 'incidencias' => []]);
        exit;
    }

    try {
        // Consultar incidencias de la BD
        $stmt = $pdo->prepare("
            SELECT id, tipo, descripcion, evidencia_path, fecha_registro 
            FROM incidencias 
            WHERE codigo_ot = ? 
            ORDER BY fecha_registro DESC
        ");
        $stmt->execute([$otCode]);
        $incidencias = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'incidencias' => $incidencias]);
        
    } catch (\Exception $e) {
        // Si hay error de BD, devolvemos JSON vacío para no romper la UI
        error_log("Error DB get_incidences: " . $e->getMessage());
        echo json_encode(['success' => true, 'incidencias' => [], 'db_error' => $e->getMessage()]);
    }

} catch (\Throwable $e) {
    // Error crítico (ej. falta config.php)
    error_log("❌ API Get Incidences Fatal: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno del servidor.']);
    exit;
}