<?php
// public/health.php - NO incluir config.php ni layout.php
http_response_code(200);
header('Content-Type: application/json');
echo json_encode(['status' => 'healthy', 'time' => date('c')]);
exit;