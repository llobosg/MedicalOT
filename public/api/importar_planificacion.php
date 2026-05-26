<?php
/**
 * API Endpoint - Importar Planificación HH
 * CON TRAZAS DE DEPURACIÓN
 */

// ✅ LOG 1: Inicio del script
error_log("🔵 [IMPORT] === INICIO importacion_planificacion.php ===");
error_log("🔵 [IMPORT] REQUEST_METHOD: " . ($_SERVER['REQUEST_METHOD'] ?? 'N/A'));
error_log("🔵 [IMPORT] PHP Version: " . phpversion());

// Capturar CUALQUIER output inesperado
ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    http_response_code(200);
    exit;
}

define('APP_ENTRY_POINT', true);

// ✅ LOG 2: Carga de config
error_log("🔵 [IMPORT] Cargando config.php...");
$configPath = __DIR__ . '/../../config.php';
if (!file_exists($configPath)) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Config no encontrado']);
    exit;
}
require_once $configPath;
error_log("🟢 [IMPORT] config.php cargado OK");

// Verificar conexión PDO
global $pdo;
if (!isset($pdo) || !($pdo instanceof PDO)) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'PDO no disponible']);
    exit;
}
error_log("🟢 [IMPORT] Conexión PDO activa");

// ✅ LOG 3: Carga de autoloader
error_log("🔵 [IMPORT] Cargando vendor/autoload.php...");
$autoloadPath = __DIR__ . '/../../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Composer autoload no encontrado']);
    exit;
}
require_once $autoloadPath;
error_log("🟢 [IMPORT] Autoloader cargado OK");

// ✅ LOG 4: Verificar PhpSpreadsheet
if (!class_exists('PhpOffice\PhpSpreadsheet\IOFactory')) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'PhpSpreadsheet no instalado']);
    exit;
}
error_log("🟢 [IMPORT] PhpSpreadsheet disponible");

// ✅ LOG 5: Carga del servicio
error_log("🔵 [IMPORT] Cargando ImportarPlanificacionHH.php...");
$servicePath = __DIR__ . '/../../src/Services/ImportarPlanificacionHH.php';
if (!file_exists($servicePath)) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Servicio no encontrado: ' . $servicePath]);
    exit;
}
require_once $servicePath;
error_log("🟢 [IMPORT] Servicio cargado OK");

use App\Services\ImportarPlanificacionHH;

try {
    // ✅ LOG 6: Validaciones
    error_log("🔵 [IMPORT] Validando request...");
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido', 405);
    }

    if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
        $errCode = $_FILES['archivo']['error'] ?? -1;
        error_log("❌ [IMPORT] Error upload: código=$errCode");
        throw new Exception("Error subida archivo (código: $errCode)", 400);
    }

    $archivo = $_FILES['archivo'];
    error_log("🟢 [IMPORT] Archivo recibido: " . $archivo['name'] . " (" . round($archivo['size']/1024) . " KB)");

    $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ['xlsx', 'xls'])) {
        throw new Exception('Solo Excel (.xlsx, .xls)', 400);
    }

    if ($archivo['size'] > 20 * 1024 * 1024) {
        throw new Exception('Archivo excede 20MB', 400);
    }

    // ✅ LOG 7: Parámetros
    $año = filter_input(INPUT_POST, 'año', FILTER_VALIDATE_INT);
    $mes = filter_input(INPUT_POST, 'mes', FILTER_VALIDATE_INT);
    error_log("🔵 [IMPORT] Params: año=$año, mes=$mes");

    // Mover archivo temporal
    $rutaTemporal = sys_get_temp_dir() . '/' . uniqid('planificacion_') . '.' . $extension;
    if (!move_uploaded_file($archivo['tmp_name'], $rutaTemporal)) {
        throw new Exception('Error moviendo archivo temporal', 500);
    }
    error_log("🟢 [IMPORT] Archivo temporal: $rutaTemporal");

    // Sesión
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $usuarioId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    error_log("🔵 [IMPORT] Usuario ID: " . ($usuarioId ?? 'null'));

    // ✅ LOG 8: Procesamiento
    error_log("🔵 [IMPORT] Creando instancia ImportarPlanificacionHH...");
    $importador = new ImportarPlanificacionHH($pdo);
    error_log("🟢 [IMPORT] Instancia creada OK");

    error_log("🔵 [IMPORT] Iniciando procesarArchivo()...");
    $resultado = $importador->procesarArchivo($rutaTemporal, $año, $mes, $usuarioId);
    error_log("🟢 [IMPORT] procesarArchivo() completado. Success=" . ($resultado['success'] ? 'true' : 'false'));

    // Limpiar temporal
    if (file_exists($rutaTemporal)) {
        @unlink($rutaTemporal);
    }

    // ✅ LOG 9: Respuesta
    $jsonResponse = json_encode($resultado, JSON_UNESCAPED_UNICODE);
    error_log("🟢 [IMPORT] JSON generado (" . strlen($jsonResponse) . " bytes)");
    
    // Limpiar buffer antes de enviar JSON limpio
    ob_end_clean();
    echo $jsonResponse;

    error_log("🔵 [IMPORT] === FIN EXITOSO ===");

} catch (Exception $e) {
    // Limpiar buffer
    if (ob_get_level() > 0) ob_end_clean();
    
    $code = $e->getCode();
    if (!is_int($code) || $code < 100 || $code > 599) {
        $code = 500;
    }
    
    error_log("❌ [IMPORT] EXCEPTION: " . $e->getMessage());
    error_log("❌ [IMPORT] File: " . $e->getFile() . ":" . $e->getLine());
    error_log("❌ [IMPORT] Trace: " . $e->getTraceAsString());
    
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => [
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ]
    ]);

} catch (Error $e) {
    // Limpiar buffer
    if (ob_get_level() > 0) ob_end_clean();
    
    error_log("💥 [IMPORT] FATAL ERROR: " . $e->getMessage());
    error_log("💥 [IMPORT] File: " . $e->getFile() . ":" . $e->getLine());
    error_log("💥 [IMPORT] Trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error fatal: ' . $e->getMessage(),
        'debug' => [
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ]
    ]);
}