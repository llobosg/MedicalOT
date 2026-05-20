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
    $userMessage = $input['message'] ?? '';

    if (empty($userMessage)) {
        throw new Exception("Mensaje vacío");
    }

    // 🔍 PASO 1: OBTENER CONTEXTO DE LA BASE DE DATOS (Ejemplo básico)
    // Aquí podrías hacer búsquedas inteligentes. Por ejemplo, si el usuario menciona una fecha,
    // traes las planificaciones de esa semana.
    
    $contextData = [];
    
    // Ejemplo: Si pregunta sobre "esta semana", traemos las planificaciones actuales
    if (stripos($userMessage, 'semana') !== false || stripos($userMessage, 'planificacion') !== false) {
        $stmt = $pdo->query("
            SELECT p.codigo_ot, p.fecha_programada, t.nombre as tecnico, e.nombre as especialidad 
            FROM planificaciones p
            LEFT JOIN asignacion_recursos_planificacion arp ON p.id = arp.id_planificacion
            LEFT JOIN tecnicos t ON arp.id_tecnico = t.id
            LEFT JOIN ordenes_trabajo ot ON p.codigo_ot = ot.codigo_ot
            LEFT JOIN especialidades e ON ot.id_especialidad = e.id
            WHERE p.fecha_programada >= CURDATE() AND p.fecha_programada <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
            LIMIT 10
        ");
        $contextData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Convertir datos a texto legible para la IA
    $contextText = "";
    if (!empty($contextData)) {
        foreach ($contextData as $row) {
            $contextText .= "- OT: {$row['codigo_ot']}, Fecha: {$row['fecha_programada']}, Técnico: {$row['tecnico'] ?? 'Sin asignar'}, Especialidad: {$row['especialidad'] ?? 'N/A'}\n";
        }
    } else {
        $contextText = "No hay datos específicos encontrados para el contexto actual.";
    }

    // 🔐 PASO 2: LLAMAR A OPENAI
    $apiKey = getenv('OPENAI_API_KEY');
    if (!$apiKey) throw new Exception("API Key de OpenAI no configurada en Railway.");

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "https://api.openai.com/v1/chat/completions",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "Authorization: Bearer $apiKey"
        ],
        CURLOPT_POSTFIELDS => json_encode([
            "model" => "gpt-4o-mini", // Modelo rápido y barato
            "messages" => [
                ["role" => "system", "content" => "Eres un asistente experto en gestión de mantenimiento hospitalario (MedicalOT). Tu tarea es responder preguntas basándote ÚNICAMENTE en los datos proporcionados en el contexto. Si no tienes información, di que no la tienes. Sé conciso y profesional."],
                ["role" => "user", "content" => "Contexto de datos:\n$contextText\n\nPregunta del usuario: $userMessage"]
            ],
            "temperature" => 0.7
        ])
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception("Error al comunicarse con OpenAI: $response");
    }

    $result = json_decode($response, true);
    $aiResponse = $result['choices'][0]['message']['content'] ?? "Lo siento, no pude generar una respuesta.";

    echo json_encode([
        'success' => true,
        'message' => $aiResponse
    ]);

} catch (\Throwable $e) {
    error_log("❌ Error AI Assistant: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno: ' . $e->getMessage()]);
    exit;
}