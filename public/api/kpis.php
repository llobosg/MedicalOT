<?php
// public/api/kpis.php
header('Content-Type: application/json; charset=utf-8');
while (ob_get_level()) ob_end_clean();

try {
    define('APP_ENTRY_POINT', true);
    
    $docRoot     = $_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__);
    $projectRoot = dirname($docRoot);
    $configPath = file_exists("$projectRoot/config.php") ? "$projectRoot/config.php" : null;
    
    if (!$configPath) {
        throw new Exception("Archivo de configuración no encontrado");
    }
    require_once $configPath;

    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'No autorizado']);
        exit;
    }

    $action = $_GET['action'] ?? 'global';

    switch ($action) {
        case 'global':
            // === KPIs GLOBALES - Versión tolerante a nulos ===
            
            // 1. Total OTs y HHs (con COALESCE para evitar null)
            $stats = $pdo->query("
                SELECT 
                    COUNT(*) as total_ots,
                    COALESCE(SUM(total_hh_planificadas), 0) as hh_plan,
                    COALESCE(SUM(total_hh_reales_acumuladas), 0) as hh_real
                FROM ot_resumen_actual
            ")->fetch(PDO::FETCH_ASSOC);

            // 2. SLA: OTs cerradas SIN retraso (o con retraso <= 3 días de tolerancia)
            $sla = $pdo->query("
                SELECT 
                    COUNT(*) as total_cerradas,
                    SUM(CASE WHEN dias_retraso <= 3 THEN 1 ELSE 0 END) as dentro_sla
                FROM ot_resumen_actual 
                WHERE ultimo_estado = 'completada'
            ")->fetch(PDO::FETCH_ASSOC);
            
            $total_cerradas = (int)($sla['total_cerradas'] ?? 0);
            $dentro_sla = (int)($sla['dentro_sla'] ?? 0);
            $slaPercent = $total_cerradas > 0 ? round(($dentro_sla / $total_cerradas) * 100, 1) : 0;

            // 3. OTs Cerradas (valor seguro)
            $otsClosed = $total_cerradas;

            // 4. OTs en Riesgo: Pendientes con fecha vencida + 7 días
            $riesgo = $pdo->query("
                SELECT COUNT(*) FROM ot_resumen_actual 
                WHERE ultimo_estado IN ('pendiente', 'asignada', 'en_ejecucion') 
                AND dias_retraso > 7
            ")->fetchColumn();

            echo json_encode([
                'success' => true,
                'data' => [
                    'total_ots'   => (int)($stats['total_ots'] ?? 0),
                    'hh_plan'     => floatval($stats['hh_plan'] ?? 0),
                    'hh_real'     => floatval($stats['hh_real'] ?? 0),
                    'sla_percent' => floatval($slaPercent),
                    'ots_closed'  => $otsClosed,
                    'ots_riesgo'  => (int)($riesgo ?? 0),
                    'note_hh'     => 'HH Reales se actualizarán al reportar ejecución en terreno'
                ]
            ]);
            break;

        case 'chart_data':
            // === GRÁFICO SIMPLIFICADO: Top 10 Especialidades por HH Plan ===
            $stmt = $pdo->query("
                SELECT 
                    COALESCE(id_especialidad, 0) as code,
                    COUNT(*) as total_ots,
                    ROUND(SUM(total_hh_planificadas), 1) as hh_plan,
                    ROUND(SUM(total_hh_reales_acumuladas), 1) as hh_real
                FROM ot_resumen_actual
                WHERE id_especialidad IS NOT NULL
                GROUP BY id_especialidad
                ORDER BY hh_plan DESC
                LIMIT 10
            ");
            
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Mapeo legible de códigos de especialidad (ajustar según tu catálogo real)
            $especialidadesMap = [
                50 => 'M-CLIMATIZACION',
                51 => 'M-ELECTRICIDAD', 
                53 => 'M-ELECTRONICA',
                55 => 'M-GASFITERIA',
                57 => 'M-POLIVALENTE',
                59 => 'M-INFRAESTRUCTURA'
            ];
            
            $chartData = array_map(function($row) use ($especialidadesMap) {
                $code = (int)$row['code'];
                return [
                    'label' => $especialidadesMap[$code] ?? "Espec. #{$code}",
                    'code' => $code,
                    'hh_plan' => floatval($row['hh_plan']),
                    'hh_real' => floatval($row['hh_real']),
                    'total_ots' => (int)$row['total_ots']
                ];
            }, $data);

            echo json_encode([
                'success' => true,
                'data' => $chartData,
                'note' => 'Mostrando top 10 especialidades. Usa filtros para más detalle.'
            ]);
            break;

        case 'recent_ots':
            $limit = min((int)($_GET['limit'] ?? 10), 50); // Máximo 50 para rendimiento
            $stmt = $pdo->prepare("
                SELECT 
                    codigo_ot,
                    COALESCE(nombre_equipo, 'Sin equipo') as nombre_equipo,
                    COALESCE(ultimo_estado, 'pendiente') as estado,
                    ROUND(COALESCE(total_hh_planificadas, 0), 1) as hh_plan,
                    ROUND(COALESCE(total_hh_reales_acumuladas, 0), 1) as hh_real,
                    COALESCE(dias_retraso, 0) as dias_retraso
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
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Acción no válida']);
    }

} catch (\Throwable $e) {
    error_log("❌ API KPIs: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno: ' . $e->getMessage()]);
}