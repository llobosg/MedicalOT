<?php
// public/api/recursos.php
header('Content-Type: application/json; charset=utf-8');
while (ob_get_level()) ob_end_clean();

try {
    // 🔐 Seguridad y Rutas
    if (session_status() === PHP_SESSION_NONE) session_start();
    
    $docRoot     = $_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__);
    $projectRoot = dirname($docRoot);
    $configPath = file_exists("$projectRoot/config.php") ? "$projectRoot/config.php" : null;
    if (!$configPath) throw new Exception("Config no encontrado");
    require_once $configPath;

    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'No autorizado']);
        exit;
    }

    $action = $_GET['action'] ?? ($_POST['action'] ?? 'list_tecnicos');
    $type   = $_GET['type'] ?? ''; // 'tecnico' o 'grupo'

    try {
        // --- LISTAR TÉCNICOS ---
        if ($action === 'list_tecnicos') {
            $stmt = $pdo->query("
                SELECT t.*, e.nombre as especialidad_nombre, tt.nombre as turno_actual
                FROM tecnicos t
                LEFT JOIN especialidades e ON t.id_especialidad = e.id
                LEFT JOIN asignacion_turnos at ON t.id = at.id_tecnico AND at.fecha_hasta IS NULL
                LEFT JOIN tipos_turno tt ON at.id_tipo_turno = tt.id
                ORDER BY t.nombre ASC
            ");
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            
        // --- LISTAR GRUPOS ---
        } elseif ($action === 'list_grupos') {
            $stmt = $pdo->query("
                SELECT g.*, v.nombre_vertical, tt.nombre as turno_actual
                FROM grupos g
                LEFT JOIN verticales v ON g.id_vertical = v.id_vertical
                LEFT JOIN asignacion_turnos at ON g.id = at.id_grupo AND at.fecha_hasta IS NULL
                LEFT JOIN tipos_turno tt ON at.id_tipo_turno = tt.id
                ORDER BY g.nombre_grupo ASC
            ");
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);

        // --- LISTAR TIPOS DE TURNO ---
        } elseif ($action === 'list_tipos_turno') {
            $stmt = $pdo->query("SELECT * FROM tipos_turno WHERE activo = 1 ORDER BY codigo ASC");
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);

        // --- CREAR TÉCNICO ---
        } elseif ($action === 'create_tecnico') {
            $rut = trim($_POST['rut'] ?? '');
            $nombre = trim($_POST['nombre'] ?? '');
            $correo = trim($_POST['correo'] ?? '');
            $telefono = trim($_POST['telefono'] ?? '');
            $id_esp = $_POST['id_especialidad'] ?? null;
            $id_turno = $_POST['id_tipo_turno'] ?? null;

            if (empty($rut) || empty($nombre)) throw new Exception("RUT y Nombre son obligatorios.");

            // Iniciar transacción
            $pdo->beginTransaction();

            // 1. Insertar Técnico
            $stmt = $pdo->prepare("INSERT INTO tecnicos (rut, nombre, correo, telefono, id_especialidad, activo) VALUES (?, ?, ?, ?, ?, TRUE)");
            $stmt->execute([$rut, $nombre, $correo, $telefono, $id_esp]);
            $id_tecnico = $pdo->lastInsertId();

            // 2. Asignar Turno si se proporcionó
            if ($id_turno) {
                $stmt = $pdo->prepare("INSERT INTO asignacion_turnos (id_tecnico, id_tipo_turno, fecha_desde) VALUES (?, ?, CURDATE())");
                $stmt->execute([$id_tecnico, $id_turno]);
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Técnico creado correctamente']);

        // --- ACTUALIZAR TÉCNICO ---
        } elseif ($action === 'update_tecnico') {
            $id = $_POST['id'] ?? null;
            $rut = trim($_POST['rut'] ?? '');
            $nombre = trim($_POST['nombre'] ?? '');
            $correo = trim($_POST['correo'] ?? '');
            $telefono = trim($_POST['telefono'] ?? '');
            $id_esp = $_POST['id_especialidad'] ?? null;
            $id_turno = $_POST['id_tipo_turno'] ?? null;

            if (!$id) throw new Exception("ID requerido.");

            $pdo->beginTransaction();

            // 1. Actualizar datos básicos
            $stmt = $pdo->prepare("UPDATE tecnicos SET rut=?, nombre=?, correo=?, telefono=?, id_especialidad=? WHERE id=?");
            $stmt->execute([$rut, $nombre, $correo, $telefono, $id_esp, $id]);

            // 2. Manejar cambio de turno (Cerrar anterior y abrir nuevo si cambió)
            if ($id_turno) {
                // Cerrar turno vigente anterior
                $stmtClose = $pdo->prepare("UPDATE asignacion_turnos SET fecha_hasta = CURDATE() WHERE id_tecnico = ? AND fecha_hasta IS NULL");
                $stmtClose->execute([$id]);
                
                // Abrir nuevo turno
                $stmtOpen = $pdo->prepare("INSERT INTO asignacion_turnos (id_tecnico, id_tipo_turno, fecha_desde) VALUES (?, ?, CURDATE())");
                $stmtOpen->execute([$id, $id_turno]);
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Técnico actualizado']);

        // --- ELIMINAR TÉCNICO ---
        } elseif ($action === 'delete_tecnico') {
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception("ID requerido.");
            
            $stmt = $pdo->prepare("DELETE FROM tecnicos WHERE id=?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Técnico eliminado']);

        // --- CREAR GRUPO ---
        } elseif ($action === 'create_grupo') {
            $nombre = trim($_POST['nombre_grupo'] ?? '');
            $id_vert = $_POST['id_vertical'] ?? null;
            $desc = trim($_POST['descripcion'] ?? '');
            $id_turno = $_POST['id_tipo_turno'] ?? null;

            if (empty($nombre)) throw new Exception("Nombre del grupo obligatorio.");

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO grupos (nombre_grupo, id_vertical, descripcion, activo) VALUES (?, ?, ?, TRUE)");
            $stmt->execute([$nombre, $id_vert, $desc]);
            $id_grupo = $pdo->lastInsertId();

            if ($id_turno) {
                $stmt = $pdo->prepare("INSERT INTO asignacion_turnos (id_grupo, id_tipo_turno, fecha_desde) VALUES (?, ?, CURDATE())");
                $stmt->execute([$id_grupo, $id_turno]);
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Grupo creado']);

        // --- ACTUALIZAR GRUPO ---
        } elseif ($action === 'update_grupo') {
            $id = $_POST['id'] ?? null;
            $nombre = trim($_POST['nombre_grupo'] ?? '');
            $id_vert = $_POST['id_vertical'] ?? null;
            $desc = trim($_POST['descripcion'] ?? '');
            $id_turno = $_POST['id_tipo_turno'] ?? null;

            if (!$id) throw new Exception("ID requerido.");

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("UPDATE grupos SET nombre_grupo=?, id_vertical=?, descripcion=? WHERE id=?");
            $stmt->execute([$nombre, $id_vert, $desc, $id]);

            if ($id_turno) {
                $stmtClose = $pdo->prepare("UPDATE asignacion_turnos SET fecha_hasta = CURDATE() WHERE id_grupo = ? AND fecha_hasta IS NULL");
                $stmtClose->execute([$id]);
                $stmtOpen = $pdo->prepare("INSERT INTO asignacion_turnos (id_grupo, id_tipo_turno, fecha_desde) VALUES (?, ?, CURDATE())");
                $stmtOpen->execute([$id, $id_turno]);
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Grupo actualizado']);

        // --- ELIMINAR GRUPO ---
        } elseif ($action === 'delete_grupo') {
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception("ID requerido.");
            
            $stmt = $pdo->prepare("DELETE FROM grupos WHERE id=?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Grupo eliminado']);

        } else {
            throw new Exception("Acción no válida");
        }

    } catch (\Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log("Error Recursos: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }

} catch (\Throwable $e) {
    error_log("❌ API Recursos Fatal: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno del servidor.']);
    exit;
}