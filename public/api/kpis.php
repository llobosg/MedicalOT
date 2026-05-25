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
            // 1. HHs Plan (desde planilla) + HHs Reales (calculadas por tiempo en sistema)
            $stats = $pdo->query("
                SELECT 
                    COUNT(*) as total_ots,
                    COALESCE(SUM(total_hh_planificadas), 0) as hh_plan,
                    COALESCE(SUM(
                        CASE 
                            WHEN ultimo_estado IN ('completada', 'cerrada') 
                            THEN TIMESTAMPDIFF(HOUR, primera_carga, ultima_carga) 
                            ELSE 0 
                        END
                    ), 0) as hh_real_calc
                FROM ot_resumen_actual
            ")->fetch(PDO::FETCH_ASSOC);

            // 2. SLA (mismo filtro de estados finales)
            $sla = $pdo->query("
                SELECT 
                    COUNT(*) as total_cerradas,
                    SUM(CASE WHEN dias_retraso <= 3 THEN 1 ELSE 0 END) as dentro_sla
                FROM ot_resumen_actual 
                WHERE ultimo_estado IN ('completada', 'cerrada')
            ")->fetch(PDO::FETCH_ASSOC);
            
            $total_cerradas = (int)($sla['total_cerradas'] ?? 0);
            $dentro_sla = (int)($sla['dentro_sla'] ?? 0);
            $slaPercent = $total_cerradas > 0 ? round(($dentro_sla / $total_cerradas) * 100, 1) : 0;

            // 3. OTs en Riesgo
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
                    'hh_real'     => floatval($stats['hh_real_calc'] ?? 0), // ✅ Calculado automáticamente
                    'sla_percent' => floatval($slaPercent),
                    'ots_closed'  => $total_cerradas,
                    'ots_riesgo'  => (int)($riesgo ?? 0)
                ]
            ]);
            break;

        case 'chart_data':
            $group = $_GET['group_by'] ?? 'especialidad';
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
                $params[] = (int)$week;
            }
            
            $whereClause = implode(" AND ", $where);
            
            if ($group === 'estado') {
                $stmt = $pdo->prepare("SELECT ultimo_estado as label, COUNT(*) as count FROM ot_resumen_actual WHERE ultimo_estado IS NOT NULL AND $whereClause GROUP BY ultimo_estado");
                $stmt->execute($params);
                $data = array_map(fn($r)=>['label'=>$r['label'], 'value'=>(int)$r['count']], $stmt->fetchAll(PDO::FETCH_ASSOC));
            } else {
                $stmt = $pdo->prepare("
                    SELECT 
                        COALESCE(id_especialidad, 0) as code,
                        COUNT(*) as count,
                        ROUND(SUM(total_hh_planificadas), 1) as hh 
                    FROM ot_resumen_actual 
                    WHERE id_especialidad IS NOT NULL AND $whereClause
                    GROUP BY id_especialidad 
                    ORDER BY hh DESC 
                    LIMIT 10
                ");
                $stmt->execute($params);
                
                $espMap = [
                    50=>'M-CLIMATIZACIÓN', 51=>'M-ELECTRICIDAD', 52=>'M-GASFITERÍA',
                    53=>'M-ELECTRÓNICA', 54=>'M-CARPINTERÍA', 55=>'M-ELECTROMECÁNICA',
                    57=>'M-POLIVALENTE'
                ];
                
                $data = array_map(fn($r)=>[
                    'label'=>$espMap[$r['code']] ?? "Esp. {$r['code']}", 
                    'value'=>floatval($r['hh']),
                    'count'=>(int)$r['count']
                ], $stmt->fetchAll(PDO::FETCH_ASSOC));
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