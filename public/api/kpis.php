<?php
// public/api/kpis.php
header('Content-Type: application/json; charset=utf-8');
while (ob_get_level()) ob_end_clean();

try {
    define('APP_ENTRY_POINT', true);
    
    // 🔧 Ruta dinámica corregida (config.php está en la raíz del proyecto)
    $docRoot     = $_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__);
    $projectRoot = dirname($docRoot);
    $configPath = file_exists("$projectRoot/config.php") ? "$projectRoot/config.php" : null;
    
    if (!$configPath) {
        throw new Exception("Archivo de configuración no encontrado");
    }
    require_once $configPath; // $pdo ya está disponible aquí

    // 🔐 Validación de sesión
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'No autorizado']);
        exit;
    }

    $action = $_GET['action'] ?? 'global';

    switch ($action) {
        case 'global':
            // 1. Total OTs y HHs
            $stats = $pdo->query("
                SELECT 
                    COUNT(*) as total_ots,
                    COALESCE(SUM(total_hh_planificadas), 0) as hh_plan,
                    COALESCE(SUM(total_hh_reales_acumuladas), 0) as hh_real
                FROM ot_resumen_actual
            ")->fetch(PDO::FETCH_ASSOC);

            // 2. SLA Cumplido (OTs completadas sin retraso)
            $sla = $pdo->query("
                SELECT 
                    COUNT(*) as total_cerradas,
                    SUM(CASE WHEN dias_retraso <= 0 THEN 1 ELSE 0 END) as a_tiempo
                FROM ot_resumen_actual 
                WHERE ultimo_estado = 'completada'
            ")->fetch(PDO::FETCH_ASSOC);
            
            $slaPercent = 0;
            if ($sla['total_cerradas'] > 0) {
                $slaPercent = round(($sla['a_tiempo'] / $sla['total_cerradas']) * 100, 1);
            }

            // 3. OTs en Riesgo (Retrasadas > 7 días y no cerradas)
            $riesgo = $pdo->query("
                SELECT COUNT(*) FROM ot_resumen_actual 
                WHERE ultimo_estado IN ('pendiente', 'en_ejecucion', 'reprogramada') 
                AND dias_retraso > 7
            ")->fetchColumn();

            echo json_encode([
                'success' => true,
                'data' => [
                    'total_ots'   => (int)$stats['total_ots'],
                    'hh_plan'     => floatval($stats['hh_plan']),
                    'hh_real'     => floatval($stats['hh_real']),
                    'sla_percent' => floatval($slaPercent),
                    'ots_riesgo'  => (int)$riesgo
                ]
            ]);
            break;

        case 'chart_data':
            $groupBy = $_GET['group_by'] ?? 'especialidad';
            
            // Mapeo seguro de columnas para evitar inyección
            $fieldMap = [
                'especialidad' => 'id_especialidad',
                'tipo'         => 'tipo_mantenimiento',
                'estado'       => 'ultimo_estado'
            ];
            $field = $fieldMap[$groupBy] ?? 'id_especialidad';

            $stmt = $pdo->query("
                SELECT 
                    $field as label,
                    SUM(total_hh_planificadas) as hh_plan,
                    SUM(total_hh_reales_acumuladas) as hh_real,
                    COUNT(*) as count_ots
                FROM ot_resumen_actual
                WHERE $field IS NOT NULL
                GROUP BY $field
                ORDER BY hh_plan DESC
                LIMIT 15
            ");

            echo json_encode([
                'success' => true,
                'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ]);
            break;

        case 'recent_ots':
            $limit = (int)($_GET['limit'] ?? 10);
            $stmt = $pdo->prepare("
                SELECT 
                    codigo_ot,
                    nombre_equipo,
                    ultimo_estado as estado,
                    ROUND(total_hh_planificadas, 1) as hh_plan,
                    ROUND(total_hh_reales_acumuladas, 1) as hh_real,
                    dias_retraso
                FROM ot_resumen_actual
                ORDER BY ultima_carga DESC
                LIMIT ?
            ");
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            $stmt->execute();

            echo json_encode([
                'success' => true,
                'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ]);
            break;

        default:
            throw new Exception("Acción no válida");
    }

} catch (\Throwable $e) {
    error_log("❌ API KPIs Fatal: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}