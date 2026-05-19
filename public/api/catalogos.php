<?php
// public/api/catalogos.php
header('Content-Type: application/json; charset=utf-8');
while (ob_get_level()) ob_end_clean();

try {
    // 🔐 Seguridad y Rutas
    if (session_status() === PHP_SESSION_NONE) session_start();

    // 🔐 Configuración de Seguridad y Rutas
    define('APP_ENTRY_POINT', true);
    
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

    // Verificar rol Admin Hospital
    $rolUsuario = $_SESSION['user_role'] ?? '';
    $isAdmin = in_array($rolUsuario, ['admin', 'admin_hospital', 'admin_hosp']);
    
    if (!$isAdmin) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Acceso denegado. Solo Administradores.']);
        exit;
    }

    $action = $_GET['action'] ?? ($_POST['action'] ?? 'list');
    $type   = $_GET['type'] ?? 'especialidad'; // 'especialidad' o 'turno'

    try {
        if ($action === 'list') {
            if ($type === 'especialidad') {
                $stmt = $pdo->query("SELECT id, codigo, nombre FROM especialidades ORDER BY nombre ASC");
                echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            } else {
                $stmt = $pdo->query("SELECT id, codigo, nombre, hh_diarias FROM tipos_turno WHERE activo=1 ORDER BY codigo ASC");
                echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            }
            
        } elseif ($action === 'create') {
            if ($type === 'especialidad') {
                $codigo = trim($_POST['codigo'] ?? '');
                $nombre = trim($_POST['nombre'] ?? '');
                if (empty($codigo) || empty($nombre)) throw new Exception("Campos obligatorios.");
                
                $stmt = $pdo->prepare("INSERT INTO especialidades (codigo, nombre) VALUES (?, ?)");
                $stmt->execute([$codigo, $nombre]);
                
            } else {
                $codigo = trim($_POST['codigo'] ?? '');
                $nombre = trim($_POST['nombre'] ?? '');
                $hh = floatval($_POST['hh_diarias'] ?? 8.00);
                
                $stmt = $pdo->prepare("INSERT INTO tipos_turno (codigo, nombre, hh_diarias, activo) VALUES (?, ?, ?, TRUE)");
                $stmt->execute([$codigo, $nombre, $hh]);
            }
            echo json_encode(['success' => true, 'message' => 'Registro creado correctamente']);

        } elseif ($action === 'update') {
            $id = $_POST['id'] ?? null;
            if (!$id) throw new Exception("ID requerido.");

            if ($type === 'especialidad') {
                $codigo = trim($_POST['codigo'] ?? '');
                $nombre = trim($_POST['nombre'] ?? '');
                
                $stmt = $pdo->prepare("UPDATE especialidades SET codigo=?, nombre=? WHERE id=?");
                $stmt->execute([$codigo, $nombre, $id]);
                
            } else {
                $codigo = trim($_POST['codigo'] ?? '');
                $nombre = trim($_POST['nombre'] ?? '');
                $hh = floatval($_POST['hh_diarias'] ?? 8.00);
                
                $stmt = $pdo->prepare("UPDATE tipos_turno SET codigo=?, nombre=?, hh_diarias=? WHERE id=?");
                $stmt->execute([$codigo, $nombre, $hh, $id]);
            }
            echo json_encode(['success' => true, 'message' => 'Registro actualizado correctamente']);

        } elseif ($action === 'delete') {
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception("ID requerido.");

            if ($type === 'especialidad') {
                // Verificar si está en uso por técnicos o protocolos antes de borrar
                $check = $pdo->prepare("SELECT COUNT(*) FROM tecnicos WHERE id_especialidad = ? UNION ALL SELECT COUNT(*) FROM protocolos WHERE id_especialidad = ?"); 
                // Nota: Ajusta según tus tablas reales. Si protocolos no tiene FK directa, ignora esa parte.
                // Por simplicidad, intentamos borrar. Si falla por FK, capturamos el error.
                try {
                    $stmt = $pdo->prepare("DELETE FROM especialidades WHERE id=?");
                    $stmt->execute([$id]);
                } catch (\PDOException $e) {
                    throw new Exception("No se puede eliminar porque está siendo usada por otros registros.");
                }
            } else {
                // Verificar si hay recursos con este turno activo
                $check = $pdo->prepare("SELECT COUNT(*) FROM asignacion_turnos WHERE id_tipo_turno = ? AND fecha_hasta IS NULL");
                $check->execute([$id]);
                if ($check->fetchColumn() > 0) {
                    throw new Exception("No se puede eliminar porque hay recursos con este turno activo.");
                }
                
                $stmt = $pdo->prepare("DELETE FROM tipos_turno WHERE id=?");
                $stmt->execute([$id]);
            }
            echo json_encode(['success' => true, 'message' => 'Eliminado correctamente']);

        } else {
            throw new Exception("Acción no válida");
        }

    } catch (\Exception $e) {
        error_log("Error Catálogos: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }

} catch (\Throwable $e) {
    error_log("❌ API Catálogos Fatal: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno del servidor.']);
    exit;
}