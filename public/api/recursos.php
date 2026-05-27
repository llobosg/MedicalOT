<?php
/**
 * API Endpoint - Gestión de Recursos (Técnicos y Grupos)
 * Versión Corregida: Manejo seguro de parámetros SQL
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

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

try {
    switch ($action) {
        // ==========================================
        // LISTAR TÉCNICOS
        // ==========================================
        case 'list_tecnicos':
            // Nota: Si agregaste id_tipo_turno a la tabla tecnicos, inclúyelo aquí.
            // Si NO lo has agregado aún, quita t.id_tipo_turno y tt.nombre as turno_actual
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
            
            try {
                $stmt = $pdo->query($sql);
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'data' => $data]);
            } catch (Exception $e) {
                // Fallback si la columna id_tipo_turno aún no existe en la BD
                if (strpos($e->getMessage(), 'Unknown column') !== false) {
                    $sqlFallback = "SELECT 
                                        t.id, t.rut, t.nombre, t.correo, t.telefono, t.activo,
                                        t.id_especialidad, e.nombre as especialidad_nombre,
                                        t.id_vertical, v.nombre_vertical,
                                        NULL as id_tipo_turno, NULL as turno_actual
                                    FROM tecnicos t
                                    LEFT JOIN especialidades e ON t.id_especialidad = e.id
                                    LEFT JOIN verticales v ON t.id_vertical = v.id_vertical
                                    WHERE t.activo = 1
                                    ORDER BY t.nombre ASC";
                    $stmt = $pdo->query($sqlFallback);
                    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    echo json_encode(['success' => true, 'data' => $data]);
                } else {
                    throw $e;
                }
            }
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
            
            try {
                $stmt = $pdo->query($sql);
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'data' => $data]);
            } catch (Exception $e) {
                // Fallback si tabla o columna no existe
                echo json_encode(['success' => true, 'data' => []]);
            }
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
            
            try {
                $stmt = $pdo->query($sql);
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'data' => $data]);
            } catch (Exception $e) {
                echo json_encode(['success' => true, 'data' => []]);
            }
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
            $id_especialidad = !empty($_POST['id_especialidad']) ? $_POST['id_especialidad'] : null;
            $id_vertical = !empty($_POST['id_vertical']) ? $_POST['id_vertical'] : null;
            $id_tipo_turno = !empty($_POST['id_tipo_turno']) ? $_POST['id_tipo_turno'] : null;

            if (empty($nombre)) throw new Exception("El nombre es obligatorio");

            // Verificar si la columna id_tipo_turno existe en la tabla tecnicos
            $stmtCols = $pdo->query("SHOW COLUMNS FROM tecnicos LIKE 'id_tipo_turno'");
            $hasTurnoCol = $stmtCols->rowCount() > 0;

            if ($action === 'create_tecnico') {
                $fields = ['rut', 'nombre', 'correo', 'telefono', 'id_especialidad', 'id_vertical', 'activo'];
                $values = ['?', '?', '?', '?', '?', '?', '1'];
                $params = [$rut, $nombre, $correo, $telefono, $id_especialidad, $id_vertical];

                if ($hasTurnoCol) {
                    $fields[] = 'id_tipo_turno';
                    $values[] = '?';
                    $params[] = $id_tipo_turno;
                }

                $sql = "INSERT INTO tecnicos (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $values) . ")";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $message = "Técnico creado exitosamente";
            } else {
                // Update
                $sets = [];
                $params = [];

                $sets[] = "rut = ?"; $params[] = $rut;
                $sets[] = "nombre = ?"; $params[] = $nombre;
                $sets[] = "correo = ?"; $params[] = $correo;
                $sets[] = "telefono = ?"; $params[] = $telefono;
                $sets[] = "id_especialidad = ?"; $params[] = $id_especialidad;
                $sets[] = "id_vertical = ?"; $params[] = $id_vertical;

                if ($hasTurnoCol) {
                    $sets[] = "id_tipo_turno = ?";
                    $params[] = $id_tipo_turno;
                }

                $sql = "UPDATE tecnicos SET " . implode(', ', $sets) . " WHERE id = ?";
                $params[] = $id;
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
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
            $id_vertical = !empty($_POST['id_vertical']) ? $_POST['id_vertical'] : null;
            $id_tipo_turno = !empty($_POST['id_tipo_turno']) ? $_POST['id_tipo_turno'] : null;

            if (empty($nombre_grupo)) throw new Exception("El nombre del grupo es obligatorio");

            // Verificar si tabla grupos_trabajo existe
            $stmtCheck = $pdo->query("SHOW TABLES LIKE 'grupos_trabajo'");
            if ($stmtCheck->rowCount() == 0) {
                throw new Exception("Tabla grupos_trabajo no encontrada");
            }

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
            try {
                $stmt = $pdo->prepare("UPDATE grupos_trabajo SET activo = 0 WHERE id = ?");
                $stmt->execute([$id]);
                echo json_encode(['success' => true, 'message' => 'Grupo desactivado']);
            } catch (Exception $e) {
                throw new Exception("Error al desactivar grupo: " . $e->getMessage());
            }
            break;

        default:
            throw new Exception("Acción no válida: $action");
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}