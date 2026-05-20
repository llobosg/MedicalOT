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
        } elseif ($method === 'GET' && $action === 'detail') {
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
        } elseif ($method === 'POST' && $action === 'asignar') {
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
                    
                    // Asumimos 8 horas máximas por día para un técnico
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
        } elseif ($method === 'DELETE' || ($method === 'POST' && $action === 'desasignar')) {
            $idAsignacion = $_GET['id'] ?? $input['id_asignacion'] ?? null;
            if (!$idAsignacion) throw new Exception("ID de asignación requerido.");

            $pdo->beginTransaction();
            try {
                $stmtDel = $pdo->prepare("DELETE FROM asignacion_recursos_planificacion WHERE id = ?");
                $stmtDel->execute([$idAsignacion]);
                
                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Recurso desasignado.']);
            } catch (\Exception $e) {
                $pdo->rollBack();
                throw $e;
            }

        // ------------------------------------------------------------------
        // ACCIÓN: REGISTRAR ASISTENCIA (POST)
        // ------------------------------------------------------------------
        } elseif ($method === 'POST' && $action === 'registrar_asistencia') {
            $idTecnico = $input['id_tecnico'] ?? null;
            $fecha     = $input['fecha'] ?? null;
            $estado    = $input['estado'] ?? 'presente';
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

        // ------------------------------------------------------------------
        // ACCIÓN: OBTENER SEMANA PARA CALENDARIO (GET)
        // ------------------------------------------------------------------
        } elseif ($method === 'GET' && $action === 'get_week') {
            $startDate = $_GET['start_date'] ?? date('Y-m-d'); 
            
            // Ajustar al lunes de esa semana
            $dateObj = new DateTime($startDate);
            $dayOfWeek = (int)$dateObj->format('N'); 
            $diffDays = $dayOfWeek - 1;
            $dateObj->modify("-$diffDays days");
            $mondayStart = $dateObj->format('Y-m-d');
            
            $sundayEnd = (new DateTime($mondayStart))->modify('+6 days')->format('Y-m-d');

            // Obtener todas las planificaciones de la semana
            // CORRECCIÓN: JOIN con equipos para obtener el nombre del equipo correctamente
            $stmt = $pdo->prepare("
                SELECT p.*, 
                       eq.nombre as nombre_equipo, 
                       e.nombre as especialidad_nombre,
                       GROUP_CONCAT(DISTINCT CONCAT(t.nombre, '(', arp.hh_asignadas, 'h)')) as tecnicos_asignados_str,
                       COUNT(arp.id) as num_asignaciones
                FROM planificaciones p
                LEFT JOIN ordenes_trabajo ot ON p.codigo_ot = ot.codigo_ot
                LEFT JOIN equipos eq ON ot.id_equipo = eq.id -- <--- NUEVO JOIN CON EQUIPOS
                LEFT JOIN especialidades e ON ot.id_especialidad = e.id
                LEFT JOIN asignacion_recursos_planificacion arp ON p.id = arp.id_planificacion
                LEFT JOIN tecnicos t ON arp.id_tecnico = t.id AND arp.tipo_recurso = 'tecnico'
                WHERE p.fecha_programada BETWEEN ? AND ?
                GROUP BY p.id
                ORDER BY p.fecha_programada ASC, p.id ASC
            ");
            $stmt->execute([$mondayStart, $sundayEnd]);
            $planifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Obtener técnicos disponibles para la sidebar
            $techStmt = $pdo->query("SELECT id, nombre, especialidades.nombre as esp FROM tecnicos LEFT JOIN especialidades ON tecnicos.id_especialidad = especialidades.id ORDER BY nombre");
            $technicians = $techStmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'week_start' => $mondayStart,
                'week_end' => $sundayEnd,
                'data' => $planifications,
                'resources' => $technicians
            ]);

        // ------------------------------------------------------------------
        // ACCIÓN: CAMBIAR FECHA DE PLANIFICACIÓN (POST)
        // ------------------------------------------------------------------
        } elseif ($method === 'POST' && $action === 'change_date') {
            $idPlan = $input['id_planificacion'] ?? null;
            $newDate = $input['new_date'] ?? null;
            
            if (!$idPlan || !$newDate) throw new Exception("Datos incompletos.");

            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("UPDATE planificaciones SET fecha_programada = ?, estado = 'reprogramada' WHERE id = ?");
                $stmt->execute([$newDate, $idPlan]);
                
                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Fecha actualizada correctamente.']);
            } catch (\Exception $e) {
                $pdo->rollBack();
                throw $e;
            }

        // ------------------------------------------------------------------
        // ACCIÓN: REASIGNAR RECURSO (POST)
        // ------------------------------------------------------------------
        } elseif ($method === 'POST' && $action === 'reassign_resource') {
            $idAsignacion = $input['id_asignacion'] ?? null;
            $nuevoIdRecurso = $input['nuevo_id_recurso'] ?? null;
            $tipoRecurso = $input['tipo_recurso'] ?? null;

            if (!$idAsignacion || !$nuevoIdRecurso) throw new Exception("Datos incompletos.");

            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("
                    UPDATE asignacion_recursos_planificacion 
                    SET id_tecnico = CASE WHEN ? = 'tecnico' THEN ? ELSE NULL END,
                        id_grupo = CASE WHEN ? = 'grupo' THEN ? ELSE NULL END,
                        tipo_recurso = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $tipoRecurso, $nuevoIdRecurso,
                    $tipoRecurso, $nuevoIdRecurso,
                    $tipoRecurso,
                    $idAsignacion
                ]);

                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Recurso reasignado correctamente.']);
            } catch (\Exception $e) {
                $pdo->rollBack();
                throw $e;
            }

        } else {
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