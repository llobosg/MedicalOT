<?php
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

    $sql = "SELECT o.codigo_ot, o.fecha_programada, o.turno, o.dia_semana,
                   p.codigo as cod_protocolo, p.nombre as nombre_protocolo, p.familia, p.periodicidad,
                   e.nombre as nombre_equipo, e.serie,
                   a.nombre as nombre_area, a.ubicacion as area_ubicacion,
                   esp.codigo as cod_especialidad, esp.nombre as nombre_especialidad,
                   prov.rut as cod_proveedor, prov.razon_social as nombre_proveedor,
                   o.hh_programadas, o.hh_reales, o.estado
            FROM ordenes_trabajo o
            LEFT JOIN protocolos p ON o.id_protocolo = p.id
            LEFT JOIN equipos e ON o.id_equipo = e.id
            LEFT JOIN areas a ON o.id_area = a.id
            LEFT JOIN especialidades esp ON o.id_especialidad = esp.id
            LEFT JOIN proveedores prov ON o.rut_proveedor = prov.rut
            WHERE 1=1";

    $params = [];
    if ($search) {
        $s = "%$search%";
        $sql .= " AND (o.codigo_ot LIKE ? OR p.nombre LIKE ? OR e.nombre LIKE ? OR a.nombre LIKE ? OR esp.nombre LIKE ? OR prov.razon_social LIKE ?)";
        $params = array_fill(0, 6, $s);
    }
    if ($esp)    { $sql .= " AND esp.codigo = ?"; $params[] = $esp; }
    if ($estado) { $sql .= " AND o.estado = ?";   $params[] = $estado; }
    if ($mes) {
        $meses = ['enero'=>1,'febrero'=>2,'marzo'=>3,'abril'=>4,'mayo'=>5,'junio'=>6,
                  'julio'=>7,'agosto'=>8,'septiembre'=>9,'octubre'=>10,'noviembre'=>11,'diciembre'=>12];
        if (isset($meses[strtolower($mes)])) {
            $sql .= " AND MONTH(o.fecha_programada) = ?";
            $params[] = $meses[strtolower($mes)];
        }
    }

    // Conteo total
    $countSql = "SELECT COUNT(*) FROM ($sql) AS temp";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    // Paginación y ordenamiento
    $sql .= " ORDER BY o.fecha_programada ASC, o.codigo_ot ASC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'data' => $data,
        'total' => $total,
        'page' => $page,
        'totalPages' => (int)ceil($total / $limit)
    ]);
    exit;

} catch (\Throwable $e) {
    error_log("❌ API OTs Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code($e->getCode() ?: 500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}