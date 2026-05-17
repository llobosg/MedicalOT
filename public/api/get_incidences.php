<?php
// public/api/get_incidences.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../includes/config.php';

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