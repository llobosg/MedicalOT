<?php
// public/api/get_incidences.php

// 1. Limpiar cualquier output previo
while (ob_get_level()) ob_end_clean();

// 2. Forzar cabeceras
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *'); // Solo para debug, quitar en prod si no es necesario

try {
    // 3. Iniciar sesión SIEMPRE primero
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // 4. Validación de sesión estricta pero con JSON claro
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401); // Cambiado a 401 Unauthorized en lugar de 403
        echo json_encode(['success' => false, 'error' => 'Sesión expirada o no iniciada']);
        exit;
    }

    // 5. Cargar config (Usando la ruta correcta que ya validamos)
    $projectRoot = dirname(__DIR__, 2); // Sube 2 niveles desde public/api -> raiz proyecto
    $configPath = $projectRoot . '/config.php';
    
    if (!file_exists($configPath)) {
        throw new Exception("Config no encontrado en: {$configPath}");
    }

    // antes de require
    define('APP_ENTRY_POINT', true);
    require_once $configPath;

    // Obtener parámetro OT
    $otCode = $_GET['ot'] ?? '';

    if (empty($otCode)) {
        echo json_encode(['success' => true, 'incidencias' => []]);
        exit;
    }

    try {
        // Consultar incidencias de la BD
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
        // Si hay error de BD, devolvemos JSON vacío para no romper la UI
        error_log("Error DB get_incidences: " . $e->getMessage());
        echo json_encode(['success' => true, 'incidencias' => [], 'db_error' => $e->getMessage()]);
    }

} catch (\Throwable $e) {
    // Error crítico (ej. falta config.php)
    error_log("❌ API Get Incidences Fatal: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno del servidor.']);
    exit;
}