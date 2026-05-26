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
    // 🤖 INTENTAR OPENROUTER (IA Real Gratuita)
    // ═══════════════════════════════════════════════════════
    $apiKey = getenv('OPENAI_API_KEY'); // Aquí va tu key de OpenRouter (sk-or-...)
    
    if ($apiKey) {
        $aiResponse = callOpenRouter($userMessage, $stats, $apiKey);
        
        if ($aiResponse !== null) {
            echo json_encode([
                'success' => true,
                'message' => $aiResponse,
                'mode' => 'openrouter',
                'model' => 'meta-llama/llama-3.1-8b-instruct:free'
            ]);
            exit;
        }
    }
    
    // ═══════════════════════════════════════════════════════
    // 🎭 FALLBACK: MODO MOCK INTELIGENTE
    // ═══════════════════════════════════════════════════════
    $mockResponse = generateMockResponse($userMessage, $stats);
    
    // Simular delay de "pensamiento"
    usleep(600000); // 0.6 segundos
    
    echo json_encode([
        'success' => true,
        'message' => $mockResponse,
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
// 🤖 FUNCIÓN: LLAMAR A OPENROUTER
// ═══════════════════════════════════════════════════════
function callOpenRouter($userMessage, $stats, $apiKey) {
    $apiEndpoint = 'https://openrouter.ai/api/v1/chat/completions';
    $aiModel = 'meta-llama/llama-3.1-8b-instruct:free';
    
    $systemPrompt = "Eres el asistente experto de MedicalOT para el Hospital de Antofagasta. " .
                    "Responde basándote SOLO en los datos proporcionados. " .
                    "Sé conciso, profesional y orientado a la acción. " .
                    "Usa emojis moderadamente y formato estructurado con negritas (**texto**) y bullets (•). " .
                    "SIEMPRE responde en español.";
    
    $context = buildContext($stats);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $apiEndpoint,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "Authorization: Bearer $apiKey",
            "HTTP-Referer: https://medicalot.up.railway.app",
            "X-Title: MedicalOT"
        ],
        CURLOPT_POSTFIELDS => json_encode([
            "model" => $aiModel,
            "messages" => [
                ["role" => "system", "content" => $systemPrompt],
                ["role" => "user", "content" => "Contexto de datos:\n$context\n\nPregunta del usuario: $userMessage"]
            ],
            "temperature" => 0.7,
            "max_tokens" => 800
        ])
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        error_log("❌ OpenRouter Error (HTTP $httpCode): $response");
        return null; // Falló, usar mock
    }

    $result = json_decode($response, true);
    return $result['choices'][0]['message']['content'] ?? null;
}

// ═══════════════════════════════════════════════════════
// 📝 FUNCIÓN: CONSTRUIR CONTEXTO PARA LA IA
// ═══════════════════════════════════════════════════════
function buildContext($stats) {
    $espMap = [
        50=>'M-CLIMATIZACIÓN', 51=>'M-ELECTRICIDAD', 52=>'M-GASFITERÍA',
        53=>'M-ELECTRÓNICA', 54=>'M-CARPINTERÍA', 55=>'M-ELECTROMECÁNICA',
        57=>'M-POLIVALENTE'
    ];
    
    $ctx = "📊 ESTADÍSTICAS GENERALES:\n";
    $ctx .= "• Total de OTs: {$stats['general']['total_ots']}\n";
    $ctx .= "• HHs Planificadas: {$stats['general']['hh_plan']}\n";
    $ctx .= "• OTs Cerradas: {$stats['general']['ots_cerradas']}\n";
    $ctx .= "• OTs en Riesgo (+7 días): {$stats['general']['ots_riesgo']}\n";
    $ctx .= "• OTs Reprogramadas: {$stats['general']['ots_reprogramadas']}\n\n";
    
    $ctx .= "🏆 TOP 5 ESPECIALIDADES (por HHs):\n";
    foreach ($stats['especialidades'] as $e) {
        $nombre = $espMap[$e['id_especialidad']] ?? "Esp.{$e['id_especialidad']}";
        $ctx .= "• {$nombre}: {$e['total']} OTs, {$e['hh']} HHs, {$e['en_riesgo']} en riesgo\n";
    }
    $ctx .= "\n";
    
    $ctx .= "📅 DISTRIBUCIÓN POR MES:\n";
    foreach ($stats['meses'] as $m) {
        $ctx .= "• " . ucfirst($m['mes_carga']) . ": {$m['total']} OTs, {$m['hh']} HHs\n";
    }
    $ctx .= "\n";
    
    if (!empty($stats['ots_riesgo'])) {
        $ctx .= "⚠️ TOP 5 OTs EN MAYOR RIESGO:\n";
        foreach ($stats['ots_riesgo'] as $ot) {
            $esp = $espMap[$ot['id_especialidad']] ?? 'N/A';
            $ctx .= "• ID {$ot['id_prevision_sic']}: {$ot['nombre_equipo']} [{$esp}] - {$ot['dias_retraso']} días retraso\n";
        }
    }
    
    return $ctx;
}

