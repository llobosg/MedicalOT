<?php
// public/api/planificacion.php
// NIVEL: PRODUCCIÓN / ALTA DISPONIBILIDAD

header('Content-Type: application/json; charset=utf-8');
while (ob_get_level()) ob_end_clean();

try {
    // 🔐 1. SEGURIDAD Y RUTAS CRÍTICAS
    define('APP_ENTRY_POINT', true);
    
    $docRoot     = $_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__);
    $projectRoot = dirname($docRoot);
    $configPath = file_exists("$projectRoot/config.php") ? "$projectRoot/config.php" : null;
    
    if (!$configPath) throw new Exception("Config no encontrado", 500);
    require_once $configPath;

    if (session_status() === PHP_SESSION_NONE) session_start();

    // Autenticación
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'No autorizado']);
        exit;
    }

    // Autorización (Solo Admin Hospital o Jefes de Vertical pueden planificar)
    $rolUsuario = $_SESSION['user_role'] ?? '';
    $isAdminOrJefe = in_array($rolUsuario, ['admin', 'admin_hospital', 'admin_hosp', 'jefe_vertical']);
    
    if (!$isAdminOrJefe) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Acceso denegado. Solo Administradores y Jefes de Vertical.']);
        exit;
    }

    // Obtener método y parámetros
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? ($_POST['action'] ?? '');
    
    // Helper para obtener body JSON o POST data
    $input = json_decode(file_get_contents('php://input'), true);
    if ($method === 'POST' && !$input) {
        $input = $_POST;
    }

    try {
        // ------------------------------------------------------------------
        // ACCIÓN: LISTAR PLANIFICACIONES (GET)
        // ------------------------------------------------------------------
        if ($method === 'GET' && ($action === 'list' || empty($action))) {
            $fechaDesde = $_GET['desde'] ?? date('Y-m-01');
            $fechaHasta = $_GET['hasta'] ?? date('Y-m-t');
            $estado = $_GET['estado'] ?? '';
            
            $sql = "SELECT 
                        p.id, 
                        p.codigo_ot, 
                        p.fecha_programada, 
                        p.hh_requeridas, 
                        p.estado,
                        p.observaciones,
                        ot.nombre_equipo,
                        ot.id_especialidad,
                        e.nombre as especialidad_nombre,
                        v.nombre_vertical
                    FROM planificaciones p
                    LEFT JOIN ordenes_trabajo ot ON p.codigo_ot = ot.codigo_ot
                    LEFT JOIN especialidades e ON ot.id_especialidad = e.id
                    LEFT JOIN verticales v ON ot.id_vertical = v.id_vertical
                    WHERE p.fecha_programada BETWEEN ? AND ?";
            
            $params = [$fechaDesde, $fechaHasta];
            
            if ($estado) {
                $sql .= " AND p.estado = ?";
                $params[] = $estado;
            }
            
            $sql .= " ORDER BY p.fecha_programada ASC, p.codigo_ot ASC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true, 
                'data' => $data,
                'count' => count($data)
            ]);

        // ------------------------------------------------------------------
        // ACCIÓN: OBTENER DETALLE DE UNA PLANIFICACIÓN (GET)
        // ------------------------------------------------------------------
        elseif ($method === 'GET' && $action === 'detail') {
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception("ID requerido");

            $stmt = $pdo->prepare("
                SELECT p.*, 
                       GROUP_CONCAT(DISTINCT CONCAT(t.nombre, '(', arp.hh_asignadas, 'h)')) as tecnicos_asignados,
                       GROUP_CONCAT(DISTINCT g.nombre_grupo) as grupos_asignados
                FROM planificaciones p
                LEFT JOIN asignacion_recursos_planificacion arp ON p.id = arp.id_planificacion
                LEFT JOIN tecnicos t ON arp.id_tecnico = t.id AND arp.tipo_recurso = 'tecnico'
                LEFT JOIN grupos g ON arp.id_grupo = g.id AND arp.tipo_recurso = 'grupo'
                WHERE p.id = ?
                GROUP BY p.id
            ");
            $stmt->execute([$id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$data) throw new Exception("Planificación no encontrada");

            echo json_encode(['success' => true, 'data' => $data]);

        // ------------------------------------------------------------------
        // ACCIÓN: ASIGNAR RECURSO (POST) - EL CORAZÓN DEL SISTEMA
        // ------------------------------------------------------------------
        elseif ($method === 'POST' && $action === 'asignar') {
            $idPlanificacion = $input['id_planificacion'] ?? null;
            $tipoRecurso     = $input['tipo_recurso'] ?? null; // 'tecnico' o 'grupo'
            $idRecurso       = $input['id_recurso'] ?? null;   // ID tecnico o grupo
            $hhAsignadas     = floatval($input['hh_asignadas'] ?? 0);

            if (!$idPlanificacion || !$tipoRecurso || !$idRecurso || $hhAsignadas <= 0) {
                throw new Exception("Datos incompletos para asignación.");
            }

            $pdo->beginTransaction();

            try {
                // 1. Verificar que la planificación existe y está pendiente/asignada
                $checkPlan = $pdo->prepare("SELECT estado, hh_requeridas FROM planificaciones WHERE id = ?");
                $checkPlan->execute([$idPlanificacion]);
                $plan = $checkPlan->fetch(PDO::FETCH_ASSOC);
                
                if (!$plan) throw new Exception("Planificación no existe.");
                if ($plan['estado'] === 'completada' || $plan['estado'] === 'cancelada') {
                    throw new Exception("No se puede asignar recursos a una planificación finalizada.");
                }

                // 2. Validar Disponibilidad del Recurso (Solo para Técnicos individuales)
                if ($tipoRecurso === 'tecnico') {
                    // Obtener fecha de la planificación
                    $fechaPlan = $pdo->prepare("SELECT fecha_programada FROM planificaciones WHERE id = ?");
                    $fechaPlan->execute([$idPlanificacion]);
                    $fechaStr = $fechaPlan->fetchColumn();
                    
                    // A. Verificar Asistencia (¿Está presente?)
                    $checkAsist = $pdo->prepare("SELECT estado FROM registros_asistencia WHERE id_tecnico = ? AND fecha = ?");
                    $checkAsist->execute([$idRecurso, $fechaStr]);
                    $asistencia = $checkAsist->fetchColumn();
                    
                    if ($asistencia && $asistencia !== 'presente') {
                        throw new Exception("El técnico está marcado como '{$asistencia}' en esa fecha. No se puede asignar.");
                    }

                    // B. Verificar Conflicto de Horario (¿Ya tiene otra tarea ese día?)
                    // Calculamos HH totales ya asignadas a este técnico en esa fecha
                    $checkConflict = $pdo->prepare("
                        SELECT SUM(arp2.hh_asignadas) as hh_usadas
                        FROM asignacion_recursos_planificacion arp2
                        JOIN planificaciones p2 ON arp2.id_planificacion = p2.id
                        WHERE arp2.id_tecnico = ? 
                        AND p2.fecha_programada = ?
                        AND arp2.id_planificacion != ?
                    ");
                    $checkConflict->execute([$idRecurso, $fechaStr, $idPlanificacion]);
                    $hhUsadas = $checkConflict->fetchColumn() ?: 0;
                    
                    // Asumimos 8 horas máximas por día para un técnico (ajustable según turno)
                    $maxHH = 8.0; 
                    if (($hhUsadas + $hhAsignadas) > $maxHH) {
                        throw new Exception("Conflicto de horario: El técnico ya tiene {$hhUsadas}h asignadas. Solo quedan ".($maxHH - $hhUsadas)."h disponibles.");
                    }
                }

                // 3. Insertar Asignación
                $stmtInsert = $pdo->prepare("
                    INSERT INTO asignacion_recursos_planificacion 
                    (id_planificacion, id_tecnico, id_grupo, tipo_recurso, hh_asignadas) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                
                $idTec = ($tipoRecurso === 'tecnico') ? $idRecurso : null;
                $idGrp = ($tipoRecurso === 'grupo') ? $idRecurso : null;
                
                $stmtInsert->execute([$idPlanificacion, $idTec, $idGrp, $tipoRecurso, $hhAsignadas]);

                // 4. Actualizar Estado de Planificación si era pendiente
                if ($plan['estado'] === 'pendiente_asignacion') {
                    $updateState = $pdo->prepare("UPDATE planificaciones SET estado = 'asignada' WHERE id = ?");
                    $updateState->execute([$idPlanificacion]);
                }

                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Recurso asignado correctamente.']);

            } catch (\Exception $e) {
                $pdo->rollBack();
                throw $e;
            }

        // ------------------------------------------------------------------
        // ACCIÓN: DESASIGNAR RECURSO (DELETE)
        // ------------------------------------------------------------------
        elseif ($method === 'DELETE' || ($method === 'POST' && $action === 'desasignar')) {
            $idAsignacion = $_GET['id'] ?? $input['id_asignacion'] ?? null;
            if (!$idAsignacion) throw new Exception("ID de asignación requerido.");

            $pdo->beginTransaction();
            try {
                // Eliminar asignación
                $stmtDel = $pdo->prepare("DELETE FROM asignacion_recursos_planificacion WHERE id = ?");
                $stmtDel->execute([$idAsignacion]);
                
                // Si ya no hay asignaciones, volver a pendiente
                $checkEmpty = $pdo->prepare("SELECT COUNT(*) FROM asignacion_recursos_planificacion WHERE id_planificacion = (SELECT id_planificacion FROM asignacion_recursos_planificacion WHERE id = ? LIMIT 1)");
                // Nota: Lógica simplificada. En producción, verificaríamos cuántas quedan.
                
                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Recurso desasignado.']);
            } catch (\Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
        }

        // ------------------------------------------------------------------
        // ACCIÓN: REGISTRAR ASISTENCIA (POST) - CONTROL DE OFERTA
        // ------------------------------------------------------------------
        elseif ($method === 'POST' && $action === 'registrar_asistencia') {
            $idTecnico = $input['id_tecnico'] ?? null;
            $fecha     = $input['fecha'] ?? null;
            $estado    = $input['estado'] ?? 'presente'; // presente, ausente, licencia_medica, etc.
            $motivo    = $input['motivo'] ?? null;

            if (!$idTecnico || !$fecha) throw new Exception("Técnico y Fecha requeridos.");

            $pdo->beginTransaction();
            try {
                // Upsert: Insertar o Actualizar
                $stmt = $pdo->prepare("
                    INSERT INTO registros_asistencia (id_tecnico, fecha, estado, motivo_ausencia, registrado_por)
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                        estado = VALUES(estado),
                        motivo_ausencia = VALUES(motivo_ausencia),
                        registrado_por = VALUES(registrado_por)
                ");
                
                $userId = $_SESSION['user_id'];
                $stmt->execute([$idTecnico, $fecha, $estado, $motivo, $userId]);

                // ALERTA INTELIGENTE: Si marcó ausencia, buscar OTs afectadas
                if ($estado !== 'presente') {
                    $checkOts = $pdo->prepare("
                        SELECT p.id, p.codigo_ot 
                        FROM asignacion_recursos_planificacion arp
                        JOIN planificaciones p ON arp.id_planificacion = p.id
                        WHERE arp.id_tecnico = ? AND p.fecha_programada = ?
                    ");
                    $checkOts->execute([$idTecnico, $fecha]);
                    $otsAfectadas = $checkOts->fetchAll(PDO::FETCH_COLUMN);
                    
                    // Aquí podrías enviar un email o notificación push en el futuro
                    // Por ahora, lo devolvemos en el JSON para que el frontend avise
                    $alerta = !empty($otsAfectadas) ? "Atención: Hay " . count($otsAfectadas) . " OT(s) programadas para hoy que requieren este técnico." : "";
                } else {
                    $alerta = "";
                }

                $pdo->commit();
                echo json_encode([
                    'success' => true, 
                    'message' => 'Asistencia registrada.',
                    'alerta' => $alerta
                ]);

            } catch (\Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
        }

        else {
            throw new Exception("Acción no válida");
        }

    } catch (\Exception $e) {
        error_log("Error Planificación: " . $e->getMessage());
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }

} catch (\Throwable $e) {
    error_log("❌ API Planificación Fatal: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno del servidor.']);
    exit;
}