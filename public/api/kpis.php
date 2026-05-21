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
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'No autorizado']);
        exit;
    }

    $action = $_GET['action'] ?? 'global';

    try {
        // ------------------------------------------------------------------
        // ACCIÓN: KPIs GLOBALES (Las fichas superiores)
        // ------------------------------------------------------------------
        if ($action === 'global') {
            // 1. Total OTs Procesadas (Última carga)
            $totalOts = $pdo->query("SELECT COUNT(*) FROM ot_resumen_actual")->fetchColumn();
            
            // 2. HHs Plan vs Real (Acumulado histórico)
            $hhStats = $pdo->query("
                SELECT 
                    SUM(total_hh_planificadas) as total_plan,
                    SUM(total_hh_reales_acumuladas) as total_real
                FROM ot_resumen_actual
            ")->fetch(PDO::FETCH_ASSOC);

            // 3. SLA Cumplido (% de OTs completadas a tiempo - simplificado)
            // Asumimos que si días_retraso <= 0 está bien
            $slaQuery = $pdo->query("
                SELECT 
                    COUNT(*) as total_completadas,
                    SUM(CASE WHEN dias_retraso <= 0 THEN 1 ELSE 0 END) as a_tiempo
                FROM ot_resumen_actual 
                WHERE ultimo_estado = 'completada'
            ")->fetch(PDO::FETCH_ASSOC);
            
            $slaPercent = 0;
            if ($slaQuery['total_completadas'] > 0) {
                $slaPercent = round(($slaQuery['a_tiempo'] / $slaQuery['total_completadas']) * 100, 1);
            }

            // 4. OTs En Riesgo (Retrasadas > 7 días y no completadas)
            $riesgo = $pdo->query("
                SELECT COUNT(*) FROM ot_resumen_actual 
                WHERE ultimo_estado IN ('pendiente', 'en_ejecucion', 'reprogramada') 
                AND dias_retraso > 7
            ")->fetchColumn();

            echo json_encode([
                'success' => true,
                'data' => [
                    'total_ots' => (int)$totalOts,
                    'hh_plan' => floatval($hhStats['total_plan']),
                    'hh_real' => floatval($hhStats['total_real']),
                    'sla_percent' => floatval($slaPercent),
                    'ots_riesgo' => (int)$riesgo
                ]
            ]);

        // ------------------------------------------------------------------
        // ACCIÓN: DATOS PARA GRÁFICOS (Drill-down)
        // ------------------------------------------------------------------
        } elseif ($action === 'chart_data') {
            $groupBy = $_GET['group_by'] ?? 'especialidad'; // especialidad, vertical, tipo
            
            $fieldMap = [
                'especialidad' => ['table' => 'e', 'col' => 'nombre', 'join' => 'LEFT JOIN especialidades e ON ra.id_especialidad = e.id'],
                'vertical' => ['table' => 'v', 'col' => 'nombre_vertical', 'join' => 'LEFT JOIN verticales v ON ra.id_vertical = v.id_vertical'],
                'tipo' => ['table' => 'ra', 'col' => 'tipo_mantenimiento', 'join' => '']
            ];

            $config = $fieldMap[$groupBy] ?? $fieldMap['especialidad'];

            $stmt = $pdo->query("
                SELECT 
                    {$config['table']}.{$config['col']} as label,
                    SUM(ra.total_hh_planificadas) as hh_plan,
                    SUM(ra.total_hh_reales_acumuladas) as hh_real,
                    COUNT(*) as count_ots
                FROM ot_resumen_actual ra
                {$config['join']}
                GROUP BY {$config['table']}.{$config['col']}
                ORDER BY hh_plan DESC
            ");

            echo json_encode([
                'success' => true,
                'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ]);

        // ------------------------------------------------------------------
        // ACCIÓN: HISTORIAL DE UNA OT ESPECÍFICA (Bitácora)
        // ------------------------------------------------------------------
        } elseif ($action === 'ot_history') {
            $codigoOt = $_GET['codigo_ot'] ?? '';
            if (empty($codigoOt)) throw new Exception("Código OT requerido");

            $stmt = $pdo->prepare("
                SELECT 
                    fecha_carga,
                    fuente,
                    estado,
                    hh_planificadas,
                    hh_reales,
                    fecha_programada,
                    observaciones
                FROM ot_historico
                WHERE codigo_ot = ?
                ORDER BY fecha_carga ASC
            ");
            $stmt->execute([$codigoOt]);

            echo json_encode([
                'success' => true,
                'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ]);

        } else {
            throw new Exception("Acción no válida");
        }

    } catch (\Exception $e) {
        error_log("Error KPIs: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }

} catch (\Throwable $e) {
    error_log("❌ API KPIs Fatal: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno del servidor.']);
    exit;
}