// ═══════════════════════════════════════════════════════
// 📊 OBTENER ESTADÍSTICAS DE LA BD (YA EXISTE)
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
// 🎭 GENERADOR DE RESPUESTAS MOCK (YA EXISTE - MANTENER IGUAL)
// ═══════════════════════════════════════════════════════
function generateMockResponse($message, $stats) {
    $msg = strtolower($message);
    $espMap = [
        50=>'M-CLIMATIZACIÓN', 51=>'M-ELECTRICIDAD', 52=>'M-GASFITERÍA',
        53=>'M-ELECTRÓNICA', 54=>'M-CARPINTERÍA', 55=>'M-ELECTROMECÁNICA',
        57=>'M-POLIVALENTE'
    ];
    
    // Saludos
    if (preg_match('/^(hola|buenos|buenas|hey|hi|hello)/', $msg)) {
        return "¡Hola! 👋 Soy tu asistente de MedicalOT.\n\n" .
               "Tengo acceso a los datos del hospital:\n" .
               "• **{$stats['general']['total_ots']}** órdenes de trabajo registradas\n" .
               "• **{$stats['general']['hh_plan']}** horas planificadas\n" .
               "• **{$stats['general']['ots_riesgo']}** OTs en riesgo\n\n" .
               "¿Qué te gustaría analizar?";
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
               "Priorizar revisión de las **{$stats['general']['ots_riesgo']}** OTs en riesgo.";
    }
    
    // Prioridades
    if (preg_match('/(priori|cr[ií]tic|urgente|qu[eé] (deber[ií]a|atiendo|hago hoy))/i', $msg)) {
        $resp = "🎯 **TOP 5 OTs PRIORITARIAS PARA ATENDER HOY**\n\n";
        
        foreach ($stats['ots_riesgo'] as $i => $ot) {
            $esp = $espMap[$ot['id_especialidad']] ?? 'N/A';
            $resp .= "**" . ($i+1) . ". ID {$ot['id_prevision_sic']}** - {$ot['nombre_equipo']}\n";
            $resp .= "   ⏰ **{$ot['dias_retraso']} días** de retraso | {$ot['total_hh_planificadas']} HHs | $esp\n\n";
        }
        
        $resp .= "💡 **Acción sugerida:**\nContactar a los responsables y reprogramar para esta semana.";
        
        return $resp;
    }
    
    // Especialidades
    if (preg_match('/(especialidad|materia|[aá]rea|disciplina|electricidad|climatizaci|gasfiter|polivalente)/', $msg)) {
        $resp = "🏆 **RANKING DE ESPECIALIDADES POR CARGA**\n\n";
        
        foreach ($stats['especialidades'] as $i => $e) {
            $nombre = $espMap[$e['id_especialidad']] ?? "Esp.{$e['id_especialidad']}";
            $medal = $i === 0 ? '🥇' : ($i === 1 ? '🥈' : ($i === 2 ? '🥉' : '•'));
            $alert = $e['en_riesgo'] > 0 ? " | ⚠️ **{$e['en_riesgo']} en riesgo**" : "";
            
            $resp .= "$medal **$nombre**\n";
            $resp .= "   {$e['total']} OTs | {$e['hh']} HHs$alert\n\n";
        }
        
        return $resp;
    }
    
    // Riesgo
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
        
        return $resp;
    }
    
    // Meses
    if (preg_match('/(mes|enero|febrero|marzo|abril|mayo|junio|julio|agosto|septiembre|octubre|noviembre|diciembre|tendencia|compar)/', $msg)) {
        $resp = "📅 **DISTRIBUCIÓN POR MES**\n\n";
        
        foreach ($stats['meses'] as $m) {
            $resp .= "**" . ucfirst($m['mes_carga']) . "**: {$m['total']} OTs | {$m['hh']} HHs\n";
        }
        
        $maxMes = null;
        $maxHH = 0;
        foreach ($stats['meses'] as $m) {
            if ($m['hh'] > $maxHH) {
                $maxHH = $m['hh'];
                $maxMes = $m['mes_carga'];
            }
        }
        
        $resp .= "\n💡 **Insight:** El mes con mayor carga es **" . ucfirst($maxMes) . "** con {$maxHH} HHs.";
        
        return $resp;
    }
    
    // Respuesta genérica
    return "📊 **DATOS DISPONIBLES**\n\n" .
           "Tengo acceso a estos indicadores:\n\n" .
           "• **{$stats['general']['total_ots']}** OTs totales\n" .
           "• **{$stats['general']['hh_plan']}** HHs planificadas\n" .
           "• **{$stats['general']['ots_riesgo']}** OTs en riesgo\n\n" .
           "¿Qué te gustaría explorar?";
}