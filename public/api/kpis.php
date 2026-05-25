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
            $where = [];
            $params = [];
            
            // Solo filtrar por año si se especificó explícitamente
            if ($year && $year !== '') {
                $where[] = "YEAR(ultima_fecha_programada) = ?";
                $params[] = (int)$year;
            }
            
            // Filtrar por mes solo si se especificó
            if ($month && $month !== '' && $month !== '0') {
                $where[] = "mes_carga = ?";
                $params[] = $month;
            }
            
            // Filtrar por semana solo si se especificó
            if ($week && $week !== '' && $week !== '0') {
                $where[] = "semana_carga = ?";
                $params[] = (int)$week;
            }
            
            $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
            
            // Consulta con filtros
            $sqlStats = "SELECT 
                COUNT(*) as total_ots,
                COALESCE(SUM(total_hh_planificadas), 0) as hh_plan,
                COALESCE(SUM(total_hh_reales_acumuladas), 0) as hh_real
            FROM ot_resumen_actual $whereClause";
            
            $stats = $pdo->prepare($sqlStats);
            $stats->execute($params);
            $stats = $stats->fetch(PDO::FETCH_ASSOC);

            // SLA con mismos filtros
            $sqlSla = "SELECT 
                COUNT(*) as total_cerradas,
                SUM(CASE WHEN dias_retraso <= 3 THEN 1 ELSE 0 END) as dentro_sla
            FROM ot_resumen_actual 
            $whereClause AND ultimo_estado IN ('completada', 'cerrada')";
            
            $sla = $pdo->prepare($sqlSla);
            $sla->execute($params);
            $sla = $sla->fetch(PDO::FETCH_ASSOC);
            
            $total_cerradas = (int)($sla['total_cerradas'] ?? 0);
            $dentro_sla = (int)($sla['dentro_sla'] ?? 0);
            $slaPercent = $total_cerradas > 0 ? round(($dentro_sla / $total_cerradas) * 100, 1) : 0;

            // OTs en Riesgo con filtros
            $sqlRiesgo = "SELECT COUNT(*) FROM ot_resumen_actual 
            $whereClause AND ultimo_estado IN ('pendiente', 'asignada', 'en_ejecucion') 
            AND dias_retraso > 7";
            
            $riesgoStmt = $pdo->prepare($sqlRiesgo);
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
                    'ots_riesgo'  => (int)($riesgo ?? 0),
                    'debug' => [
                        'filters_applied' => [
                            'year' => $year,
                            'month' => $month,
                            'week' => $week
                        ],
                        'where_clause' => $whereClause,
                        'params' => $params
                    ]
                ]
            ]);
            break;

        case 'chart_data':
            $group = $_GET['group_by'] ?? 'especialidad';
            $year  = !empty($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
            $month = !empty($_GET['month']) ? $_GET['month'] : null;
            $week  = !empty($_GET['week']) ? (int)$_GET['week'] : null;
            
            // WHERE dinámico (solo agrega condiciones si hay valor)
            $where = ["YEAR(ultima_fecha_programada) = ?"];
            $params = [$year];
            
            if ($month !== null) {
                $where[] = "mes_carga = ?";
                $params[] = $month;
            }
            if ($week !== null) {
                $where[] = "semana_carga = ?";
                $params[] = $week;
            }
            
            $whereClause = "WHERE " . implode(" AND ", $where);
            
            try {
                if ($group === 'estado') {
                    $sql = "SELECT ultimo_estado as label, COUNT(*) as count 
                            FROM ot_resumen_actual 
                            $whereClause AND ultimo_estado IS NOT NULL 
                            GROUP BY ultimo_estado";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $data = array_map(fn($r) => [
                        'label' => $r['label'], 
                        'value' => (int)$r['count']
                    ], $stmt->fetchAll(PDO::FETCH_ASSOC));
                } else {
                    $sql = "SELECT 
                        COALESCE(id_especialidad, 0) as code,
                        COUNT(*) as count,
                        ROUND(SUM(total_hh_planificadas), 1) as hh 
                    FROM ot_resumen_actual 
                    $whereClause AND id_especialidad IS NOT NULL
                    GROUP BY id_especialidad 
                    ORDER BY hh DESC 
                    LIMIT 10";
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    
                    $espMap = [
                        50 => 'M-CLIMATIZACIÓN', 51 => 'M-ELECTRICIDAD', 52 => 'M-GASFITERÍA',
                        53 => 'M-ELECTRÓNICA', 54 => 'M-CARPINTERÍA', 55 => 'M-ELECTROMECÁNICA',
                        57 => 'M-POLIVALENTE'
                    ];
                    
                    $data = array_map(fn($r) => [
                        'label' => $espMap[$r['code']] ?? "Esp. {$r['code']}", 
                        'value' => floatval($r['hh']),
                        'count' => (int)$r['count']
                    ], $stmt->fetchAll(PDO::FETCH_ASSOC));
                }
                
                echo json_encode([
                    'success' => true, 
                    'data' => $data,
                    'count' => count($data)
                ]);
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'error' => $e->getMessage(),
                    'data' => []
                ]);
            }
            break;

        case 'reprogramadas':
            $limit = min((int)($_GET['limit']??10), 50);
            $stmt = $pdo->prepare("SELECT codigo_ot, nombre_equipo, veces_reprogramadas, ultima_fecha_programada, dias_retraso, ultimo_estado FROM ot_resumen_actual WHERE veces_reprogramadas > 0 ORDER BY veces_reprogramadas DESC, ultima_carga DESC LIMIT ?");
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            $stmt->execute();
            echo json_encode(['success'=>true, 'data'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;
        
        case 'risk_ots':
            $year = $_GET['year'] ?? date('Y');
            $month = $_GET['month'] ?? null;
            $especialidad = $_GET['especialidad'] ?? null; // 🆕 Filtro de especialidad
            $limit = min((int)($_GET['limit'] ?? 50), 100);
            
            $where = ["YEAR(ultima_fecha_programada) = ?"];
            $params = [$year];
            
            if ($month && $month !== '') {
                $where[] = "mes_carga = ?";
                $params[] = $month;
            }
            
            // 🆕 Filtro por especialidad (drill-down)
            if ($especialidad && $especialidad !== '') {
                $where[] = "id_especialidad = ?";
                $params[] = (int)$especialidad;
            }
            
            $where[] = "ultimo_estado IN ('pendiente', 'asignada', 'en_ejecucion')";
            $where[] = "dias_retraso > 7";
            
            $whereClause = implode(" AND ", $where);
            
            $stmt = $pdo->prepare("
                SELECT 
                    codigo_ot, nombre_equipo, 
                    COALESCE(id_especialidad, 0) as id_especialidad,
                    ultimo_estado, dias_retraso,
                    ultima_fecha_programada, total_hh_planificadas
                FROM ot_resumen_actual
                WHERE $whereClause
                ORDER BY dias_retraso DESC, total_hh_planificadas DESC
                LIMIT ?
            ");
            $params[] = $limit;
            $stmt->execute($params);
            
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $espMap = [
                50=>'M-CLIMATIZACIÓN', 51=>'M-ELECTRICIDAD', 52=>'M-GASFITERÍA',
                53=>'M-ELECTRÓNICA', 54=>'M-CARPINTERÍA', 55=>'M-ELECTROMECÁNICA',
                57=>'M-POLIVALENTE'
            ];
            foreach ($rows as &$r) {
                $r['especialidad_nombre'] = $espMap[$r['id_especialidad']] ?? "Esp. {$r['id_especialidad']}";
            }
            
            echo json_encode(['success' => true, 'data' => $rows]);
            break;

        case 'risk_by_especialidad':
            $year = $_GET['year'] ?? date('Y');
            $month = $_GET['month'] ?? null;
            
            $where = ["YEAR(ultima_fecha_programada) = ?"];
            $params = [$year];
            
            if ($month && $month !== '') {
                $where[] = "mes_carga = ?";
                $params[] = $month;
            }
            
            $where[] = "ultimo_estado IN ('pendiente', 'asignada', 'en_ejecucion')";
            $where[] = "dias_retraso > 7";
            $where[] = "id_especialidad IS NOT NULL";
            
            $whereClause = implode(" AND ", $where);
            
            $stmt = $pdo->prepare("
                SELECT 
                    id_especialidad as code,
                    COUNT(*) as count_ots,
                    ROUND(SUM(total_hh_planificadas), 1) as hh_total,
                    MAX(dias_retraso) as max_retraso
                FROM ot_resumen_actual
                WHERE $whereClause
                GROUP BY id_especialidad
                ORDER BY count_ots DESC
                LIMIT 10
            ");
            $stmt->execute($params);
            
            $espMap = [
                50=>'M-CLIMATIZACIÓN', 51=>'M-ELECTRICIDAD', 52=>'M-GASFITERÍA',
                53=>'M-ELECTRÓNICA', 54=>'M-CARPINTERÍA', 55=>'M-ELECTROMECÁNICA',
                57=>'M-POLIVALENTE'
            ];
            
            $data = array_map(fn($r) => [
                'code' => (int)$r['code'], // 🆕 Agregar código para click
                'label' => $espMap[$r['code']] ?? "Esp. {$r['code']}",
                'value' => (int)$r['count_ots'],
                'hh' => floatval($r['hh_total']),
                'max_retraso' => (int)$r['max_retraso']
            ], $stmt->fetchAll(PDO::FETCH_ASSOC));
            
            echo json_encode(['success' => true, 'data' => $data]);
            break;

        default: throw new Exception("Acción no válida");
    }
} catch (Throwable $e) {
    http_response_code(500); echo json_encode(['success'=>false, 'error'=>$e->getMessage()]);
}