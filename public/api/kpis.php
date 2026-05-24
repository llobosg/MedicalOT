<?php
/**
 * API para KPIs del Dashboard - MedicalOT
 * Hospital de Antofagasta
 */

define('APP_ENTRY_POINT', true);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';

header('Content-Type: application/json');

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$group_by = $_GET['group_by'] ?? 'especialidad';

try {
    $db = getDB();
    
    switch ($action) {
        
        case 'global':
            // === KPIs GLOBALES ===
            $response = getGlobalKpis($db);
            echo json_encode($response);
            break;
            
        case 'chart_data':
            // === DATOS PARA GRÁFICOS ===
            $response = getChartData($db, $group_by);
            echo json_encode($response);
            break;
            
        case 'recent_ots':
            // === ÚLTIMAS OTs PROCESADAS ===
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            $response = getRecentOTs($db, $limit);
            echo json_encode($response);
            break;
            
        case 'by_month':
            // === KPIs POR MES (Filtros) ===
            $month = $_GET['month'] ?? date('Y-m');
            $response = getKpisByMonth($db, $month);
            echo json_encode($response);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Acción no válida']);
    }
    
} catch (PDOException $e) {
    error_log("Error KPIs API: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno del servidor']);
}

// ============================================================================
// FUNCIONES PRINCIPALES
// ============================================================================

/**
 * Obtiene los KPIs globales del dashboard
 */
function getGlobalKpis($db) {
    $result = [
        'sla_percent' => 0,
        'hh_real' => 0,
        'hh_plan' => 0,
        'ots_closed' => 0,
        'ots_riesgo' => 0,
        'total_ots' => 0
    ];
    
    try {
        // 1. Total de OTs y cerradas
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN estado = 'cerrada' THEN 1 ELSE 0 END) as cerradas
            FROM ots 
            WHERE fecha_programada >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
        ");
        $stmt->execute();
        $counts = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $result['total_ots'] = $counts['total'] ?? 0;
        $result['ots_closed'] = $counts['cerradas'] ?? 0;
        
        // 2. HH Planificadas vs Reales
        $stmt = $db->prepare("
            SELECT 
                COALESCE(SUM(hh_programadas), 0) as hh_plan,
                COALESCE(SUM(hh_reales), 0) as hh_real
            FROM ots 
            WHERE fecha_programada >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
        ");
        $stmt->execute();
        $hh = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $result['hh_plan'] = round($hh['hh_plan'] ?? 0, 2);
        $result['hh_real'] = round($hh['hh_real'] ?? 0, 2);
        
        // 3. SLA: OTs cerradas dentro del plazo (fecha_real <= fecha_programada + tolerancia)
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_cerradas,
                SUM(CASE 
                    WHEN fecha_cierre <= DATE_ADD(fecha_programada, INTERVAL 3 DAY) 
                    THEN 1 ELSE 0 
                END) as dentro_sla
            FROM ots 
            WHERE estado = 'cerrada' 
            AND fecha_programada >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
        ");
        $stmt->execute();
        $sla = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $total_cerradas = $sla['total_cerradas'] ?? 1; // Evitar división por cero
        $dentro_sla = $sla['dentro_sla'] ?? 0;
        $result['sla_percent'] = round(($dentro_sla / $total_cerradas) * 100);
        
        // 4. OTs en Riesgo: Programadas para hoy o antes, NO cerradas, con más de 7 días de retraso
        $stmt = $db->prepare("
            SELECT COUNT(*) as en_riesgo
            FROM ots 
            WHERE estado NOT IN ('cerrada', 'cancelada')
            AND fecha_programada <= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        ");
        $stmt->execute();
        $riesgo = $stmt->fetch(PDO::FETCH_ASSOC);
        $result['ots_riesgo'] = $riesgo['en_riesgo'] ?? 0;
        
        return ['success' => true, 'data' => $result];
        
    } catch (PDOException $e) {
        error_log("Error getGlobalKpis: " . $e->getMessage());
        return ['success' => false, 'error' => 'Error calculando KPIs'];
    }
}

/**
 * Obtiene datos para gráficos agrupados por especialidad, área o equipo
 */
function getChartData($db, $groupBy = 'especialidad') {
    try {
        $validGroups = ['especialidad', 'area', 'equipo', 'estado'];
        if (!in_array($groupBy, $validGroups)) {
            $groupBy = 'especialidad';
        }
        
        // Mapeo de campos según agrupación
        $fieldMap = [
            'especialidad' => ['field' => 'cod_especialidad', 'label' => 'nombre_especialidad'],
            'area' => ['field' => 'cod_area', 'label' => 'nombre_area'],
            'equipo' => ['field' => 'cod_equipo', 'label' => 'nombre_equipo'],
            'estado' => ['field' => 'estado', 'label' => 'estado']
        ];
        
        $config = $fieldMap[$groupBy];
        $field = $config['field'];
        $label = $config['label'];
        
        // Consulta principal para HH por grupo
        $stmt = $db->prepare("
            SELECT 
                COALESCE($field, 'Sin clasificar') as code,
                COALESCE($label, 'Sin clasificar') as label,
                COALESCE(SUM(hh_programadas), 0) as hh_plan,
                COALESCE(SUM(hh_reales), 0) as hh_real,
                COUNT(*) as total_ots
            FROM ots 
            WHERE fecha_programada >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY $field
            ORDER BY hh_plan DESC
            LIMIT 15
        ");
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Formatear datos para Chart.js
        $chartData = array_map(function($row) {
            return [
                'label' => $row['label'] ?? $row['code'],
                'code' => $row['code'],
                'hh_plan' => round($row['hh_plan'], 2),
                'hh_real' => round($row['hh_real'], 2),
                'total_ots' => $row['total_ots']
            ];
        }, $data);
        
        return ['success' => true, 'data' => $chartData, 'group_by' => $groupBy];
        
    } catch (PDOException $e) {
        error_log("Error getChartData: " . $e->getMessage());
        return ['success' => false, 'error' => 'Error obteniendo datos de gráfico'];
    }
}

/**
 * Obtiene las últimas OTs procesadas para la tabla
 */
function getRecentOTs($db, $limit = 10) {
    try {
        $stmt = $db->prepare("
            SELECT 
                codigo_ot,
                nombre_equipo,
                estado,
                ROUND(hh_programadas, 1) as hh_plan,
                ROUND(hh_reales, 1) as hh_real,
                fecha_programada,
                fecha_cierre,
                CASE 
                    WHEN estado = 'cerrada' AND fecha_cierre IS NOT NULL 
                    THEN DATEDIFF(fecha_cierre, fecha_programada)
                    WHEN estado NOT IN ('cerrada', 'cancelada') 
                    THEN DATEDIFF(CURDATE(), fecha_programada)
                    ELSE 0 
                END as retraso_dias
            FROM ots 
            WHERE fecha_programada >= DATE_SUB(CURDATE(), INTERVAL 2 MONTH)
            ORDER BY fecha_programada DESC, codigo_ot DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $limit, PDO::FETCH_INT);
        $stmt->execute();
        $ots = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Formatear estados para badges
        $stateLabels = [
            'pendiente' => ['label' => 'Pendiente', 'class' => 'b-pen'],
            'asignada' => ['label' => 'Asignada', 'class' => 'b-asi'],
            'en_proceso' => ['label' => 'En Proceso', 'class' => 'b-pro'],
            'cerrada' => ['label' => 'Cerrada', 'class' => 'b-cer'],
            'cancelada' => ['label' => 'Cancelada', 'class' => 'b-cer']
        ];
        
        $formatted = array_map(function($ot) use ($stateLabels) {
            $state = $ot['estado'] ?? 'pendiente';
            $badge = $stateLabels[$state] ?? ['label' => $state, 'class' => 'b-pen'];
            
            // Calcular retraso visual
            $retraso = $ot['retraso_dias'] ?? 0;
            $retrasoClass = $retraso > 7 ? 'text-red-600 font-bold' : ($retraso > 3 ? 'text-yellow-600' : 'text-gray-600');
            $retrasoText = $retraso > 0 ? "+{$retraso}d" : '–';
            
            return [
                'codigo_ot' => $ot['codigo_ot'],
                'equipo' => $ot['nombre_equipo'] ?? 'Sin equipo',
                'estado' => $badge['label'],
                'estado_class' => $badge['class'],
                'hh_plan' => $ot['hh_plan'],
                'hh_real' => $ot['hh_real'],
                'retraso' => $retrasoText,
                'retraso_class' => $retrasoClass,
                'fecha' => $ot['fecha_programada'] ? date('d/m/Y', strtotime($ot['fecha_programada'])) : '-'
            ];
        }, $ots);
        
        return ['success' => true, 'data' => $formatted];
        
    } catch (PDOException $e) {
        error_log("Error getRecentOTs: " . $e->getMessage());
        return ['success' => false, 'error' => 'Error obteniendo OTs recientes'];
    }
}

/**
 * Obtiene KPIs filtrados por mes específico
 */
function getKpisByMonth($db, $month) {
    try {
        // Validar formato de mes (YYYY-MM)
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
        }
        
        $startDate = $month . '-01';
        $endDate = date('Y-m-t', strtotime($startDate)); // Último día del mes
        
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_ots,
                SUM(CASE WHEN estado = 'cerrada' THEN 1 ELSE 0 END) as cerradas,
                COALESCE(SUM(hh_programadas), 0) as hh_plan,
                COALESCE(SUM(hh_reales), 0) as hh_real,
                SUM(CASE 
                    WHEN estado = 'cerrada' AND fecha_cierre <= DATE_ADD(fecha_programada, INTERVAL 3 DAY) 
                    THEN 1 ELSE 0 
                END) as dentro_sla
            FROM ots 
            WHERE fecha_programada BETWEEN ? AND ?
        ");
        $stmt->execute([$startDate, $endDate]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $total_cerradas = $data['cerradas'] ?? 1;
        $dentro_sla = $data['dentro_sla'] ?? 0;
        
        return [
            'success' => true,
            'data' => [
                'period' => $month,
                'total_ots' => $data['total_ots'] ?? 0,
                'ots_closed' => $data['cerradas'] ?? 0,
                'hh_plan' => round($data['hh_plan'] ?? 0, 2),
                'hh_real' => round($data['hh_real'] ?? 0, 2),
                'sla_percent' => round(($dentro_sla / $total_cerradas) * 100),
                'eficiencia' => $data['hh_plan'] > 0 
                    ? round(($data['hh_real'] / $data['hh_plan']) * 100) 
                    : 0
            ]
        ];
        
    } catch (PDOException $e) {
        error_log("Error getKpisByMonth: " . $e->getMessage());
        return ['success' => false, 'error' => 'Error filtrando por mes'];
    }
}
?>