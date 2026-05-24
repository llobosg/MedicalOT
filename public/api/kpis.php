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
            $stats = $pdo->query("SELECT COUNT(*) as total, COALESCE(SUM(total_hh_planificadas),0) as hh_plan, COALESCE(SUM(total_hh_reales_acumuladas),0) as hh_real FROM ot_resumen_actual")->fetch(PDO::FETCH_ASSOC);
            $sla = $pdo->query("SELECT COUNT(*) as cerradas, SUM(CASE WHEN dias_retraso <= 3 THEN 1 ELSE 0 END) as ok FROM ot_resumen_actual WHERE ultimo_estado='completada'")->fetch(PDO::FETCH_ASSOC);
            $riesgo = $pdo->query("SELECT COUNT(*) FROM ot_resumen_actual WHERE ultimo_estado IN ('pendiente','asignada','en_ejecucion') AND dias_retraso > 7")->fetchColumn();
            
            $slaPct = ($stats['total'] > 0 && $sla['cerradas'] > 0) ? round(($sla['ok'] / $sla['cerradas']) * 100, 1) : 0;
            echo json_encode(['success'=>true, 'data'=>['total_ots'=>(int)$stats['total'], 'hh_plan'=>floatval($stats['hh_plan']), 'hh_real'=>floatval($stats['hh_real']), 'sla_percent'=>floatval($slaPct), 'ots_riesgo'=>(int)$riesgo]]);
            break;

        case 'chart_data':
            $group = $_GET['group_by'] ?? 'especialidad';
            if ($group === 'estado') {
                $stmt = $pdo->query("SELECT ultimo_estado as label, COUNT(*) as count FROM ot_resumen_actual WHERE ultimo_estado IS NOT NULL GROUP BY ultimo_estado");
                $data = array_map(fn($r)=>['label'=>$r['label'], 'value'=>(int)$r['count']], $stmt->fetchAll(PDO::FETCH_ASSOC));
            } else {
                $stmt = $pdo->query("SELECT COALESCE(id_especialidad,0) as code, COUNT(*) as count, ROUND(SUM(total_hh_planificadas),1) as hh FROM ot_resumen_actual GROUP BY id_especialidad ORDER BY hh DESC LIMIT 10");
                $espMap = [50=>'Climatización', 51=>'Electricidad', 53=>'Electrónica', 55=>'Electromecánica', 57=>'Polivalente'];
                $data = array_map(fn($r)=>['label'=>$espMap[$r['code']] ?? "Espec #{$r['code']}", 'value'=>floatval($r['hh'])], $stmt->fetchAll(PDO::FETCH_ASSOC));
            }
            echo json_encode(['success'=>true, 'data'=>$data]);
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