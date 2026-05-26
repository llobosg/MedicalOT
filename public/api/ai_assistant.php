<?php
// public/api/ai_assistant.php
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

    $input = json_decode(file_get_contents('php://input'), true);
    $userMessage = trim($input['message'] ?? '');
    
    if (empty($userMessage)) {
        throw new Exception("Mensaje vacío");
    }

    // ═══════════════════════════════════════════════════════
    // 📊 OBTENER DATOS REALES DE LA BD
    // ═══════════════════════════════════════════════════════
    $stats = getDatabaseStats($pdo);
    
    // ═══════════════════════════════════════════════════════
    // 🎭 MODO MOCK INTELIGENTE (Funciona 100% sin APIs externas)
    // ═══════════════════════════════════════════════════════
    $response = generateMockResponse($userMessage, $stats);
    
    // Simular delay de "pensamiento" para que se vea más real
    usleep(600000); // 0.6 segundos
    
    echo json_encode([
        'success' => true,
        'message' => $response,
        'mode' => 'mock-inteligente'
    ]);

} catch (\Throwable $e) {
    error_log("❌ Error AI: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error: ' . $e->getMessage()
    ]);
    exit;
}

// ═══════════════════════════════════════════════════════
// 📊 OBTENER ESTADÍSTICAS DE LA BD
// ═══════════════════════════════════════════════════════
function getDatabaseStats($pdo) {
    $stats = [];
    
    // Stats generales
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_ots,
            COALESCE(SUM(total_hh_planificadas), 0) as hh_plan,
            SUM(CASE WHEN dias_retraso > 7 AND ultimo_estado IN ('pendiente','asignada','en_ejecucion') THEN 1 ELSE 0 END) as ots_riesgo,
            SUM(CASE WHEN ultimo_estado IN ('completada','cerrada') THEN 1 ELSE 0 END) as ots_cerradas,
            SUM(CASE WHEN veces_reprogramadas > 0 THEN 1 ELSE 0 END) as ots_reprogramadas
        FROM ot_resumen_actual
    ");
    $stats['general'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Top especialidades
    $stmt = $pdo->query("
        SELECT id_especialidad, COUNT(*) as total, ROUND(SUM(total_hh_planificadas),1) as hh,
               SUM(CASE WHEN dias_retraso > 7 THEN 1 ELSE 0 END) as en_riesgo
        FROM ot_resumen_actual WHERE id_especialidad IS NOT NULL
        GROUP BY id_especialidad ORDER BY hh DESC LIMIT 5
    ");
    $stats['especialidades'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Top OTs en riesgo
    $stmt = $pdo->query("
        SELECT id_prevision_sic, nombre_equipo, dias_retraso, total_hh_planificadas, id_especialidad
        FROM ot_resumen_actual
        WHERE dias_retraso > 7 AND ultimo_estado IN ('pendiente','asignada','en_ejecucion')
        ORDER BY dias_retraso DESC LIMIT 5
    ");
    $stats['ots_riesgo'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Distribución por mes
    $stmt = $pdo->query("
        SELECT mes_carga, COUNT(*) as total, ROUND(SUM(total_hh_planificadas),1) as hh
        FROM ot_resumen_actual WHERE mes_carga IS NOT NULL
        GROUP BY mes_carga
        ORDER BY FIELD(mes_carga,'enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre')
    ");
    $stats['meses'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $stats;
}

// ═══════════════════════════════════════════════════════
// 🎭 GENERADOR DE RESPUESTAS INTELIGENTES
// ═══════════════════════════════════════════════════════
function generateMockResponse($message, $stats) {
    $msg = strtolower($message);
    $espMap = [
        50=>'M-CLIMATIZACIÓN',
        51=>'M-ELECTRICIDAD',
        52=>'M-GASFITERÍA',
        53=>'M-ELECTRÓNICA',
        54=>'M-CARPINTERÍA',
        55=>'M-ELECTROMECÁNICA',
        57=>'M-POLIVALENTE'
    ];
    
    // Saludos
    if (preg_match('/^(hola|buenos|buenas|hey|hi|hello)/', $msg)) {
        return "¡Hola! 👋 Soy tu asistente de MedicalOT.\n\n" .
               "Tengo acceso a los datos del hospital:\n" .
               "• **{$stats['general']['total_ots']}** órdenes de trabajo registradas\n" .
               "• **{$stats['general']['hh_plan']}** horas planificadas\n" .
               "• **{$stats['general']['ots_riesgo']}** OTs en riesgo\n\n" .
               "¿Qué te gustaría analizar? Puedo ayudarte con:\n" .
               "📊 Resúmenes ejecutivos\n" .
               "⚠️ Análisis de riesgos\n" .
               "🏆 Desglose por especialidad\n" .
               "📈 Tendencias y comparativas";
    }
    
    // Resumen ejecutivo
    if (preg_match('/(resumen|ejecutivo|panorama|c[oó]mo vamos|general|estado)/', $msg)) {
        $topEsp = $stats['especialidades'][0] ?? null;
        $topEspName = $topEsp ? ($espMap[$topEsp['id_especialidad']] ?? 'N/A') : 'N/A';
        
        return "📊 **RESUMEN EJECUTIVO - MEDICALOT**\n\n" .
               "🎯 **KPIs Principales:**\n" .
               "• **{$stats['general']['total_ots']}** OTs totales en el sistema\n" .
               "• **{$stats['general']['hh_plan']}** HHs planificadas\n" .
               "• **{$stats['general']['ots_cerradas']}** OTs cerradas\n" .
               "• **{$stats['general']['ots_riesgo']}** OTs en riesgo (+7 días) ⚠️\n\n" .
               "🏆 **Especialidad con Mayor Carga:**\n" .
               "$topEspName con **{$topEsp['hh']}** HHs planificadas\n\n" .
               "💡 **Recomendación:**\n" .
               "Priorizar revisión de las **{$stats['general']['ots_riesgo']}** OTs en riesgo, " .
               "especialmente en {$topEspName} que concentra la mayor carga operativa.";
    }
    
    // Prioridades / OTs críticas
    if (preg_match('/(priori|cr[ií]tic|urgente|qu[eé] (deber[ií]a|atiendo|hago hoy))/i', $msg)) {
        $resp = "🎯 **TOP 5 OTs PRIORITARIAS PARA ATENDER HOY**\n\n";
        $resp .= "Ordenadas por días de retraso:\n\n";
        
        foreach ($stats['ots_riesgo'] as $i => $ot) {
            $esp = $espMap[$ot['id_especialidad']] ?? 'N/A';
            $resp .= "**" . ($i+1) . ". ID {$ot['id_prevision_sic']}** - {$ot['nombre_equipo']}\n";
            $resp .= "   ⏰ **{$ot['dias_retraso']} días** de retraso | {$ot['total_hh_planificadas']} HHs | $esp\n\n";
        }
        
        $resp .= "💡 **Acción sugerida:**\n";
        $resp .= "Contactar a los responsables de estas OTs y reprogramar para esta semana. " .
                 "La primera ({$stats['ots_riesgo'][0]['dias_retraso']} días) requiere atención inmediata.";
        
        return $resp;
    }
    
    // Especialidades
    if (preg_match('/(especialidad|materia|[aá]rea|disciplina|electricidad|climatizaci|gasfiter|polivalente)/', $msg)) {
        $resp = "🏆 **RANKING DE ESPECIALIDADES POR CARGA**\n\n";
        
        foreach ($stats['especialidades'] as $i => $e) {
            $nombre = $espMap[$e['id_especialidad']] ?? "Esp.{$e['id_especialidad']}";
            $medal = $i === 0 ? '🥇' : ($i === 1 ? '🥈' : ($i === 2 ? '🥉' : '•'));
            $alert = $e['en_riesgo'] > 0 ? " | ⚠️ **{$e['en_riesgo']} en riesgo**" : " | ✅ Sin riesgo";
            
            $resp .= "$medal **$nombre**\n";
            $resp .= "   {$e['total']} OTs | {$e['hh']} HHs$alert\n\n";
        }
        
        $topEsp = $espMap[$stats['especialidades'][0]['id_especialidad']] ?? 'N/A';
        $resp .= "💡 **Insight:** $topEsp concentra la mayor carga. " .
                 "Considerar refuerzo de personal o tercerización si el backlog sigue creciendo.";
        
        return $resp;
    }
    
    // Riesgo / retraso / problemas
    if (preg_match('/(riesgo|retraso|atraso|problema|alerta)/', $msg)) {
        $resp = "⚠️ **ANÁLISIS DE OTs EN RIESGO**\n\n";
        $resp .= "**{$stats['general']['ots_riesgo']}** órdenes llevan más de 7 días de retraso.\n\n";
        $resp .= "🔥 **Las 5 más críticas:**\n\n";
        
        foreach ($stats['ots_riesgo'] as $ot) {
            $esp = $espMap[$ot['id_especialidad']] ?? 'N/A';
            $nivel = $ot['dias_retraso'] > 30 ? '🚨 CRÍTICO' : ($ot['dias_retraso'] > 14 ? '⚠️ ALTO' : '⚡ MEDIO');
            $resp .= "$nivel - ID {$ot['id_prevision_sic']} ({$ot['dias_retraso']}d)\n";
            $resp .= "   {$ot['nombre_equipo']} | $esp\n\n";
        }
        
        $criticos = count(array_filter($stats['ots_riesgo'], fn($o) => $o['dias_retraso'] > 30));
        $resp .= "💡 **Plan de acción:**\n";
        $resp .= "1. Reunión urgente con jefes de área para los **{$criticos} casos críticos** (+30 días)\n";
        $resp .= "2. Reasignar recursos de especialidades con baja carga\n";
        $resp .= "3. Evaluar tercerización para reducir backlog";
        
        return $resp;
    }
    
    // Meses / tendencias
    if (preg_match('/(mes|enero|febrero|marzo|abril|mayo|junio|julio|agosto|septiembre|octubre|noviembre|diciembre|tendencia|compar)/', $msg)) {
        $resp = "📅 **DISTRIBUCIÓN POR MES**\n\n";
        
        foreach ($stats['meses'] as $m) {
            $resp .= "**" . ucfirst($m['mes_carga']) . "**: {$m['total']} OTs | {$m['hh']} HHs\n";
        }
        
        // Encontrar el mes con más carga
        $maxMes = null;
        $maxHH = 0;
        foreach ($stats['meses'] as $m) {
            if ($m['hh'] > $maxHH) {
                $maxHH = $m['hh'];
                $maxMes = $m['mes_carga'];
            }
        }
        
        $resp .= "\n💡 **Insight:** El mes con mayor carga es **" . ucfirst($maxMes) . "** con {$maxHH} HHs. " .
                 "Planificar recursos adicionales para ese periodo.";
        
        return $resp;
    }
    
    // Reprogramaciones
    if (preg_match('/(reprogram|cambio|modif)/', $msg)) {
        return "🔄 **ANÁLISIS DE REPROGRAMACIONES**\n\n" .
               "**{$stats['general']['ots_reprogramadas']}** OTs han sido reprogramadas al menos una vez.\n\n" .
               "Esto representa el **" . round(($stats['general']['ots_reprogramadas'] / max(1,$stats['general']['total_ots'])) * 100, 1) . "%** del total.\n\n" .
               "💡 **Posibles causas:**\n" .
               "• Falta de recursos en fechas programadas\n" .
               "• Cambios de prioridad en el hospital\n" .
               "• Problemas con proveedores externos\n\n" .
               "📋 **Recomendación:** Revisar el proceso de planificación inicial para reducir reprogramaciones futuras.";
    }
    
    // Anomalías / inusual
    if (preg_match('/(anomal|inusual|raro|extra[nñ]o|detect)/', $msg)) {
        $resp = "🔍 **DETECCIÓN DE ANOMALÍAS**\n\n";
        
        // Buscar meses con carga inusual
        $avgHH = array_sum(array_column($stats['meses'], 'hh')) / max(1, count($stats['meses']));
        $resp .= "📊 **Carga promedio mensual:** " . round($avgHH, 1) . " HHs\n\n";
        
        foreach ($stats['meses'] as $m) {
            if ($m['hh'] > $avgHH * 1.3) {
                $resp .= "⚠️ **" . ucfirst($m['mes_carga']) . "** tiene carga alta: {$m['hh']} HHs (+30% vs promedio)\n";
            }
        }
        
        $resp .= "\n🎯 **OTs con reprogramaciones múltiples:**\n";
        $resp .= "**{$stats['general']['ots_reprogramadas']}** OTs reprogramadas (posible problema de planificación)\n\n";
        
        $resp .= "💡 **Acción sugerida:** Investigar los picos de carga y las OTs reprogramadas recurrentes.";
        
        return $resp;
    }
    
    // Respuesta genérica
    return "📊 **DATOS DISPONIBLES**\n\n" .
           "Tengo acceso a estos indicadores:\n\n" .
           "• **{$stats['general']['total_ots']}** OTs totales\n" .
           "• **{$stats['general']['hh_plan']}** HHs planificadas\n" .
           "• **{$stats['general']['ots_riesgo']}** OTs en riesgo\n" .
           "• **{$stats['general']['ots_cerradas']}** OTs cerradas\n\n" .
           "Puedo analizar:\n" .
           "📊 Resúmenes ejecutivos\n" .
           "🎯 Prioridades del día\n" .
           "🏆 Desglose por especialidad\n" .
           "⚠️ OTs en riesgo\n" .
           "📅 Tendencias mensuales\n\n" .
           "¿Qué te gustaría explorar?";
}