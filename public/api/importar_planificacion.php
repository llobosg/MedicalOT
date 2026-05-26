<?php
/**
 * API Endpoint - Importar Planificación HH
 * Recibe archivo Excel y procesa la planificación mensual
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

define('APP_ENTRY_POINT', true);
    
$docRoot     = $_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__);
$projectRoot = dirname($docRoot);
$configPath = file_exists("$projectRoot/config.php") ? "$projectRoot/config.php" : null;
    
if (!$configPath) {
    throw new Exception("Archivo de configuración no encontrado");
}

require_once $configPath;
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Services\ImportarPlanificacionHH;

try {
    // Validar método
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido', 405);
    }
    
    // Validar archivo
    if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No se recibió archivo válido', 400);
    }
    
    $archivo = $_FILES['archivo'];
    
    // Validar extensión
    $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ['xlsx', 'xls'])) {
        throw new Exception('Solo se permiten archivos Excel (.xlsx, .xls)', 400);
    }
    
    // Validar tamaño (máximo 10MB)
    if ($archivo['size'] > 10 * 1024 * 1024) {
        throw new Exception('El archivo excede el tamaño máximo de 10MB', 400);
    }
    
    // Obtener parámetros
    $año = filter_input(INPUT_POST, 'año', FILTER_VALIDATE_INT);
    $mes = filter_input(INPUT_POST, 'mes', FILTER_VALIDATE_INT);
    
    if (!$año || $año < 2020 || $año > 2030) {
        throw new Exception('Año inválido', 400);
    }
    
    if (!$mes || $mes < 1 || $mes > 12) {
        throw new Exception('Mes inválido', 400);
    }
    
    // Mover archivo a ubicación temporal
    $rutaTemporal = sys_get_temp_dir() . '/' . uniqid('planificacion_') . '.' . $extension;
    
    if (!move_uploaded_file($archivo['tmp_name'], $rutaTemporal)) {
        throw new Exception('Error al procesar el archivo', 500);
    }
    
    // Obtener ID de usuario (si hay sesión)
    session_start();
    $usuarioId = $_SESSION['user_id'] ?? null;
    
    // Conectar a BD
    $pdo = Database::getConnection();
    
    // Procesar archivo
    $importador = new ImportarPlanificacionHH($pdo);
    $resultado = $importador->procesarArchivo($rutaTemporal, $año, $mes, $usuarioId);
    
    // Limpiar archivo temporal
    if (file_exists($rutaTemporal)) {
        unlink($rutaTemporal);
    }
    
    // Responder
    if ($resultado['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'Importación completada exitosamente',
            'importacion_id' => $resultado['importacion_id'],
            'stats' => $resultado['stats']
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $resultado['error'],
            'stats' => $resultado['stats']
        ]);
    }
    
} catch (Exception $e) {
    $code = $e->getCode() ?: 500;
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}