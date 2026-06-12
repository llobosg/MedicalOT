<?php
/**
 * API Endpoint - Importar Ejecución Real (Cierre de OTs)
 * Versión Corregida: Sin errores de sintaxis 'use'
 */
header('Content-Type: application/json; charset=utf-8');
define('APP_ENTRY_POINT', true);

// Configurar límites para procesos pesados
ini_set('display_errors', 0); // No mostrar errores HTML en producción
error_reporting(E_ALL);
set_time_limit(300); // 5 minutos
ini_set('memory_limit', '512M');

// 1. Cargar dependencias NECESARIAMENTE antes del 'use'
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/Services/ImportarEjecucionOT.php';

// 2. Declarar el uso del Namespace (DEBE ser global, fuera de funciones/bloques)
use App\Services\ImportarEjecucionOT;

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Método no permitido", 405);
    }

    if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
        $errCode = $_FILES['archivo']['error'] ?? -1;
        throw new Exception("Error al subir archivo (Código: $errCode)", 400);
    }

    $file = $_FILES['archivo'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // ✅ Aceptar CSV y Excel
    if (!in_array($ext, ['xlsx', 'xls', 'csv'])) {
        throw new Exception("Formato inválido. Se permiten .xlsx, .xls o .csv", 400);
    }

    $tmpPath = sys_get_temp_dir() . '/' . uniqid('ejecucion_') . '.' . $ext;
    
    if (!move_uploaded_file($file['tmp_name'], $tmpPath)) {
        throw new Exception("Error interno al mover archivo temporal", 500);
    }

    // 3. Usar la variable global $pdo definida en config.php
    global $pdo;
    if (!$pdo) {
        throw new Exception("Conexión a BD fallida");
    }

    // 4. Instanciar y procesar
    $importer = new ImportarEjecucionOT($pdo);
    $result = $importer->procesarArchivo($tmpPath);
    
    // Limpiar temporal
    @unlink($tmpPath);
    
    // 5. Devolver respuesta JSON limpia
    echo json_encode($result);
    exit;

} catch (Exception $e) {
    // Si hay error, limpiar buffer y devolver JSON de error
    http_response_code(500);
    ob_clean(); 
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage(),
        'debug' => [
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ]
    ]);
    exit;
}