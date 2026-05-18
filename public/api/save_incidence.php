<?php
// public/api/save_incidence.php
header('Content-Type: application/json; charset=utf-8');
while (ob_get_level()) ob_end_clean();

try {
    // 🔐 1. PROTECCIÓN DE SESIÓN
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['user_id'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Acceso denegado.']);
        exit;
    }

    // 🔍 2. RESOLUCIÓN DE RUTAS
    $docRoot     = $_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__);
    $projectRoot = dirname($docRoot);
    
    $autoloadPath = file_exists("$projectRoot/vendor/autoload.php") 
                  ? "$projectRoot/vendor/autoload.php" 
                  : (file_exists("$docRoot/vendor/autoload.php") ? "$docRoot/vendor/autoload.php" : null);
                  
    $configPath = file_exists("$projectRoot/config.php") 
                ? "$projectRoot/config.php" 
                : (file_exists("$docRoot/config.php") ? "$docRoot/config.php" : null);
                
    if (!$autoloadPath || !$configPath) {
        throw new Exception("Faltan archivos críticos (vendor/autoload.php o config.php).");
    }
    
    require_once $autoloadPath;
    require_once $configPath;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido', 405);
    }

    $otCode = $_POST['ot_code'] ?? '';
    $type   = $_POST['type'] ?? 'otro';
    $desc   = trim($_POST['description'] ?? '');
    $userId = $_SESSION['user_id'];

    if (empty($otCode) || empty($desc)) {
        throw new Exception('Datos incompletos (OT y Descripción obligatorias)');
    }

    // 1. Manejo de Archivo (Evidencia)
    $filePath = null;
    if (isset($_FILES['evidence']) && $_FILES['evidence']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = $projectRoot . '/public/uploads/incidencias/';
        
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $fileTmpPath = $_FILES['evidence']['tmp_name'];
        $fileName    = basename($_FILES['evidence']['name']);
        $fileExt     = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        $allowedExts = ['jpg', 'jpeg', 'png', 'pdf'];
        if (!in_array($fileExt, $allowedExts)) {
            throw new Exception('Tipo de archivo no permitido.');
        }

        $uniqueName = uniqid('inc_') . '_' . time() . '.' . $fileExt;
        $destination = $uploadDir . $uniqueName;

        if (move_uploaded_file($fileTmpPath, $destination)) {
            $filePath = 'uploads/incidencias/' . $uniqueName;
        } else {
            throw new Exception('Error al mover el archivo.');
        }
    }

    // 2. Insertar en Base de Datos
    $stmt = $pdo->prepare("
        INSERT INTO incidencias (codigo_ot, tipo, descripcion, evidencia_path, usuario_id) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([$otCode, $type, $desc, $filePath, $userId]);

    echo json_encode([
        'success' => true, 
        'message' => 'Incidencia registrada.',
        'id' => $pdo->lastInsertId(),
        'has_evidence' => !empty($filePath)
    ]);
    exit;

} catch (\Throwable $e) {
    error_log("❌ API Save Incidencia Fatal: " . $e->getMessage());
    http_response_code($e->getCode() ?: 500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}