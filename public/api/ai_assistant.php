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

    // Obtener mensaje del usuario
    $input = json_decode(file_get_contents('php://input'), true);
    $userMessage = trim($input['message'] ?? '');
    
    if (empty($userMessage)) {
        throw new Exception("Mensaje vacío");
    }

    // ═══════════════════════════════════════════════════════
    // 🔍 OBTENER CONTEXTO DE LA BASE DE DATOS
    // ═══════════════════════════════════════════════════════
    $contextText = "";
    $messageLower = strtolower($userMessage);
    
    $espMap = [
        50=>'M-CLIMATIZACIÓN', 51=>'M-ELECTRICIDAD', 52=>'M-GASFITERÍA',
        53=>'M-ELECTRÓNICA', 54=>'M-CARPINTERÍA', 55=>'M-ELECTROMECÁNICA',
        57=>'M-POLIVALENTE'
    ];

    // 1. Overview general (siempre incluir)
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_ots,
            COALESCE(SUM(total_hh_planificadas), 0) as hh_plan,
            SUM(CASE WHEN dias_retraso > 7 AND ultimo_estado IN ('pendiente', 'asignada', 'en_ejecucion') THEN 1 ELSE 0 END) as ots_riesgo,
            SUM(CASE WHEN ultimo_estado IN ('completada', 'cerrada') THEN 1 ELSE 0 END) as ots_cerradas,
            SUM(CASE WHEN veces_reprogramadas > 0 THEN 1 ELSE 0 END) as ots_reprogramadas
        FROM ot_resumen_actual
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $contextText .= "📊 ESTADÍSTICAS GENERALES:\n";
    $contextText .= "- Total de OTs: {$stats['total_ots']}\n";
    $contextText .= "- HHs Planificadas: {$stats['hh_plan']}\n";
    $contextText .= "- OTs Cerradas: {$stats['ots_cerradas']}\n";
    $contextText .= "- OTs en Riesgo (+7 días retraso): {$stats['ots_riesgo']}\n";
    $contextText .= "- OTs Reprogramadas: {$stats['ots_reprogramadas']}\n\n";

    // 2. Si pregunta sobre especialidades
    if (preg_match('/(especialidad|materia|disciplina|[aá]rea|electricidad|climatizaci|gasfiter|polivalente)/i', $messageLower)) {
        $stmt = $pdo->query("
            SELECT 
                id_especialidad,
                COUNT(*) as total_ots,
                ROUND(SUM(total_hh_planificadas), 1) as hh_total,
                SUM(CASE WHEN dias_retraso > 7 THEN 1 ELSE 0 END) as en_riesgo
            FROM ot_resumen_actual
            WHERE id_especialidad IS NOT NULL
            GROUP BY id_especialidad
            ORDER BY hh_total DESC
        ");
        $esps = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $contextText .= "🏆 ESPECIALIDADES (por HHs planificadas):\n";
        foreach ($esps as $e) {
            $nombre = $espMap[$e['id_especialidad']] ?? "Esp. {$e['id_especialidad']}";
            $contextText .= "- {$nombre}: {$e['total_ots']} OTs, {$e['hh_total']} HHs, {$e['en_riesgo']} en riesgo\n";
        }
        $contextText .= "\n";
    }

    // 3. Si pregunta sobre riesgo, retraso o problemas
    if (preg_match('/(riesgo|retraso|atraso|problema|cr[ií]tico|alerta|priori)/i', $messageLower)) {
        $stmt = $pdo->query("
            SELECT 
                id_prevision_sic, codigo_ot, nombre_equipo,
                dias_retraso, total_hh_planificadas, id_especialidad
            FROM ot_resumen_actual
            WHERE dias_retraso > 7 
            AND ultimo_estado IN ('pendiente', 'asignada', 'en_ejecucion')
            ORDER BY dias_retraso DESC
            LIMIT 10
        ");
        $ots = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $contextText .= "⚠️ TOP 10 OTs EN MAYOR RIESGO:\n";
        foreach ($ots as $ot) {
            $esp = $espMap[$ot['id_especialidad']] ?? 'N/A';
            $contextText .= "- ID {$ot['id_prevision_sic']} ({$ot['codigo_ot']}): {$ot['nombre_equipo']} [{$esp}] - {$ot['dias_retraso']} días retraso, {$ot['total_hh_planificadas']} HHs\n";
        }
        $contextText .= "\n";
    }

    // 4. Si pregunta sobre mes o comparativa
    if (preg_match('/(enero|febrero|marzo|abril|mayo|junio|julio|agosto|septiembre|octubre|noviembre|diciembre|mes|compara|tendencia)/i', $messageLower)) {
        $stmt = $pdo->query("
            SELECT 
                mes_carga,
                COUNT(*) as total_ots,
                ROUND(SUM(total_hh_planificadas), 1) as hh_total,
                SUM(CASE WHEN dias_retraso > 7 THEN 1 ELSE 0 END) as en_riesgo
            FROM ot_resumen_actual
            WHERE mes_carga IS NOT NULL
            GROUP BY mes_carga
            ORDER BY FIELD(mes_carga, 'enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre')
        ");
        $meses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $contextText .= "📅 DISTRIBUCIÓN POR MES:\n";
        foreach ($meses as $m) {
            $contextText .= "- {$m['mes_carga']}: {$m['total_ots']} OTs, {$m['hh_total']} HHs, {$m['en_riesgo']} en riesgo\n";
        }
        $contextText .= "\n";
    }

    // 5. Si pregunta sobre reprogramaciones
    if (preg_match('/(reprograma|cambio|modifi)/i', $messageLower)) {
        $stmt = $pdo->query("
            SELECT 
                id_prevision_sic, codigo_ot, nombre_equipo,
                veces_reprogramadas, dias_retraso
            FROM ot_resumen_actual
            WHERE veces_reprogramadas > 0
            ORDER BY veces_reprogramadas DESC
            LIMIT 10
        ");
        $reprogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $contextText .= "🔄 TOP 10 OTs MÁS REPROGRAMADAS:\n";
        foreach ($reprogs as $r) {
            $contextText .= "- ID {$r['id_prevision_sic']} ({$r['codigo_ot']}): {$r['nombre_equipo']} - Reprogramada {$r['veces_reprogramadas']} veces, {$r['dias_retraso']} días retraso\n";
        }
        $contextText .= "\n";
    }

    // ═══════════════════════════════════════════════════════
    // 🔐 LLAMAR A OPENAI
    // ═══════════════════════════════════════════════════════
    $apiKey = getenv('OPENAI_API_KEY');
    if (!$apiKey) {
        throw new Exception("API Key de OpenAI no configurada en Railway (variable OPENAI_API_KEY)");
    }

    $systemPrompt = <<<PROMPT
Eres el asistente experto de MedicalOT, el sistema de gestión de mantenimiento del Hospital de Antofagasta.

REGLAS ESTRICTAS:
1. Responde ÚNICAMENTE basándote en los datos proporcionados en el contexto.
2. Si NO tienes información para responder, di claramente: "No tengo esa información en los datos disponibles actualmente."
3. Sé conciso, profesional y orientado a la acción.
4. Usa formato estructurado: títulos en MAYÚSCULAS, bullets (•), negritas con **texto**.
5. SIEMPRE termina con una recomendación accionable o pregunta de seguimiento.
6. NO inventes datos, cifras ni IDs.
7. Si el usuario saluda, preséntate brevemente y pregunta qué necesita analizar.
8. Usa emojis moderadamente para hacer la lectura más amigable.

TU PERSONALIDAD:
- Profesional pero cercano
- Orientado a datos y decisiones
- Proactivo en sugerir análisis
- Consciente del impacto en pacientes

EJEMPLOS DE BUENAS RESPUESTAS:
📊 RESUMEN EJECUTIVO
• Total de OTs: X
• Alertas principales: Y

💡 RECOMENDACIÓN
Revisar las Z OTs con más de 30 días de retraso en M-ELECTRICIDAD.
PROMPT;

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "https://api.openai.com/v1/chat/completions",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "Authorization: Bearer $apiKey"
        ],
        CURLOPT_POSTFIELDS => json_encode([
            "model" => "gpt-4o-mini",
            "messages" => [
                ["role" => "system", "content" => $systemPrompt],
                ["role" => "user", "content" => "Contexto de datos actuales:\n$contextText\n\nPregunta del usuario: $userMessage"]
            ],
            "temperature" => 0.7,
            "max_tokens" => 800
        ])
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception("Error OpenAI (HTTP $httpCode): $response");
    }

    $result = json_decode($response, true);
    $aiResponse = $result['choices'][0]['message']['content'] ?? "Lo siento, no pude generar una respuesta.";

    echo json_encode([
        'success' => true,
        'message' => $aiResponse,
        'usage' => $result['usage'] ?? null
    ]);

} catch (\Throwable $e) {
    error_log("❌ Error AI Assistant: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Error: ' . $e->getMessage()
    ]);
    exit;
}