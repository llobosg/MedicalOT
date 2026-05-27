<?php
/**
 * API Endpoint - Gestión de Recursos (Técnicos y Grupos)
 */
header('Content-Type: application/json; charset=utf-8');
define('APP_ENTRY_POINT', true);
    
    $docRoot     = $_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__);
    $projectRoot = dirname($docRoot);
    $configPath = file_exists("$projectRoot/config.php") ? "$projectRoot/config.php" : null;
    
    if (!$configPath) {
        throw new Exception("Archivo de configuración no encontrado");
    }
    require_once $configPath;

    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'No autorizado']);
        exit;
    }

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

try {
    switch ($action) {
        // ==========================================
        // LISTAR TÉCNICOS
        // ==========================================
        case 'list_tecnicos':
            $sql = "SELECT 
                        t.id, t.rut, t.nombre, t.correo, t.telefono, t.activo,
                        t.id_especialidad, e.nombre as especialidad_nombre,
                        t.id_vertical, v.nombre_vertical,
                        t.id_tipo_turno, tt.nombre as turno_actual
                    FROM tecnicos t
                    LEFT JOIN especialidades e ON t.id_especialidad = e.id
                    LEFT JOIN verticales v ON t.id_vertical = v.id_vertical
                    LEFT JOIN tipos_turno tt ON t.id_tipo_turno = tt.id
                    WHERE t.activo = 1
                    ORDER BY t.nombre ASC";
            $stmt = $pdo->query($sql);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $data]);
            break;

        // ==========================================
        // LISTAR GRUPOS
        // ==========================================
        case 'list_grupos':
            $sql = "SELECT 
                        g.id, g.nombre_grupo, g.descripcion, g.activo,
                        g.id_vertical, v.nombre_vertical,
                        g.id_tipo_turno, tt.nombre as turno_actual
                    FROM grupos_trabajo g
                    LEFT JOIN verticales v ON g.id_vertical = v.id_vertical
                    LEFT JOIN tipos_turno tt ON g.id_tipo_turno = tt.id
                    WHERE g.activo = 1
                    ORDER BY g.nombre_grupo ASC";
            $stmt = $pdo->query($sql);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $data]);
            break;

        // ==========================================
        // LISTAR RECURSOS CON TURNO ACTIVO (UNIFICADO)
        // ==========================================
        case 'list_con_turno':
            $sql = "SELECT 
                        'tecnico' as tipo_recurso,
                        t.id, t.nombre as nombre_display, t.id_vertical, v.nombre_vertical, t.id_especialidad, e.nombre as especialidad_nombre,
                        t.id_tipo_turno, tt.nombre as turno_nombre
                    FROM tecnicos t
                    LEFT JOIN verticales v ON t.id_vertical = v.id_vertical
                    LEFT JOIN especialidades e ON t.id_especialidad = e.id
                    LEFT JOIN tipos_turno tt ON t.id_tipo_turno = tt.id
                    WHERE t.activo = 1 AND t.id_tipo_turno IS NOT NULL
                    
                    UNION ALL
                    
                    SELECT 
                        'grupo' as tipo_recurso,
                        g.id, g.nombre_grupo as nombre_display, g.id_vertical, v.nombre_vertical, NULL as id_especialidad, NULL as especialidad_nombre,
                        g.id_tipo_turno, tt.nombre as turno_nombre
                    FROM grupos_trabajo g
                    LEFT JOIN verticales v ON g.id_vertical = v.id_vertical
                    LEFT JOIN tipos_turno tt ON g.id_tipo_turno = tt.id
                    WHERE g.activo = 1 AND g.id_tipo_turno IS NOT NULL
                    
                    ORDER BY turno_nombre ASC, nombre_display ASC";
            $stmt = $pdo->query($sql);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $data]);
            break;

        // ==========================================
        // LISTAR TIPOS DE TURNO (Para Selects)
        // ==========================================
        case 'list_tipos_turno':
            $sql = "SELECT id, codigo, nombre, hh_diarias, tipo FROM tipos_turno WHERE activo = 1 ORDER BY codigo ASC";
            $stmt = $pdo->query($sql);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $data]);
            break;

        // ==========================================
        // CREAR / ACTUALIZAR TÉCNICO
        // ==========================================
        case 'create_tecnico':
        case 'update_tecnico':
            $id = $_POST['id'] ?? null;
            $rut = $_POST['rut'] ?? '';
            $nombre = $_POST['nombre'] ?? '';
            $correo = $_POST['correo'] ?? '';
            $telefono = $_POST['telefono'] ?? '';
            $id_especialidad = $_POST['id_especialidad'] ?? null;
            $id_vertical = $_POST['id_vertical'] ?? null;
            $id_tipo_turno = $_POST['id_tipo_turno'] ?? null;

            if (empty($nombre)) throw new Exception("El nombre es obligatorio");

            if ($action === 'create_tecnico') {
                $sql = "INSERT INTO tecnicos (rut, nombre, correo, telefono, id_especialidad, id_vertical, id_tipo_turno, activo) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, 1)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$rut, $nombre, $correo, $telefono, $id_especialidad, $id_vertical, $id_tipo_turno]);
                $message = "Técnico creado exitosamente";
            } else {
                $sql = "UPDATE tecnicos SET rut=?, nombre=?, correo=?, telefono=?, id_especialidad=?, id_vertical=?, id_tipo_turno=? 
                        WHERE id=?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$rut, $nombre, $correo, $telefono, $id_especialidad, $id_vertical, $id_tipo_turno, $id]);
                $message = "Técnico actualizado exitosamente";
            }
            echo json_encode(['success' => true, 'message' => $message]);
            break;

        // ==========================================
        // CREAR / ACTUALIZAR GRUPO
        // ==========================================
        case 'create_grupo':
        case 'update_grupo':
            $id = $_POST['id'] ?? null;
            $nombre_grupo = $_POST['nombre_grupo'] ?? '';
            $descripcion = $_POST['descripcion'] ?? '';
            $id_vertical = $_POST['id_vertical'] ?? null;
            $id_tipo_turno = $_POST['id_tipo_turno'] ?? null;

            if (empty($nombre_grupo)) throw new Exception("El nombre del grupo es obligatorio");

            if ($action === 'create_grupo') {
                $sql = "INSERT INTO grupos_trabajo (nombre_grupo, descripcion, id_vertical, id_tipo_turno, activo) 
                        VALUES (?, ?, ?, ?, 1)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nombre_grupo, $descripcion, $id_vertical, $id_tipo_turno]);
                $message = "Grupo creado exitosamente";
            } else {
                $sql = "UPDATE grupos_trabajo SET nombre_grupo=?, descripcion=?, id_vertical=?, id_tipo_turno=? 
                        WHERE id=?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nombre_grupo, $descripcion, $id_vertical, $id_tipo_turno, $id]);
                $message = "Grupo actualizado exitosamente";
            }
            echo json_encode(['success' => true, 'message' => $message]);
            break;

        // ==========================================
        // ELIMINAR (Desactivar)
        // ==========================================
        case 'delete_tecnico':
            $id = $_GET['id'] ?? $_POST['id'];
            if (!$id) throw new Exception("ID requerido");
            $stmt = $pdo->prepare("UPDATE tecnicos SET activo = 0 WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Técnico desactivado']);
            break;

        case 'delete_grupo':
            $id = $_GET['id'] ?? $_POST['id'];
            if (!$id) throw new Exception("ID requerido");
            $stmt = $pdo->prepare("UPDATE grupos_trabajo SET activo = 0 WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Grupo desactivado']);
            break;

        default:
            throw new Exception("Acción no válida: $action");
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}