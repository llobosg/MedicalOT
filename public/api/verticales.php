<?php
// public/api/verticales.php
// Desactivar reportes de errores visibles para evitar romper JSON
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');

try {
    // 1. INICIAR SESIÓN (Crítico para evitar 403 por falta de auth)
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // 2. RESOLUCIÓN DE RUTAS ROBUSTA
    $docRoot     = $_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__);
    $projectRoot = dirname($docRoot);
    
    // Buscar config.php en raíz del proyecto (según tu corrección anterior)
    $configPath = file_exists("$projectRoot/config.php") 
                ? "$projectRoot/config.php" 
                : (file_exists("$docRoot/config.php") ? "$docRoot/config.php" : null);
                
    if (!$configPath) {
        throw new Exception("config.php no encontrado en {$projectRoot} o {$docRoot}");
    }

    // 👇 ESTA LÍNEA ES LA CLAVE
    define('APP_ENTRY_POINT', true);
    require_once $configPath;
    
    // 3. VALIDACIÓN DE AUTENTICACIÓN ESTRICTA
    // Si no hay user_id en sesión, devolvemos 401 Unauthorized (no 403)
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'No autorizado. Inicia sesión.']);
        exit;
    }

    // Obtener rol del usuario (ajustar clave según tu login actual)
    // En tu login_unificado.php guardabas $_SESSION['rol'] o similar.
    // Verifica qué clave usas. Asumiremos 'rol' o 'recinto_rol'.
    // Obtener el rol desde la clave CORRECTA: user_role
    $rolUsuario = $_SESSION['user_role'] ?? '';
    
    // Normalizar y validar roles permitidos
    // Aceptamos: 'admin', 'admin_hospital', 'admin_hosp'
    $rolesAdmin = ['admin', 'admin_hospital', 'admin_hosp'];
    $isAdmin = in_array($rolUsuario, $rolesAdmin);

    // Determinar acción
    $action = $_GET['action'] ?? ($_POST['action'] ?? 'list');

  // Debug log en Railway para verificar qué rol llegó
    error_log("🔍 API Verticales - Rol detectado: " . $rolUsuario . " | Es Admin: " . ($isAdmin ? 'SI' : 'NO'));

    if (in_array($action, ['create', 'update', 'delete']) && !$isAdmin) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Acceso denegado. Solo Administradores pueden modificar verticales.']);
        exit;
    }

    // 5. LÓGICA DE NEGOCIO
    try {
        if ($action === 'list') {
            // Todos los usuarios logueados pueden ver la lista
            $stmt = $pdo->query("
                SELECT id_vertical, nombre_vertical, nombre_responsable, contacto_email, cod_especialidad_principal, activo 
                FROM verticales 
                ORDER BY nombre_vertical ASC
            ");
            $verticales = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Log para debuggear si llega aquí
            error_log("✅ API Verticales: Listado exitoso. Registros: " . count($verticales));
            
            echo json_encode(['success' => true, 'verticales' => $verticales]);
            
        } elseif ($action === 'create') {
            $nombre = trim($_POST['nombre_vertical'] ?? '');
            $responsable = trim($_POST['nombre_responsable'] ?? '');
            $esp = trim($_POST['cod_especialidad_principal'] ?? '');
            $email = trim($_POST['contacto_email'] ?? '');
            
            if (empty($nombre)) throw new Exception("El nombre de la vertical es obligatorio.");

            try {
                $stmt = $pdo->prepare("
                    INSERT INTO verticales (nombre_vertical, nombre_responsable, contacto_email, cod_especialidad_principal, activo) 
                    VALUES (?, ?, ?, ?, TRUE)
                ");
                $stmt->execute([$nombre, $responsable, $email, $esp]);
                
                echo json_encode(['success' => true, 'message' => 'Vertical creada correctamente', 'id' => $pdo->lastInsertId()]);
                
            } catch (\PDOException $e) {
                // Detectar si es error de duplicado (Código 23000 o mensaje con "Duplicate entry")
                if ($e->getCode() == 23000 || strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    throw new Exception("Ya existe una Vertical con el nombre '{$nombre}'. Por favor, utiliza otro nombre único.");
                } else {
                    throw $e; // Re-lanzar otros errores de BD
                }
            }
            
        } elseif ($action === 'update') {
            if (!$isAdmin) throw new Exception("Permiso denegado.");
            
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
            
            echo json_encode(['success' => true, 'message' => 'Vertical actualizada']);
            
        } elseif ($action === 'delete') {
            if (!$isAdmin) throw new Exception("Permiso denegado.");
            
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception("ID requerido.");
            
            // Verificar dependencias (opcional pero recomendado)
            $checkUser = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE id_vertical = ?");
            $checkUser->execute([$id]);
            if ($checkUser->fetchColumn() > 0) {
                 throw new Exception("No se puede eliminar: tiene usuarios asignados.");
            }

            $stmt = $pdo->prepare("DELETE FROM verticales WHERE id_vertical=?");
            $stmt->execute([$id]);
            
            echo json_encode(['success' => true, 'message' => 'Vertical eliminada']);
        } else {
            throw new Exception("Acción no válida.");
        }

    } catch (\Exception $e) {
        error_log("❌ Error operación módulo 7: verticales: " . $e->getMessage());
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }

} catch (\Throwable $e) {
    // Captura cualquier error fatal (ej. config.php faltante)
    error_log("💥 API Verticales Fatal: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Error interno del servidor.',
        'debug' => $e->getMessage() // Quitar esto en producción real
    ]);
    exit;
}