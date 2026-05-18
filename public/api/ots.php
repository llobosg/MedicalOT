<?php
// public/api/ots.php
// Blindaje total: prevenir salida HTML de errores
ini_set('display_errors', 0);
ini_set('log_errors', 1);
header('Content-Type: application/json; charset=utf-8');
while (ob_get_level()) ob_end_clean();

try {
    // 🔐 Configuración de Seguridad y Rutas
    define('APP_ENTRY_POINT', true);
    
    // Resolución inteligente de rutas para Railway/Local
    $docRoot     = $_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__);
    $projectRoot = dirname($docRoot);
    
    $configPath = file_exists("$projectRoot/config.php") 
                ? "$projectRoot/config.php" 
                : (file_exists("$docRoot/config.php") ? "$docRoot/config.php" : null);
                
    if (!$configPath) {
        throw new Exception("config.php no encontrado.");
    }
    
    require_once $configPath;

    if (session_status() === PHP_SESSION_NONE) session_start();
    
    // Validar sesión
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'No autorizado'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Parámetros de entrada
    $search = trim($_GET['search'] ?? '');
    $esp    = $_GET['esp'] ?? '';
    $estado = $_GET['estado'] ?? '';
    $mes    = $_GET['mes'] ?? '';
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = 50;
    $offset = ($page - 1) * $limit;

    $conditions = "WHERE 1=1";
    $params = [];

    // Filtros
    if ($search) {
        $s = "%$search%";
        // Buscamos en OT, Protocolo, Equipo, Área, Especialidad y Vertical
        $conditions .= " AND (
            o.codigo_ot LIKE ? 
            OR p.nombre LIKE ? 
            OR e.nombre LIKE ? 
            OR a.nombre LIKE ? 
            OR esp.nombre LIKE ? 
            OR v.nombre_vertical LIKE ?
        )";
        $params = array_fill(0, 6, $s);
    }
    
    if ($esp)    { $conditions .= " AND esp.codigo = ?"; $params[] = $esp; }
    if ($estado) { $conditions .= " AND o.estado = ?";   $params[] = $estado; }
    
    if ($mes) {
        $meses = [
            'enero'=>1,'febrero'=>2,'marzo'=>3,'abril'=>4,'mayo'=>5,'junio'=>6,
            'julio'=>7,'agosto'=>8,'septiembre'=>9,'octubre'=>10,'noviembre'=>11,'diciembre'=>12
        ];
        if (isset($meses[strtolower($mes)])) {
            $conditions .= " AND MONTH(o.fecha_programada) = ?";
            $params[] = $meses[strtolower($mes)];
        }
    }

    // ✅ CONSULTA DE CONTADOR
    // Nota: Eliminamos el JOIN a proveedores. Agregamos LEFT JOIN a verticales.
    // Si ordenes_trabajo no tiene id_vertical aún, el join resultará en NULL pero no fallará.
    $countSql = "SELECT COUNT(*) FROM ordenes_trabajo o
                 LEFT JOIN protocolos p ON o.id_protocolo = p.id
                 LEFT JOIN equipos e ON o.id_equipo = e.id
                 LEFT JOIN areas a ON o.id_area = a.id
                 LEFT JOIN especialidades esp ON o.id_especialidad = esp.id
                 LEFT JOIN verticales v ON o.id_vertical = v.id_vertical
                 $conditions";
                 
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    // ✅ CONSULTA DE DATOS PRINCIPAL
    // Campos actualizados: quitamos prov.*, agregamos v.*
    $fields = "
        o.codigo_ot, 
        o.fecha_programada, 
        o.turno, 
        o.dia_semana,
        p.nombre as nombre_protocolo, 
        p.familia, 
        p.periodicidad,
        e.nombre as nombre_equipo, 
        e.serie,
        a.nombre as nombre_area, 
        a.nombre as area_ubicacion,
        esp.nombre as nombre_especialidad, 
        esp.codigo as cod_especialidad,
        v.nombre_vertical as nombre_vertical,
        v.nombre_responsable as responsable_vertical,
        o.hh_programadas, 
        o.hh_reales, 
        o.estado, 
        o.observaciones
    ";

    $sql = "SELECT $fields FROM ordenes_trabajo o
            LEFT JOIN protocolos p ON o.id_protocolo = p.id
            LEFT JOIN equipos e ON o.id_equipo = e.id
            LEFT JOIN areas a ON o.id_area = a.id
            LEFT JOIN especialidades esp ON o.id_especialidad = esp.id
            LEFT JOIN verticales v ON o.id_vertical = v.id_vertical
            $conditions ORDER BY o.fecha_programada ASC, o.codigo_ot ASC LIMIT ? OFFSET ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge($params, [$limit, $offset]));
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'data' => $data,
        'total' => $total,
        'page' => $page,
        'totalPages' => max(1, (int)ceil($total / $limit))
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (\Throwable $e) {
    // Log detallado para debugging en Railway
    error_log("❌ API OTs Fatal: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
    
    // Asegurar código HTTP entero
    $code = $e->getCode();
    if (!is_int($code) || $code == 0) {
        $code = 500;
    }
    
    http_response_code($code);
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage(),
        'debug' => $e->getMessage() // Quitar debug en producción final
    ], JSON_UNESCAPED_UNICODE);
    exit;
}