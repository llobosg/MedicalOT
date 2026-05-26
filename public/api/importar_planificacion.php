<?php
/**
 * API Endpoint - Importar Planificación HH
 */

// Capturar CUALQUIER output inesperado (warnings, notices, echos)
ob_start();

// Headers CORS
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    http_response_code(200);
    exit;
}

// Permitir acceso desde navegador
define('APP_ENTRY_POINT', true);

// Cargar configuración
$configPath = __DIR__ . '/../../config.php';
if (!file_exists($configPath)) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Archivo de configuración no encontrado']);
    exit;
}

require_once $configPath;

// Limpiar cualquier output generado por config.php
ob_end_clean();

// Cargar autoloader de Composer
$autoloadPath = __DIR__ . '/../../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Composer autoload no encontrado. Ejecuta: composer require phpoffice/phpspreadsheet'
    ]);
    exit;
}

require_once $autoloadPath;

// Cargar el servicio
$servicePath = __DIR__ . '/../../src/Services/ImportarPlanificacionHH.php';
if (!file_exists($servicePath)) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Servicio ImportarPlanificacionHH no encontrado en: ' . $servicePath
    ]);
    exit;
}

require_once $servicePath;

use App\Services\ImportarPlanificacionHH;

try {
    // Validar método
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido. Use POST.', 405);
    }
    
    // Validar archivo
    if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'El archivo excede el tamaño máximo permitido por el servidor',
            UPLOAD_ERR_FORM_SIZE => 'El archivo excede el tamaño máximo del formulario',
            UPLOAD_ERR_PARTIAL => 'El archivo se subió parcialmente',
            UPLOAD_ERR_NO_FILE => 'No se subió ningún archivo',
            UPLOAD_ERR_NO_TMP_DIR => 'Falta el directorio temporal',
            UPLOAD_ERR_CANT_WRITE => 'No se pudo escribir el archivo en disco',
            UPLOAD_ERR_EXTENSION => 'Extensión de PHP detuvo la subida'
        ];
        
        $errorCode = $_FILES['archivo']['error'] ?? -1;
        $errorMessage = $errorMessages[$errorCode] ?? 'Error desconocido al subir archivo';
        
        throw new Exception("No se recibió archivo válido: $errorMessage", 400);
    }
    
    $archivo = $_FILES['archivo'];
    
    // Validar extensión
    $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ['xlsx', 'xls'])) {
        throw new Exception('Solo se permiten archivos Excel (.xlsx, .xls)', 400);
    }
    
    // Validar tamaño (máximo 20MB)
    if ($archivo['size'] > 20 * 1024 * 1024) {
        throw new Exception('El archivo excede el tamaño máximo de 20MB', 400);
    }
    
    // Obtener parámetros
    $año = filter_input(INPUT_POST, 'año', FILTER_VALIDATE_INT);
    $mes = filter_input(INPUT_POST, 'mes', FILTER_VALIDATE_INT);
    
    if (!$año || $año < 2020 || $año > 2030) {
        throw new Exception('Año inválido. Debe estar entre 2020 y 2030', 400);
    }
    
    if (!$mes || $mes < 1 || $mes > 12) {
        throw new Exception('Mes inválido. Debe estar entre 1 y 12', 400);
    }
    
    // Mover archivo a ubicación temporal
    $rutaTemporal = sys_get_temp_dir() . '/' . uniqid('planificacion_') . '.' . $extension;
    
    if (!move_uploaded_file($archivo['tmp_name'], $rutaTemporal)) {
        throw new Exception('Error al procesar el archivo subido', 500);
    }
    
    // Obtener ID de usuario (si hay sesión)
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $usuarioId = $_SESSION['user_id'] ?? null;
    
    // ✅ CONVERTIR A INT O NULL (fix para el error de tipo)
    $usuarioIdInt = is_numeric($usuarioId) ? (int)$usuarioId : null;
    
    // Usar la conexión PDO global del config.php
    global $pdo;
    
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new Exception('Conexión a base de datos no disponible', 500);
    }
    
    // Procesar archivo
    $importador = new ImportarPlanificacionHH($pdo);
    $resultado = $importador->procesarArchivo($rutaTemporal, $año, $mes, $usuarioIdInt);
    
    // Limpiar archivo temporal
    if (file_exists($rutaTemporal)) {
        @unlink($rutaTemporal);
    }
    
    // Responder
    if ($resultado['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'Importación completada exitosamente',
            'importacion_id' => $resultado['importacion_id'],
            'stats' => $resultado['stats'],
            'log' => $resultado['log']
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $resultado['error'],
            'stats' => $resultado['stats'],
            'log' => $resultado['log']
        ]);
    }
    
} catch (Exception $e) {
    $rawCode = $e->getCode();
    
    // Solo usar el código si es un entero HTTP válido (100-599)
    // Los códigos SQLSTATE como '42S02' son strings y deben ignorarse
    $httpCode = (is_int($rawCode) && $rawCode >= 100 && $rawCode <= 599) ? $rawCode : 500;
    
    http_response_code($httpCode);
    
    // Log detallado para debugging
    error_log("❌ Import Error [{$rawCode}]: " . $e->getMessage());
    error_log("📍 File: " . $e->getFile() . ":" . $e->getLine());
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => [
            'code' => $rawCode,
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ]
    ]);
} catch (Error $e) {
    // Limpiar cualquier output acumulado
    if (ob_get_level() > 0) ob_end_clean();
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error interno: ' . $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
}