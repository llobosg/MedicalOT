<?php
/**
 * public/health.php - Health Check para Railway
 * Responde siempre con 200 OK si PHP está funcionando
 */

// No cargar config.php ni BD para evitar dependencias
http_response_code(200);
header('Content-Type: application/json');

echo json_encode([
    'status' => 'healthy',
    'service' => 'medicalot',
    'timestamp' => date('c'),
    'php_version' => PHP_VERSION,
    'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown'
], JSON_PRETTY_PRINT);

exit;