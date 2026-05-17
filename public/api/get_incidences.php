<?php
header('Content-Type: application/json; charset=utf-8');
while (ob_get_level()) ob_end_clean();

try {
    // 🔍 RESOLUCIÓN INTELIGENTE DE RUTAS (Igual que en convenios.php)
    $docRoot     = $_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__);
    $projectRoot = dirname($docRoot);
    
    // Buscar config.php en includes/ dentro del proyecto o docroot
    $configPath = file_exists("$projectRoot/includes/config.php") 
                ? "$projectRoot/includes/config.php" 
                : (file_exists("$docRoot/includes/config.php") ? "$docRoot/includes/config.php" : null);
                
    if (!$configPath) {
        throw new Exception("includes/config.php no encontrado. Verifica la estructura del proyecto.");
    }
    
    require_once $configPath;

    $otCode = $_GET['ot'] ?? '';

    if (empty($otCode)) {
        echo json_encode(['success' => false, 'incidencias' => []]);
        exit;
    }

    try {
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
        error_log("Error getting incidences: " . $e->getMessage());
        echo json_encode(['success' => false, 'incidencias' => [], 'error' => $e->getMessage()]);
    }

} catch (\Throwable $e) {
    error_log("❌ API Get Incidences Fatal: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}