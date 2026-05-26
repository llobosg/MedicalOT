<?php
// public/api/ai_assistant.php
header('Content-Type: application/json; charset=utf-8');
while (ob_get_level()) ob_end_clean();

// ═══════════════════════════════════════════════════════
// 📊 CONSTANTES DE CAPACIDAD OPERATIVA
// ═══════════════════════════════════════════════════════
define('HORAS_POR_TECNICO_MES', 160);
define('HORAS_POR_TECNICO_SEMANA', 40);
define('DIAS_LABORABLES_MES', 20);
define('FACTOR_EFICIENCIA', 0.85);
define('HORAS_EFECTIVAS_MES', HORAS_POR_TECNICO_MES * FACTOR_EFICIENCIA);

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

    // Obtener datos reales de la BD
    $stats = getDatabaseStats($pdo);
    
    // Intentar OpenRouter
    $apiKey = getenv('OPENAI_API_KEY');
    
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
    
    // Fallback: modo mock inteligente
    $mockResponse = generateMockResponse($userMessage, $stats);
    usleep(600000);
    
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
                    "SIEMPRE responde en español.\n\n" .
                    "REGLA CRÍTICA: Si el contexto incluye datos 'YA CALCULADOS' (como promedios), " .
                    "USA ESOS VALORES DIRECTAMENTE en tu respuesta. NO recalculles ni listes todos los datos.\n\n" .
                    "EJEMPLO:\n" .
                    "Pregunta: '¿Cuál es el promedio de HHs por mes?'\n" .
                    "Contexto: 'PROMEDIO de HHs por mes: 8285.5 HHs'\n" .
                    "✅ Respuesta CORRECTA: '📊 El promedio es **8,285.5 HHs/mes**'\n" .
                    "❌ Respuesta INCORRECTA: Listar todos los meses y luego calcular\n\n" .
                    "CAPACIDADES:\n" .
                    "• Cálculos matemáticos (divisiones, porcentajes, proyecciones)\n" .
                    "• Estimación de recursos basándose en datos históricos\n" .
                    "• Comparación de periodos y detección de tendencias\n" .
                    "• Respuestas a preguntas de 'qué pasaría si...' (scenarios)\n\n" .
                    "CONSTANTES DE CAPACIDAD:\n" .
                    "• Horas por técnico al mes: " . HORAS_POR_TECNICO_MES . " HHs\n" .
                    "• Horas efectivas por técnico: " . HORAS_EFECTIVAS_MES . " HHs (85% eficiencia)\n" .
                    "• Días laborables por mes: " . DIAS_LABORABLES_MES;
    
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
            "max_tokens" => 1000
        ])
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        error_log("❌ OpenRouter Error (HTTP $httpCode): $response");
        return null;
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
    
    // Estadísticas mensuales calculadas
    $statsMes = calcularEstadisticasMensuales($stats);
    if ($statsMes) {
        $ctx .= "📈 ESTADÍSTICAS MENSUALES (YA CALCULADAS):\n";
        $ctx .= "• **PROMEDIO de HHs por mes: {$statsMes['promedio_hh']} HHs**\n";
        $ctx .= "• PROMEDIO de OTs por mes: {$statsMes['promedio_ots']} OTs\n";
        $ctx .= "• Mes con MÁS carga: {$statsMes['max_mes']} ({$statsMes['max_hh']} HHs)\n";
        $ctx .= "• Mes con MENOS carga: {$statsMes['min_mes']} ({$statsMes['min_hh']} HHs)\n";
        $ctx .= "• Tendencia anual: {$statsMes['tendencia']}\n\n";
    }
    
    // Capacidad operativa
    $hhPorOT = $stats['general']['total_ots'] > 0 
        ? round($stats['general']['hh_plan'] / $stats['general']['total_ots'], 1) 
        : 0;
    $tecnicosNecesarios = ceil($stats['general']['hh_plan'] / HORAS_EFECTIVAS_MES);
    
    $ctx .= "👥 CAPACIDAD OPERATIVA:\n";
    $ctx .= "• HHs promedio por OT: {$hhPorOT}\n";
    $ctx .= "• Técnicos necesarios para carga total: ~{$tecnicosNecesarios}\n";
    $ctx .= "• Horas efectivas por técnico: " . HORAS_EFECTIVAS_MES . " HHs/mes\n\n";
    
    $ctx .= "🏆 TOP 5 ESPECIALIDADES (por HHs):\n";
    foreach ($stats['especialidades'] as $e) {
        $nombre = $espMap[$e['id_especialidad']] ?? "Esp.{$e['id_especialidad']}";
        $tecnicosEsp = ceil($e['hh'] / HORAS_EFECTIVAS_MES);
        $ctx .= "• {$nombre}: {$e['total']} OTs, {$e['hh']} HHs (~{$tecnicosEsp} técnicos), {$e['en_riesgo']} en riesgo\n";
    }
    $ctx .= "\n";
    
    $ctx .= "📅 DETALLE POR MES (datos brutos):\n";
    foreach ($stats['meses'] as $m) {
        $tecnicosMes = ceil($m['hh'] / HORAS_EFECTIVAS_MES);
        $ctx .= "• " . ucfirst($m['mes_carga']) . ": {$m['total']} OTs, {$m['hh']} HHs (~{$tecnicosMes} técnicos)\n";
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
// 📊 OBTENER ESTADÍSTICAS DE LA BD
// ═══════════════════════════════════════════════════════
function getDatabaseStats($pdo) {
    $stats = [];
    
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
    
    $stmt = $pdo->query("
        SELECT id_especialidad, COUNT(*) as total, ROUND(SUM(total_hh_planificadas),1) as hh,
               SUM(CASE WHEN dias_retraso > 7 THEN 1 ELSE 0 END) as en_riesgo
        FROM ot_resumen_actual WHERE id_especialidad IS NOT NULL
        GROUP BY id_especialidad ORDER BY hh DESC LIMIT 5
    ");
    $stats['especialidades'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->query("
        SELECT id_prevision_sic, nombre_equipo, dias_retraso, total_hh_planificadas, id_especialidad
        FROM ot_resumen_actual
        WHERE dias_retraso > 7 AND ultimo_estado IN ('pendiente','asignada','en_ejecucion')
        ORDER BY dias_retraso DESC LIMIT 5
    ");
    $stats['ots_riesgo'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
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
// 🧮 FUNCIONES DE CÁLCULO
// ═══════════════════════════════════════════════════════

function calcularEstadisticasMensuales($stats) {
    if (empty($stats['meses'])) return null;
    
    $hhPorMes = array_column($stats['meses'], 'hh');
    $otsPorMes = array_column($stats['meses'], 'total');
    
    $promedioHH = round(array_sum($hhPorMes) / count($hhPorMes), 1);
    $promedioOTs = round(array_sum($otsPorMes) / count($otsPorMes), 1);
    
    $maxHH = max($hhPorMes);
    $minHH = min($hhPorMes);
    $maxMes = '';
    $minMes = '';
    
    foreach ($stats['meses'] as $m) {
        if ($m['hh'] == $maxHH) $maxMes = $m['mes_carga'];
        if ($m['hh'] == $minHH) $minMes = $m['mes_carga'];
    }
    
    $primeraMitad = array_slice($hhPorMes, 0, 6);
    $segundaMitad = array_slice($hhPorMes, 6, 6);
    $promedioPrimera = count($primeraMitad) > 0 ? array_sum($primeraMitad) / count($primeraMitad) : 0;
    $promedioSegunda = count($segundaMitad) > 0 ? array_sum($segundaMitad) / count($segundaMitad) : 0;
    
    $tendencia = 'estable';
    if ($promedioPrimera > 0 && $promedioSegunda > $promedioPrimera * 1.1) {
        $tendencia = 'creciente (+' . round((($promedioSegunda/$promedioPrimera)-1)*100, 1) . '%)';
    } elseif ($promedioPrimera > 0 && $promedioSegunda < $promedioPrimera * 0.9) {
        $tendencia = 'decreciente (' . round((($promedioSegunda/$promedioPrimera)-1)*100, 1) . '%)';
    }
    
    return [
        'promedio_hh' => $promedioHH,
        'promedio_ots' => $promedioOTs,
        'max_hh' => $maxHH,
        'max_mes' => $maxMes,
        'min_hh' => $minHH,
        'min_mes' => $minMes,
        'tendencia' => $tendencia,
        'total_meses' => count($hhPorMes)
    ];
}

function calcularTecnicosNecesarios($horas) {
    $tecnicos = ceil($horas / HORAS_EFECTIVAS_MES);
    return [
        'tecnicos' => $tecnicos,
        'horas_por_tecnico' => HORAS_EFECTIVAS_MES,
        'horas_totales' => $horas,
        'formula' => "$horas ÷ " . HORAS_EFECTIVAS_MES . " = $tecnicos"
    ];
}

function calcularCapacidadEquipo($numTecnicos) {
    $horas = $numTecnicos * HORAS_EFECTIVAS_MES;
    return [
        'horas' => $horas,
        'tecnicos' => $numTecnicos,
        'horas_por_tecnico' => HORAS_EFECTIVAS_MES,
        'formula' => "$numTecnicos × " . HORAS_EFECTIVAS_MES . " = $horas"
    ];
}

function proyectarNecesidadMes($mes, $stats) {
    $mesLower = strtolower($mes);
    foreach ($stats['meses'] as $m) {
        if (strtolower($m['mes_carga']) === $mesLower) {
            return calcularTecnicosNecesarios($m['hh']);
        }
    }
    return null;
}

// ═══════════════════════════════════════════════════════
// 🎭 GENERADOR DE RESPUESTAS MOCK
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
               "• **{$stats['general']['total_ots']}** órdenes de trabajo\n" .
               "• **{$stats['general']['hh_plan']}** horas planificadas\n" .
               "• **{$stats['general']['ots_riesgo']}** OTs en riesgo\n\n" .
               "¿Qué te gustaría analizar?";
    }
    
    // 🆕 PROMEDIOS Y ESTADÍSTICAS (DEBE IR ANTES DE "meses")
    if (preg_match('/(promedio|media|mean|estad[ií]stica)/i', $msg)) {
        $statsMes = calcularEstadisticasMensuales($stats);
        
        if (!$statsMes) {
            return "⚠️ No hay datos suficientes para calcular promedios.";
        }
        
        // Promedio de HHs
        if (preg_match('/(hh|hora|horas)/i', $msg)) {
            return "📊 **PROMEDIO DE HORAS HOMBRE POR MES (2026)**\n\n" .
                   "🎯 **{$statsMes['promedio_hh']} HHs/mes**\n\n" .
                   "📈 **Análisis estadístico:**\n" .
                   "• Promedio mensual: **{$statsMes['promedio_hh']} HHs**\n" .
                   "• Mes con mayor carga: **{$statsMes['max_mes']}** ({$statsMes['max_hh']} HHs)\n" .
                   "• Mes con menor carga: **{$statsMes['min_mes']}** ({$statsMes['min_hh']} HHs)\n" .
                   "• Rango de variación: **" . ($statsMes['max_hh'] - $statsMes['min_hh']) . " HHs**\n" .
                   "• Tendencia anual: **{$statsMes['tendencia']}**\n\n" .
                   "💡 **Insight:**\n" .
                   "La carga mensual varía entre {$statsMes['min_hh']} y {$statsMes['max_hh']} HHs. " .
                   "Para dimensionar equipos, usa el promedio ({$statsMes['promedio_hh']} HHs) " .
                   "más un buffer del 20% para picos de demanda.";
        }
        
        // Promedio de OTs
        if (preg_match('/(ots?|[oó]rdenes?)/i', $msg)) {
            return "📊 **PROMEDIO DE OTs POR MES (2026)**\n\n" .
                   "🎯 **{$statsMes['promedio_ots']} OTs/mes**\n\n" .
                   "📈 **Análisis:**\n" .
                   "• Promedio mensual: **{$statsMes['promedio_ots']} OTs**\n" .
                   "• Total anual: **{$stats['general']['total_ots']} OTs**\n" .
                   "• Meses analizados: **{$statsMes['total_meses']}**\n\n" .
                   "💡 **Insight:**\n" .
                   "El hospital mantiene un flujo constante de ~{$statsMes['promedio_ots']} órdenes mensuales.";
        }
        
        // Promedio genérico
        return "📊 **PROMEDIOS MENSUALES 2026**\n\n" .
               "🎯 **Horas Hombre:** {$statsMes['promedio_hh']} HHs/mes\n" .
               "🎯 **Órdenes de Trabajo:** {$statsMes['promedio_ots']} OTs/mes\n\n" .
               "📈 **Análisis:**\n" .
               "• Mes pico: **{$statsMes['max_mes']}** ({$statsMes['max_hh']} HHs)\n" .
               "• Mes valle: **{$statsMes['min_mes']}** ({$statsMes['min_hh']} HHs)\n" .
               "• Tendencia: **{$statsMes['tendencia']}**";
    }
    
    // Capacidad y personal
    if (preg_match('/(cu[aá]ntos? (t[eé]cnicos?|personal|gente|equipo)|necesito|requiero|cubrir|capacidad)/i', $msg)) {
        if (preg_match('/(\d+[\.,]?\d*)\s*(hh|hora|horas)/i', $msg, $matches)) {
            $horas = (float)str_replace(',', '.', $matches[1]);
            $calc = calcularTecnicosNecesarios($horas);
            
            return "👥 **CÁLCULO DE PERSONAL NECESARIO**\n\n" .
                   "Para cubrir **{$horas} HHs** necesitas:\n\n" .
                   "🎯 **{$calc['tecnicos']} técnicos** de tiempo completo\n\n" .
                   "📊 **Detalle del cálculo:**\n" .
                   "• Horas a cubrir: **{$calc['horas_totales']} HHs**\n" .
                   "• Horas efectivas por técnico: **{$calc['horas_por_tecnico']} HHs/mes**\n" .
                   "• Fórmula: {$calc['formula']}\n\n" .
                   "💡 **Consideraciones:**\n" .
                   "• Este cálculo asume **85% de eficiencia**\n" .
                   "• Con buffer del 20%: **" . ceil($calc['tecnicos'] * 1.2) . " técnicos**";
        }
        
        if (preg_match('/(enero|febrero|marzo|abril|mayo|junio|julio|agosto|septiembre|octubre|noviembre|diciembre)/i', $msg, $matches)) {
            $proyeccion = proyectarNecesidadMes($matches[1], $stats);
            if ($proyeccion) {
                return "👥 **PROYECCIÓN DE PERSONAL PARA " . strtoupper($matches[1]) . "**\n\n" .
                       "🎯 Necesitas **{$proyeccion['tecnicos']} técnicos** de tiempo completo\n\n" .
                       "📊 **Detalle:**\n" .
                       "• HHs esperadas: **{$proyeccion['horas_totales']} HHs**\n" .
                       "• Horas efectivas por técnico: **{$proyeccion['horas_por_tecnico']} HHs/mes**\n" .
                       "• Fórmula: {$proyeccion['formula']}";
            }
        }
        
        return "👥 **CAPACIDAD OPERATIVA**\n\n" .
               "• **Horas por técnico al mes:** " . HORAS_POR_TECNICO_MES . " HHs\n" .
               "• **Horas efectivas (85% eficiencia):** " . HORAS_EFECTIVAS_MES . " HHs\n\n" .
               "¿Cuántas horas necesitas cubrir?";
    }
    
    // Resumen ejecutivo
    if (preg_match('/(resumen|ejecutivo|panorama|c[oó]mo vamos|general|estado)/', $msg)) {
        $topEsp = $stats['especialidades'][0] ?? null;
        $topEspName = $topEsp ? ($espMap[$topEsp['id_especialidad']] ?? 'N/A') : 'N/A';
        
        return "📊 **RESUMEN EJECUTIVO - MEDICALOT**\n\n" .
               "🎯 **KPIs Principales:**\n" .
               "• **{$stats['general']['total_ots']}** OTs totales\n" .
               "• **{$stats['general']['hh_plan']}** HHs planificadas\n" .
               "• **{$stats['general']['ots_cerradas']}** OTs cerradas\n" .
               "• **{$stats['general']['ots_riesgo']}** OTs en riesgo ⚠️\n\n" .
               "🏆 **Especialidad con Mayor Carga:**\n" .
               "$topEspName con **{$topEsp['hh']}** HHs\n\n" .
               "💡 **Recomendación:**\n" .
               "Priorizar revisión de las **{$stats['general']['ots_riesgo']}** OTs en riesgo.";
    }
    
    // Prioridades
    if (preg_match('/(priori|cr[ií]tic|urgente|qu[eé] (deber[ií]a|atiendo|hago hoy))/i', $msg)) {
        $resp = "🎯 **TOP 5 OTs PRIORITARIAS PARA ATENDER HOY**\n\n";
        
        foreach ($stats['ots_riesgo'] as $i => $ot) {
            $esp = $espMap[$ot['id_especialidad']] ?? 'N/A';
            $resp .= "**" . ($i+1) . ". ID {$ot['id_prevision_sic']}** - {$ot['nombre_equipo']}\n";
            $resp .= "   ⏰ **{$ot['dias_retraso']} días** | {$ot['total_hh_planificadas']} HHs | $esp\n\n";
        }
        
        return $resp . "💡 **Acción sugerida:**\nContactar a los responsables y reprogramar.";
    }
    
    // Especialidades
    if (preg_match('/(especialidad|materia|[aá]rea|disciplina|electricidad|climatizaci|gasfiter|polivalente)/', $msg)) {
        $resp = "🏆 **RANKING DE ESPECIALIDADES**\n\n";
        
        foreach ($stats['especialidades'] as $i => $e) {
            $nombre = $espMap[$e['id_especialidad']] ?? "Esp.{$e['id_especialidad']}";
            $medal = $i === 0 ? '🥇' : ($i === 1 ? '🥈' : ($i === 2 ? '🥉' : '•'));
            $alert = $e['en_riesgo'] > 0 ? " | ⚠️ **{$e['en_riesgo']} en riesgo**" : "";
            
            $resp .= "$medal **$nombre**\n   {$e['total']} OTs | {$e['hh']} HHs$alert\n\n";
        }
        
        return $resp;
    }
    
    // Riesgo
    if (preg_match('/(riesgo|retraso|atraso|problema|alerta)/', $msg)) {
        $resp = "⚠️ **ANÁLISIS DE OTs EN RIESGO**\n\n";
        $resp .= "**{$stats['general']['ots_riesgo']}** órdenes con +7 días de retraso.\n\n";
        $resp .= "🔥 **Las 5 más críticas:**\n\n";
        
        foreach ($stats['ots_riesgo'] as $ot) {
            $esp = $espMap[$ot['id_especialidad']] ?? 'N/A';
            $nivel = $ot['dias_retraso'] > 30 ? '🚨' : ($ot['dias_retraso'] > 14 ? '⚠️' : '⚡');
            $resp .= "$nivel ID {$ot['id_prevision_sic']} ({$ot['dias_retraso']}d) - {$ot['nombre_equipo']} | $esp\n";
        }
        
        return $resp;
    }
    
    // Meses / tendencias
    if (preg_match('/(mes|enero|febrero|marzo|abril|mayo|junio|julio|agosto|septiembre|octubre|noviembre|diciembre|tendencia|compar)/', $msg)) {
        $statsMes = calcularEstadisticasMensuales($stats);
        
        $resp = "📅 **DISTRIBUCIÓN POR MES**\n\n";
        foreach ($stats['meses'] as $m) {
            $resp .= "**" . ucfirst($m['mes_carga']) . "**: {$m['total']} OTs | {$m['hh']} HHs\n";
        }
        
        if ($statsMes) {
            $resp .= "\n📊 **Estadísticas:**\n";
            $resp .= "• Promedio mensual: **{$statsMes['promedio_hh']} HHs**\n";
            $resp .= "• Mes pico: **{$statsMes['max_mes']}** ({$statsMes['max_hh']} HHs)\n";
            $resp .= "• Tendencia: **{$statsMes['tendencia']}**";
        }
        
        return $resp;
    }
    
    // Eficiencia
    if (preg_match('/(eficiencia|productividad|rendimiento|carga promedio|promedio por)/i', $msg)) {
        $totalHH = $stats['general']['hh_plan'];
        $totalOTs = $stats['general']['total_ots'];
        $hhPorOT = $totalOTs > 0 ? round($totalHH / $totalOTs, 1) : 0;
        
        return "📈 **MÉTRICAS DE EFICIENCIA**\n\n" .
               "• **HHs promedio por OT:** {$hhPorOT} HHs\n" .
               "• **Total de OTs:** {$totalOTs}\n" .
               "• **Total de HHs:** {$totalHH}\n\n" .
               "💡 **Benchmark:** 3-6 HHs por OT es estándar en hospitales.";
    }
    
    // Respuesta genérica
    return "📊 **DATOS DISPONIBLES**\n\n" .
           "• **{$stats['general']['total_ots']}** OTs totales\n" .
           "• **{$stats['general']['hh_plan']}** HHs planificadas\n" .
           "• **{$stats['general']['ots_riesgo']}** OTs en riesgo\n\n" .
           "Puedo analizar: resúmenes, promedios, personal necesario, riesgos, especialidades, tendencias.\n\n" .
           "¿Qué te gustaría explorar?";
}