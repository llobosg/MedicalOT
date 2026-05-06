<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
if (!isset($_SESSION['sic_progress'])) {
    echo json_encode(['status' => 'idle', 'current' => 0, 'total' => 0, 'percent' => 0]);
    exit;
}
$p = $_SESSION['sic_progress'];
$percent = $p['total'] > 0 ? round(($p['current'] / $p['total']) * 100) : 0;
echo json_encode([
    'status' => $p['status'],
    'current' => $p['current'],
    'total' => $p['total'],
    'percent' => min($percent, 100)
]);