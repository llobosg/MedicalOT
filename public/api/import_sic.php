<?php
define('APP_ENTRY_POINT', true);
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/session.php';

header('Content-Type: application/json; charset=utf-8');

// Autenticación y rol
requireLogin();
if ($_SESSION['user_role'] !== 'admin_hosp') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acceso denegado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['sicFile'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Método o archivo inválido']);
    exit;
}

$file = $_FILES['sicFile'];
$allowed = ['csv'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($ext, $allowed)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Solo se aceptan archivos .csv']);
    exit;
}

if ($file['size'] > 50 * 1024 * 1024) { // 50MB máx
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Archivo demasiado grande (máx 50MB)']);
    exit;
}

try {
    $tmpPath = $file['tmp_name'];
    $originalName = basename($file['name']);
    
    $service = new \MedicalOT\Services\SICImportService($pdo);
    $result = $service->import($tmpPath, $originalName);
    
    // Limpiar temp
    unlink($tmpPath);
    
    http_response_code(200);
    echo json_encode($result);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}