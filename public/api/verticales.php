<?php
// public/api/verticales.php
header('Content-Type: application/json; charset=utf-8');
while (ob_get_level()) ob_end_clean();

try {
    // 🔍 1. RESOLUCIÓN INTELIGENTE DE RUTAS (Igual que en convenios.php)
    $docRoot     = $_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__);
    $projectRoot = dirname($docRoot);
    
    // Buscar config.php en includes/ o raíz
    $configPath = file_exists("$projectRoot/config.php") 
                ? "$projectRoot/config.php" 
                : (file_exists("$docRoot/config.php") ? "$docRoot/config.php" : null);
                
    if (!$configPath) {
        throw new Exception("/config.php no encontrado. Verifica estructura.");
    }
    
    require_once $configPath;

    // 🔐 2. INICIAR SESIÓN Y VALIDAR AUTORIZACIÓN
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Verificar si el usuario está logueado
    if (!isset($_SESSION['user_id'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Acceso denegado. Sesión expirada o inválida.']);
        exit;
    }

    $rolUsuario = $_SESSION['rol'] ?? ''; // 'admin_hospital' o 'jefe_vertical'
    $action = $_GET['action'] ?? ($_POST['action'] ?? 'list');

    // 🛡️ 3. CONTROL DE PERMISOS
    // Solo Admin Hospital puede crear, actualizar o eliminar
    if (in_array($action, ['create', 'update', 'delete']) && $rolUsuario !== 'admin_hospital') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Acceso denegado. Solo Administradores del Hospital pueden modificar verticales.']);
        exit;
    }

    try {
        if ($action === 'list') {
            // Todos los roles logueados pueden ver la lista
            $stmt = $pdo->query("
                SELECT id_vertical, nombre_vertical, nombre_responsable, contacto_email, cod_especialidad_principal, activo 
                FROM verticales 
                ORDER BY nombre_vertical ASC
            ");
            $verticales = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'verticales' => $verticales]);
            
        } elseif ($action === 'create') {
            $nombre = trim($_POST['nombre_vertical'] ?? '');
            $responsable = trim($_POST['nombre_responsable'] ?? '');
            $esp = trim($_POST['cod_especialidad_principal'] ?? '');
            $email = trim($_POST['contacto_email'] ?? '');
            
            if (empty($nombre)) throw new Exception("El nombre de la vertical es obligatorio.");

            $stmt = $pdo->prepare("
                INSERT INTO verticales (nombre_vertical, nombre_responsable, contacto_email, cod_especialidad_principal, activo) 
                VALUES (?, ?, ?, ?, TRUE)
            ");
            $stmt->execute([$nombre, $responsable, $email, $esp]);
            
            echo json_encode(['success' => true, 'message' => 'Vertical creada correctamente', 'id' => $pdo->lastInsertId()]);
            
        } elseif ($action === 'update') {
            $id = $_POST['id_vertical'] ?? null;
            $nombre = trim($_POST['nombre_vertical'] ?? '');
            $responsable = trim($_POST['nombre_responsable'] ?? '');
            $esp = trim($_POST['cod_especialidad_principal'] ?? '');
            $email = trim($_POST['contacto_email'] ?? '');
            
            if (!$id || empty($nombre)) throw new Exception("Datos incompletos.");

            $stmt = $pdo->prepare("
                UPDATE verticales 
                SET nombre_vertical=?, nombre_responsable=?, contacto_email=?, cod_especialidad_principal=? 
                WHERE id_vertical=?
            ");
            $stmt->execute([$nombre, $responsable, $email, $esp, $id]);
            
            echo json_encode(['success' => true, 'message' => 'Vertical actualizada correctamente']);
            
        } elseif ($action === 'delete') {
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception("ID requerido.");
            
            // Verificar si hay usuarios vinculados antes de borrar
            $checkUser = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE id_vertical = ?");
            $checkUser->execute([$id]);
            if ($checkUser->fetchColumn() > 0) {
                 throw new Exception("No se puede eliminar esta vertical porque tiene usuarios asignados.");
            }

            $stmt = $pdo->prepare("DELETE FROM verticales WHERE id_vertical=?");
            $stmt->execute([$id]);
            
            echo json_encode(['success' => true, 'message' => 'Vertical eliminada correctamente']);
        }

    } catch (\Exception $e) {
        error_log("Error operación verticales: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }

} catch (\Throwable $e) {
    error_log("❌ API Verticales Fatal: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno del servidor.']);
    exit;
}