<?php
// public/api/kpis.php
header('Content-Type: application/json; charset=utf-8');
while (ob_get_level()) ob_end_clean();

try {
    define('APP_ENTRY_POINT', true);
    $docRoot     = $_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__);
    $projectRoot = dirname($docRoot);
    $configPath = file_exists("$projectRoot/config.php") ? "$projectRoot/config.php" : null;
    if (!$configPath) throw new Exception("Config no encontrado");
    require_once $configPath;

    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['user_id'])) { http_response_code(401); exit(json_encode(['success'=>false,'error'=>'No autorizado'])); }

    $action = $_GET['action'] ?? 'global';

    switch ($action) {
        case 'global':
            // Obtener filtros del query string
            $year = $_GET['year'] ?? date('Y');
            $month = $_GET['month'] ?? null;
            $week = $_GET['week'] ?? null;
            
            // Construir WHERE dinámico
            $where = ["YEAR(ultima_fecha_programada) = ?"];
            $params = [$year];
            
            if ($month && $month !== '') {
                $where[] = "mes_carga = ?";
                $params[] = $month;
            }
            if ($week && $week !== '') {
                $where[] = "semana_carga = ?";
                $params[] = $week;
            }
            
            $whereClause = implode(" AND ", $where);
            
            // Consulta con filtros
            $stats = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_ots,
                    COALESCE(SUM(total_hh_planificadas), 0) as hh_plan,
                    COALESCE(SUM(total_hh_reales_acumuladas), 0) as hh_real
                FROM ot_resumen_actual
                WHERE $whereClause
            ");
            $stats->execute($params);
            $stats = $stats->fetch(PDO::FETCH_ASSOC);

            // SLA con mismos filtros
            $slaWhere = str_replace("YEAR(ultima_fecha_programada)", "YEAR(ultima_fecha_programada)", $whereClause);
            $sla = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_cerradas,
                    SUM(CASE WHEN dias_retraso <= 3 THEN 1 ELSE 0 END) as dentro_sla
                FROM ot_resumen_actual 
                WHERE ultimo_estado IN ('completada', 'cerrada') AND $slaWhere
            ");
            $sla->execute($params);
            $sla = $sla->fetch(PDO::FETCH_ASSOC);
            
            $total_cerradas = (int)($sla['total_cerradas'] ?? 0);
            $dentro_sla = (int)($sla['dentro_sla'] ?? 0);
            $slaPercent = $total_cerradas > 0 ? round(($dentro_sla / $total_cerradas) * 100, 1) : 0;

            // OTs en Riesgo con filtros
            $riesgoStmt = $pdo->prepare("
                SELECT COUNT(*) FROM ot_resumen_actual 
                WHERE ultimo_estado IN ('pendiente', 'asignada', 'en_ejecucion') 
                AND dias_retraso > 7 AND $whereClause
            ");
            $riesgoStmt->execute($params);
            $riesgo = $riesgoStmt->fetchColumn();

            echo json_encode([
                'success' => true,
                'data' => [
                    'total_ots'   => (int)($stats['total_ots'] ?? 0),
                    'hh_plan'     => floatval($stats['hh_plan'] ?? 0),
                    'hh_real'     => floatval($stats['hh_real'] ?? 0),
                    'sla_percent' => floatval($slaPercent),
                    'ots_closed'  => $total_cerradas,
                    'ots_riesgo'  => (int)($riesgo ?? 0)
                ]
            ]);
            break;

        case 'chart_data':
            $group = $_GET['group_by'] ?? 'especialidad';
            if ($group === 'estado') {
                $stmt = $pdo->query("SELECT ultimo_estado as label, COUNT(*) as count FROM ot_resumen_actual WHERE ultimo_estado IS NOT NULL GROUP BY ultimo_estado");
                $data = array_map(fn($r)=>['label'=>$r['label'], 'value'=>(int)$r['count']], $stmt->fetchAll(PDO::FETCH_ASSOC));
            } else {
                // Trae id_especialidad y suma HH
                $stmt = $pdo->query("SELECT COALESCE(id_especialidad,0) as code, COUNT(*) as count, ROUND(SUM(total_hh_planificadas),1) as hh FROM ot_resumen_actual GROUP BY id_especialidad ORDER BY hh DESC LIMIT 10");
                
                // Mapeo ampliado según tus datos reales
                $espMap = [
                    50=>'M-CLIMATIZACIÓN', 51=>'M-ELECTRICIDAD', 52=>'M-GASFITERÍA', 
                    53=>'M-ELECTRÓNICA', 55=>'M-ELECTROMECÁNICA', 57=>'M-POLIVALENTE'
                ];
                
                $data = array_map(function($r) use ($espMap) {
                    $code = (int)$r['code'];
                    return [
                        'label' => $espMap[$code] ?? ($code > 0 ? "Esp. {$code}" : "Sin Asignar"),
                        'value' => floatval($r['hh']), // <-- Este es el campo que usa renderSimpleBarChart
                        'hh_plan' => floatval($r['hh']) // Mantener para compatibilidad
                    ];
                }, $stmt->fetchAll(PDO::FETCH_ASSOC));
            }
            echo json_encode([
                'success' => true,
                'data' => $data ?: [] // Nunca null, siempre array vacío si no hay datos
            ]);
            break;

        case 'reprogramadas':
            $limit = min((int)($_GET['limit']??10), 50);
            $stmt = $pdo->prepare("SELECT codigo_ot, nombre_equipo, veces_reprogramadas, ultima_fecha_programada, dias_retraso, ultimo_estado FROM ot_resumen_actual WHERE veces_reprogramadas > 0 ORDER BY veces_reprogramadas DESC, ultima_carga DESC LIMIT ?");
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            $stmt->execute();
            echo json_encode(['success'=>true, 'data'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        default: throw new Exception("Acción no válida");
    }
} catch (Throwable $e) {
    http_response_code(500); echo json_encode(['success'=>false, 'error'=>$e->getMessage()]);
}