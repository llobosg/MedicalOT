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
        // ═══════════════════════════════════════════════════════
        case 'global':
        // ═══════════════════════════════════════════════════════
            try {
                $year  = !empty($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
                $month = !empty($_GET['month']) ? $_GET['month'] : null;
                $week  = !empty($_GET['week']) ? (int)$_GET['week'] : null;
                
                $where = ["YEAR(ultima_fecha_programada) = ?"];
                $params = [$year];
                
                if ($month !== null && $month !== '') {
                    $where[] = "mes_carga = ?";
                    $params[] = $month;
                }
                if ($week !== null) {
                    $where[] = "semana_carga = ?";
                    $params[] = $week;
                }
                
                $whereClause = implode(" AND ", $where);
                
                // Stats OTs (Demanda)
                $sqlStats = "SELECT COUNT(*) as total_ots,
                            COALESCE(SUM(total_hh_planificadas), 0) as hh_plan,
                            COALESCE(SUM(total_hh_reales_acumuladas), 0) as hh_real
                            FROM ot_resumen_actual WHERE $whereClause";
                $stmtStats = $pdo->prepare($sqlStats);
                $stmtStats->execute($params);
                $stats = $stmtStats->fetch(PDO::FETCH_ASSOC);
                
                // SLA
                $sqlSla = "SELECT COUNT(*) as total_cerradas,
                           SUM(CASE WHEN dias_retraso <= 3 THEN 1 ELSE 0 END) as dentro_sla
                           FROM ot_resumen_actual 
                           WHERE $whereClause AND ultimo_estado IN ('completada', 'cerrada')";
                $stmtSla = $pdo->prepare($sqlSla);
                $stmtSla->execute($params);
                $sla = $stmtSla->fetch(PDO::FETCH_ASSOC);
                
                $total_cerradas = (int)($sla['total_cerradas'] ?? 0);
                $dentro_sla = (int)($sla['dentro_sla'] ?? 0);
                $slaPercent = $total_cerradas > 0 ? round(($dentro_sla / $total_cerradas) * 100, 1) : 0;
                
                // Riesgo
                $sqlRiesgo = "SELECT COUNT(*) FROM ot_resumen_actual 
                              WHERE $whereClause 
                              AND ultimo_estado IN ('pendiente', 'asignada', 'en_ejecucion') 
                              AND dias_retraso > 7";
                $stmtRiesgo = $pdo->prepare($sqlRiesgo);
                $stmtRiesgo->execute($params);
                $riesgo = $stmtRiesgo->fetchColumn();

                // 🆕 DATOS DE PLANIFICACIÓN HH (Oferta)
                $hh_disponibles = 0;
                $tecnicos_plan = 0;
                $hh_cobertura = 0;

                try {
                    // Normalizar mes a número si viene como texto
                    $mes_num = $month;
                    if (is_string($month)) {
                        $meses_map = [
                            'enero'=>1,'febrero'=>2,'marzo'=>3,'abril'=>4,'mayo'=>5,'junio'=>6,
                            'julio'=>7,'agosto'=>8,'septiembre'=>9,'octubre'=>10,'noviembre'=>11,'diciembre'=>12
                        ];
                        if (isset($meses_map[strtolower($month)])) {
                            $mes_num = $meses_map[strtolower($month)];
                        } else {
                            $mes_num = (int)$month;
                        }
                    }
                    
                    if ($mes_num) {
                        $stmtHH = $pdo->prepare("
                            SELECT 
                                COALESCE(SUM(hh_planificadas_total), 0) as hh_total,
                                COUNT(DISTINCT id_tecnico) as tecnicos
                            FROM planificacion_hh_mensual
                            WHERE año = ? AND mes = ?
                        ");
                        $stmtHH->execute([$year, $mes_num]);
                        $rowHH = $stmtHH->fetch(PDO::FETCH_ASSOC);
                        
                        $hh_disponibles = floatval($rowHH['hh_total'] ?? 0);
                        $tecnicos_plan = intval($rowHH['tecnicos'] ?? 0);
                    }
                } catch (Exception $e) {
                    error_log("Error consultando HH Plan: " . $e->getMessage());
                }

                // Calcular Cobertura (Oferta / Demanda)
                $demanda_hh = floatval($stats['hh_plan'] ?? 0);
                if ($demanda_hh > 0 && $hh_disponibles > 0) {
                    $hh_cobertura = round(($hh_disponibles / $demanda_hh) * 100, 1);
                } elseif ($demanda_hh == 0 && $hh_disponibles > 0) {
                    $hh_cobertura = 100; // Si no hay demanda pero sí oferta, cobertura completa teórica
                }

                echo json_encode([
                    'success' => true,
                    'data' => [
                        'total_ots'       => (int)($stats['total_ots'] ?? 0),
                        'hh_plan'         => floatval($stats['hh_plan'] ?? 0), // Demanda SIC
                        'hh_real'         => floatval($stats['hh_real'] ?? 0),
                        'sla_percent'     => floatval($slaPercent),
                        'ots_closed'      => $total_cerradas,
                        'ots_riesgo'      => (int)($riesgo ?? 0),
                        // 🆕 Nuevos campos HH
                        'hh_disponibles'  => $hh_disponibles,
                        'tecnicos_plan'   => $tecnicos_plan,
                        'hh_cobertura'    => $hh_cobertura
                    ]
                ]);
            } catch (Throwable $e) {
                error_log("❌ Error global: " . $e->getMessage());
                echo json_encode(['success' => false, 'error' => $e->getMessage(), 'data' => null]);
            }
            break;

        // ═══════════════════════════════════════════════════════
        case 'chart_data':
        // ═══════════════════════════════════════════════════════
            try {
                $group = $_GET['group_by'] ?? 'especialidad';
                $year  = !empty($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
                $month = !empty($_GET['month']) ? $_GET['month'] : null;
                $week  = !empty($_GET['week']) ? (int)$_GET['week'] : null;
                
                $where = ["YEAR(ultima_fecha_programada) = ?"];
                $params = [$year];
                
                if ($month !== null && $month !== '') {
                    $where[] = "mes_carga = ?";
                    $params[] = $month;
                }
                if ($week !== null) {
                    $where[] = "semana_carga = ?";
                    $params[] = $week;
                }
                
                $whereClause = implode(" AND ", $where);
                
                if ($group === 'estado') {
                    $sql = "SELECT ultimo_estado as label, COUNT(*) as count 
                            FROM ot_resumen_actual 
                            WHERE $whereClause AND ultimo_estado IS NOT NULL 
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
                    WHERE $whereClause AND id_especialidad IS NOT NULL
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
                
                echo json_encode(['success' => true, 'data' => $data, 'count' => count($data)]);
            } catch (Throwable $e) {
                error_log("❌ Error chart_data: " . $e->getMessage());
                echo json_encode(['success' => false, 'error' => $e->getMessage(), 'data' => []]);
            }
            break;

        // ═══════════════════════════════════════════════════════
        case 'reprogramadas':
        // ═══════════════════════════════════════════════════════
            try {
                $year  = !empty($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
                $month = !empty($_GET['month']) ? $_GET['month'] : null;
                $week  = !empty($_GET['week']) ? (int)$_GET['week'] : null;
                $limit = min((int)($_GET['limit'] ?? 10), 50);
                
                $where = ["YEAR(ultima_fecha_programada) = ?"];
                $params = [$year];
                
                if ($month !== null && $month !== '') {
                    $where[] = "mes_carga = ?";
                    $params[] = $month;
                }
                if ($week !== null) {
                    $where[] = "semana_carga = ?";
                    $params[] = $week;
                }
                $where[] = "veces_reprogramadas > 0";
                
                $whereClause = implode(" AND ", $where);
                
                $stmt = $pdo->prepare("
                    SELECT 
                        id_prevision_sic, codigo_ot, nombre_equipo, 
                        veces_reprogramadas, ultima_fecha_programada, 
                        dias_retraso, ultimo_estado,
                        COALESCE(id_especialidad, 0) as id_especialidad,
                        total_hh_planificadas
                    FROM ot_resumen_actual 
                    WHERE $whereClause
                    ORDER BY veces_reprogramadas DESC, ultima_carga DESC
                    LIMIT ?
                ");
                foreach ($params as $i => $v) {
                    $stmt->bindValue($i + 1, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
                }
                $stmt->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
                $stmt->execute();
                
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $espMap = [
                    50 => 'M-CLIMATIZACIÓN', 51 => 'M-ELECTRICIDAD', 52 => 'M-GASFITERÍA',
                    53 => 'M-ELECTRÓNICA', 54 => 'M-CARPINTERÍA', 55 => 'M-ELECTROMECÁNICA',
                    57 => 'M-POLIVALENTE'
                ];
                foreach ($rows as &$r) {
                    $r['especialidad_nombre'] = $espMap[$r['id_especialidad']] ?? "Esp. {$r['id_especialidad']}";
                }
                
                echo json_encode(['success' => true, 'data' => $rows]);
            } catch (Throwable $e) {
                error_log("❌ Error reprogramadas: " . $e->getMessage());
                echo json_encode(['success' => false, 'error' => $e->getMessage(), 'data' => []]);
            }
            break;

        // ═══════════════════════════════════════════════════════
        case 'risk_by_especialidad':
        // ═══════════════════════════════════════════════════════
            try {
                $year  = !empty($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
                $month = !empty($_GET['month']) ? $_GET['month'] : null;
                $week  = !empty($_GET['week']) ? (int)$_GET['week'] : null;
                
                $where = ["YEAR(ultima_fecha_programada) = ?"];
                $params = [$year];
                
                if ($month !== null && $month !== '') {
                    $where[] = "mes_carga = ?";
                    $params[] = $month;
                }
                if ($week !== null) {
                    $where[] = "semana_carga = ?";
                    $params[] = $week;
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
                    50 => 'M-CLIMATIZACIÓN', 51 => 'M-ELECTRICIDAD', 52 => 'M-GASFITERÍA',
                    53 => 'M-ELECTRÓNICA', 54 => 'M-CARPINTERÍA', 55 => 'M-ELECTROMECÁNICA',
                    57 => 'M-POLIVALENTE'
                ];
                
                $data = array_map(fn($r) => [
                    'code' => (int)$r['code'],
                    'label' => $espMap[$r['code']] ?? "Esp. {$r['code']}",
                    'value' => (int)$r['count_ots'],
                    'hh' => floatval($r['hh_total']),
                    'max_retraso' => (int)$r['max_retraso']
                ], $stmt->fetchAll(PDO::FETCH_ASSOC));
                
                echo json_encode(['success' => true, 'data' => $data]);
            } catch (Throwable $e) {
                error_log("❌ Error risk_by_especialidad: " . $e->getMessage());
                echo json_encode(['success' => false, 'error' => $e->getMessage(), 'data' => []]);
            }
            break;

        // ═══════════════════════════════════════════════════════
        case 'risk_ots':
        // ═══════════════════════════════════════════════════════
            try {
                $year  = !empty($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
                $month = !empty($_GET['month']) ? $_GET['month'] : null;
                $week  = !empty($_GET['week']) ? (int)$_GET['week'] : null;
                $especialidad = !empty($_GET['especialidad']) ? (int)$_GET['especialidad'] : null;
                $limit = min((int)($_GET['limit'] ?? 50), 100);
                
                $where = ["YEAR(ultima_fecha_programada) = ?"];
                $params = [$year];
                
                if ($month !== null && $month !== '') {
                    $where[] = "mes_carga = ?";
                    $params[] = $month;
                }
                if ($week !== null) {
                    $where[] = "semana_carga = ?";
                    $params[] = $week;
                }
                if ($especialidad !== null) {
                    $where[] = "id_especialidad = ?";
                    $params[] = $especialidad;
                }
                $where[] = "ultimo_estado IN ('pendiente', 'asignada', 'en_ejecucion')";
                $where[] = "dias_retraso > 7";
                
                $whereClause = implode(" AND ", $where);
                
                $stmt = $pdo->prepare("
                    SELECT 
                        id_prevision_sic, codigo_ot, nombre_equipo, 
                        COALESCE(id_especialidad, 0) as id_especialidad,
                        ultimo_estado, dias_retraso,
                        ultima_fecha_programada, total_hh_planificadas
                    FROM ot_resumen_actual
                    WHERE $whereClause
                    ORDER BY dias_retraso DESC, total_hh_planificadas DESC
                    LIMIT ?
                ");
                foreach ($params as $i => $v) {
                    $stmt->bindValue($i + 1, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
                }
                $stmt->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
                $stmt->execute();
                
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $espMap = [
                    50 => 'M-CLIMATIZACIÓN', 51 => 'M-ELECTRICIDAD', 52 => 'M-GASFITERÍA',
                    53 => 'M-ELECTRÓNICA', 54 => 'M-CARPINTERÍA', 55 => 'M-ELECTROMECÁNICA',
                    57 => 'M-POLIVALENTE'
                ];
                foreach ($rows as &$r) {
                    $r['especialidad_nombre'] = $espMap[$r['id_especialidad']] ?? "Esp. {$r['id_especialidad']}";
                }
                
                echo json_encode(['success' => true, 'data' => $rows]);
            } catch (Throwable $e) {
                error_log("❌ Error risk_ots: " . $e->getMessage());
                echo json_encode(['success' => false, 'error' => $e->getMessage(), 'data' => []]);
            }
            break;

        // ═══════════════════════════════════════════════════════
        case 'get_weeks':
        // ═══════════════════════════════════════════════════════
            try {
                $year  = !empty($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
                $month = !empty($_GET['month']) ? $_GET['month'] : null;
                
                $where = ["YEAR(ultima_fecha_programada) = ?"];
                $params = [$year];
                
                if ($month !== null && $month !== '') {
                    $where[] = "mes_carga = ?";
                    $params[] = $month;
                }
                $where[] = "semana_carga IS NOT NULL";
                
                $whereClause = implode(" AND ", $where);
                
                $stmt = $pdo->prepare("
                    SELECT DISTINCT semana_carga 
                    FROM ot_resumen_actual 
                    WHERE $whereClause
                    ORDER BY semana_carga ASC
                ");
                $stmt->execute($params);
                $weeks = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                echo json_encode(['success' => true, 'data' => $weeks, 'count' => count($weeks)]);
            } catch (Throwable $e) {
                error_log("❌ Error get_weeks: " . $e->getMessage());
                echo json_encode(['success' => false, 'error' => $e->getMessage(), 'data' => []]);
            }
            break;

        // ═══════════════════════════════════════════════════════
        default:
        // ═══════════════════════════════════════════════════════
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Acción no válida: ' . $action]);
    }

} catch (Throwable $e) {
    error_log("❌ API KPIs Fatal: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno: ' . $e->getMessage()]);
    exit;
}