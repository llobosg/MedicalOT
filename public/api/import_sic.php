<?php
// public/api/import_sic.php
header('Content-Type: application/json; charset=utf-8');
while (ob_get_level()) ob_end_clean();

try {
    define('APP_ENTRY_POINT', true);
    
    // 🔧 Ruta dinámica para config.php (compatible Railway + local)
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

    // Validar método POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Método no permitido']);
        exit;
    }

    // Validar archivo
    if (!isset($_FILES['sicFile']) || $_FILES['sicFile']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No se recibió el archivo o hubo error en la subida');
    }

    $file = $_FILES['sicFile']['tmp_name'];
    $handle = fopen($file, 'r');
    if (!$handle) throw new Exception('No se pudo abrir el archivo');

    $header = fgetcsv($handle, 0, ';');
    if (!$header || count($header) < 3) {
        fclose($handle);
        throw new Exception('Formato CSV inválido');
    }

    // Procesar registros (ejemplo simplificado)
    $inserted = 0; $skipped = 0; $errors = []; $total = 0;
    
    while (($row = fgetcsv($handle, 0, ';')) !== false) {
        $total++;
        // Aquí iría tu lógica de inserción usando $pdo
        // Ejemplo: $stmt = $pdo->prepare("INSERT INTO ...");
        $inserted++; // Simulado
    }
    fclose($handle);

    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => 'Proceso completado',
        'total' => $total,
        'inserted' => $inserted,
        'skipped' => $skipped,
        'errors' => $errors
    ]);
    exit;

} catch (Throwable $e) {
    error_log("❌ import_sic.php: " . $e->getMessage() . " en línea " . $e->getLine());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    exit;
}