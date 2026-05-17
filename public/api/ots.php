<?php
// Blindaje total: prevenir salida HTML de errores
ini_set('display_errors', 0);
ini_set('log_errors', 1);
header('Content-Type: application/json; charset=utf-8');
while (ob_get_level()) ob_end_clean();

try {
    define('APP_ENTRY_POINT', true);
    require_once __DIR__ . '/../../vendor/autoload.php';
    require_once __DIR__ . '/../../config.php';

    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['user_id'])) throw new Exception('No autorizado', 401);

    $search = trim($_GET['search'] ?? '');
    $esp    = $_GET['esp'] ?? '';
    $estado = $_GET['estado'] ?? '';
    $mes    = $_GET['mes'] ?? '';
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = 50;
    $offset = ($page - 1) * $limit;

    $conditions = "WHERE 1=1";
    $params = [];

    if ($search) {
        $s = "%$search%";
        $conditions .= " AND (o.codigo_ot LIKE ? OR p.nombre LIKE ? OR e.nombre LIKE ? OR a.nombre LIKE ? OR esp.nombre LIKE ? OR prov.razon_social LIKE ?)";
        $params = array_fill(0, 6, $s);
    }
    if ($esp)    { $conditions .= " AND esp.codigo = ?"; $params[] = $esp; }
    if ($estado) { $conditions .= " AND o.estado = ?";   $params[] = $estado; }
    if ($mes) {
        $meses = ['enero'=>1,'febrero'=>2,'marzo'=>3,'abril'=>4,'mayo'=>5,'junio'=>6,
                  'julio'=>7,'agosto'=>8,'septiembre'=>9,'octubre'=>10,'noviembre'=>11,'diciembre'=>12];
        if (isset($meses[strtolower($mes)])) {
            $conditions .= " AND MONTH(o.fecha_programada) = ?";
            $params[] = $meses[strtolower($mes)];
        }
    }

    // ✅ CONTADOR (Sin columnas problemáticas)
    $countSql = "SELECT COUNT(*) FROM ordenes_trabajo o
                 LEFT JOIN protocolos p ON o.id_protocolo = p.id
                 LEFT JOIN equipos e ON o.id_equipo = e.id
                 LEFT JOIN areas a ON o.id_area = a.id
                 LEFT JOIN especialidades esp ON o.id_especialidad = esp.id
                 LEFT JOIN proveedores prov ON o.rut_proveedor = prov.rut $conditions";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    // ✅ CONSULTA DE DATOS (Corregido: a.nombre en lugar de a.ubicacion)
    $fields = "o.codigo_ot, o.fecha_programada, o.turno, o.dia_semana,
               p.nombre as nombre_protocolo, p.familia, p.periodicidad,
               e.nombre as nombre_equipo, e.serie,
               a.nombre as nombre_area, a.nombre as area_ubicacion,
               esp.nombre as nombre_especialidad, esp.codigo as cod_especialidad,
               prov.rut as cod_proveedor, prov.razon_social as nombre_proveedor,
               o.hh_programadas, o.hh_reales, o.estado, o.observaciones";

    $sql = "SELECT $fields FROM ordenes_trabajo o
            LEFT JOIN protocolos p ON o.id_protocolo = p.id
            LEFT JOIN equipos e ON o.id_equipo = e.id
            LEFT JOIN areas a ON o.id_area = a.id
            LEFT JOIN especialidades esp ON o.id_especialidad = esp.id
            LEFT JOIN proveedores prov ON o.rut_proveedor = prov.rut
            $conditions ORDER BY o.fecha_programada ASC, o.codigo_ot ASC LIMIT ? OFFSET ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge($params, [$limit, $offset]));
    $data = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'data' => $data,
        'total' => $total,
        'page' => $page,
        'totalPages' => max(1, (int)ceil($total / $limit))
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (\Throwable $e) {
    error_log("❌ API OTs Fatal: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code($e->getCode() ?: 500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}