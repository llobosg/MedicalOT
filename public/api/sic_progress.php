<?php
// api/sic_progress.php
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');

$sessionId = $_GET['session_id'] ?? session_id();

$pdo = getDBConnection();
$stmt = $pdo->prepare("SELECT * FROM import_progress WHERE session_id = ?");
$stmt->execute([$sessionId]);
$progress = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$progress) {
    echo json_encode(['status' => 'idle', 'message' => 'No hay importación activa']);
    exit;
}

// Calcular porcentaje estimado
$percent = $progress['total_rows'] > 0 
    ? min(99, floor(($progress['processed_rows'] / $progress['total_rows']) * 100)) 
    : ($progress['status'] === 'completed' ? 100 : 0);

echo json_encode([
    'status' => $progress['status'],
    'current' => $progress['processed_rows'],
    'total' => $progress['total_rows'],
    'percent' => $progress['status'] === 'completed' ? 100 : $percent,
    'inserted' => $progress['inserted_count'],
    'skipped' => $progress['skipped_count'],
    'errors' => $progress['error_count'],
    'last_error' => $progress['last_error']
]);
?>