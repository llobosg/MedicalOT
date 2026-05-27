<?php
/**
 * API Endpoint - Gestión de Asistencia Diaria
 */
header('Content-Type: application/json; charset=utf-8');
define('APP_ENTRY_POINT', true);
require_once __DIR__ . '/../../config.php';

// Verificar sesión
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

try {
    switch ($action) {
        // ==========================================
        // OBTENER LISTA DIARIA (Técnicos con turno hoy)
        // ==========================================
        case 'get_daily_list':
            $fecha = $_GET['fecha'] ?? date('Y-m-d');
            
            // Extraer año, mes, día de la fecha
            $parts = explode('-', $fecha);
            $anio = (int)$parts[0];
            $mes = (int)$parts[1];
            $dia = (int)$parts[2];
            
            // Buscar técnicos que tienen planificación diaria para esta fecha
            // Y unir con sus datos básicos y turno planificado
            $sql = "SELECT 
                        t.id as tecnico_id,
                        t.nombre,
                        t.rut,
                        v.nombre_vertical,
                        tt.nombre as turno_planificado,
                        pd.id_turno,
                        a.estado as estado_real,
                        a.observaciones
                    FROM planificacion_hh_diaria pd
                    JOIN tecnicos t ON pd.id_tecnico = t.id
                    LEFT JOIN verticales v ON t.id_vertical = v.id_vertical
                    LEFT JOIN tipos_turno tt ON pd.id_turno = tt.id
                    LEFT JOIN asistencia_real a ON t.id = a.id_tecnico AND a.fecha = ?
                    WHERE pd.fecha = ?
                    ORDER BY t.nombre ASC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$fecha, $fecha]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'data' => $data]);
            break;

        // ==========================================
        // GUARDAR ASISTENCIA MASIVA
        // ==========================================
        case 'save_daily':
            $input = json_decode(file_get_contents('php://input'), true);
            $registros = $input['registros'] ?? [];
            
            if (empty($registros)) {
                throw new Exception("No hay registros para guardar");
            }
            
            $pdo->beginTransaction();
            
            foreach ($registros as $reg) {
                $tecnico_id = $reg['tecnico_id'];
                $fecha = $reg['fecha'];
                $estado = $reg['estado'];
                $observaciones = $reg['observaciones'] ?? '';
                
                // Insertar o Actualizar (UPSERT)
                $sql = "INSERT INTO asistencia_real (id_tecnico, fecha, estado, observaciones, created_at)
                        VALUES (?, ?, ?, ?, NOW())
                        ON DUPLICATE KEY UPDATE 
                            estado = VALUES(estado),
                            observaciones = VALUES(observaciones),
                            updated_at = NOW()";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$tecnico_id, $fecha, $estado, $observaciones]);
            }
            
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Asistencia guardada correctamente']);
            break;

        default:
            throw new Exception("Acción no válida");
    }

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}