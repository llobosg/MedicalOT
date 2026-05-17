<?php
// public/api/save_incidence.php
header('Content-Type: application/json; charset=utf-8');
while (ob_get_level()) ob_end_clean();

try {
    define('APP_ENTRY_POINT', true);
    require_once __DIR__ . '/../../vendor/autoload.php';
    require_once __DIR__ . '/../../includes/config.php'; // Ajusta ruta si es necesario

    if (session_status() === PHP_SESSION_NONE) session_start();
    
    // Validación básica de sesión
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('No autorizado', 401);
    }

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
        $uploadDir = __DIR__ . '/../../uploads/incidencias/';
        
        // Crear directorio si no existe
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $fileTmpPath = $_FILES['evidence']['tmp_name'];
        $fileName    = basename($_FILES['evidence']['name']);
        $fileExt     = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        // Validar extensión (solo imágenes y PDF)
        $allowedExts = ['jpg', 'jpeg', 'png', 'pdf'];
        if (!in_array($fileExt, $allowedExts)) {
            throw new Exception('Tipo de archivo no permitido. Solo JPG, PNG, PDF.');
        }

        // Generar nombre único para evitar colisiones
        $uniqueName = uniqid('inc_') . '_' . time() . '.' . $fileExt;
        $destination = $uploadDir . $uniqueName;

        if (move_uploaded_file($fileTmpPath, $destination)) {
            // Guardamos la ruta relativa para la BD (ej: uploads/incidencias/inc_xxx.jpg)
            $filePath = 'uploads/incidencias/' . $uniqueName;
        } else {
            throw new Exception('Error al mover el archivo al servidor.');
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
        'message' => 'Incidencia registrada correctamente.',
        'id' => $pdo->lastInsertId(),
        'has_evidence' => !empty($filePath)
    ]);
    exit;

} catch (\Throwable $e) {
    error_log("❌ API Incidencia Error: " . $e->getMessage());
    http_response_code($e->getCode() ?: 500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}