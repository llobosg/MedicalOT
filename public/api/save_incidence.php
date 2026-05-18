<?php
// public/api/save_incidence.php
header('Content-Type: application/json; charset=utf-8');
while (ob_get_level()) ob_end_clean();

try {
    // 1. INICIAR SESIÓN Y VALIDAR AUTORIZACIÓN (Crítico para evitar 403)
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Verificar si el usuario está logueado (ajustar clave según tu login)
    // En tu dashboard usas $_SESSION['id_admin'] o $_SESSION['user_id']
    if (!isset($_SESSION['id_admin']) && !isset($_SESSION['user_id'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Acceso denegado. Sesión expirada o inválida.']);
        exit;
    }

    // 2. RESOLUCIÓN DE RUTAS SEGURA (Igual que en convenios.php)
    $docRoot     = $_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__);
    $projectRoot = dirname($docRoot);
    
    // Buscar config.php en la raíz del proyecto
    $configPath = file_exists("$projectRoot/config.php") 
                ? "$projectRoot/config.php" 
                : (file_exists("$docRoot/config.php") ? "$docRoot/config.php" : null);
                
    if (!$configPath) {
        throw new Exception("config.php no encontrado en {$projectRoot} o {$docRoot}");
    }
    
    define('APP_ENTRY_POINT', true);
    require_once $configPath;


    // Validar método POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido', 405);
    }

    // 3. EXTRAER DATOS
    $otCode = $_POST['ot_code'] ?? '';
    $type   = $_POST['type'] ?? 'otro';
    $desc   = trim($_POST['description'] ?? '');
    
    // Obtener ID de usuario activo
    $userId = $_SESSION['id_admin'] ?? $_SESSION['user_id'] ?? null;

    if (empty($otCode) || empty($desc)) {
        throw new Exception('Datos incompletos (OT y Descripción obligatorias)');
    }

    // 4. MANEJO DE ARCHIVO (EVIDENCIA)
    $filePath = null;
    if (isset($_FILES['evidence']) && $_FILES['evidence']['error'] === UPLOAD_ERR_OK) {
        // Definir carpeta de uploads dentro de public para acceso web directo
        $uploadDir = $projectRoot . '/public/uploads/incidencias/';
        
        // Crear directorio si no existe
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $fileTmpPath = $_FILES['evidence']['tmp_name'];
        $fileName    = basename($_FILES['evidence']['name']);
        $fileExt     = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        // Validar extensiones permitidas
        $allowedExts = ['jpg', 'jpeg', 'png', 'pdf'];
        if (!in_array($fileExt, $allowedExts)) {
            throw new Exception('Tipo de archivo no permitido. Solo JPG, PNG, PDF.');
        }

        // Generar nombre único para evitar colisiones
        $uniqueName = uniqid('inc_') . '_' . time() . '.' . $fileExt;
        $destination = $uploadDir . $uniqueName;

        if (move_uploaded_file($fileTmpPath, $destination)) {
            // Guardamos la ruta relativa web-accessible (ej: uploads/incidencias/inc_xxx.jpg)
            $filePath = 'uploads/incidencias/' . $uniqueName;
        } else {
            throw new Exception('Error al mover el archivo al servidor.');
        }
    }

    // 5. INSERTAR EN BASE DE DATOS
    $stmt = $pdo->prepare("
        INSERT INTO incidencias (codigo_ot, tipo, descripcion, evidencia_path, usuario_id) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([$otCode, $type, $desc, $filePath, $userId]);

    echo json_encode([
        'success' => true, 
        'message' => 'Incidencia registrada correctamente.',
        'id' => $pdo->lastInsertId(),
        'has_evidence' => !empty($filePath)
    ]);
    exit;

} catch (\Throwable $e) {
    // Log detallado para debugging en Railway
    error_log("❌ API Save Incidencia Fatal: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}