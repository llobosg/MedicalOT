<?php
// public/tools/init_kpis.php
// HERRAMIENTA DE INICIALIZACIÓN PARA DEMO EMILY
// EJECUTAR UNA SOLA VEZ Y LUEGO BORRAR ESTE ARCHIVO

header('Content-Type: text/html; charset=utf-8');

try {
    define('APP_ENTRY_POINT', true);
    
    $docRoot     = $_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__);
    $projectRoot = dirname($docRoot);
    $configPath = file_exists("$projectRoot/config.php") ? "$projectRoot/config.php" : null;
    if (!$configPath) throw new Exception("Config no encontrado");
    require_once $configPath;

    echo "<h1>🚀 Inicializando KPIs para Demo Emily...</h1>";
    echo "<p>Conectando con la base de datos...</p>";

    // 1. Verificar si ya hay datos para no duplicar en pruebas
    $count = $pdo->query("SELECT COUNT(*) FROM ot_resumen_actual")->fetchColumn();
    if ($count > 0) {
        echo "<p style='color:orange;'>⚠️ Ya existen {$count} registros en ot_resumen_actual. Limpieza previa recomendada si quieres reiniciar.</p>";
        // Opcional: Borrar todo para empezar limpio
        // $pdo->exec("TRUNCATE TABLE ot_resumen_actual");
        // $pdo->exec("TRUNCATE TABLE ot_historico");
    }

    // 2. Obtener todas las OTs de ordenes_trabajo que tengan id_prevision_sic
    echo "<p>Buscando OTs en ordenes_trabajo...</p>";
    $stmt = $pdo->query("
        SELECT 
            ot.codigo_ot, 
            ot.id_prevision_sic, 
            ot.fecha_programada,
            ot.id_vertical,
            ot.id_especialidad,
            ot.id_equipo,
            cm.nombre_equipamiento,
            cm.horas_estimadas as hh_planificadas,
            cm.cod_especialidad,
            cm.tipo
        FROM ordenes_trabajo ot
        LEFT JOIN cat_mantenimientos_master cm ON ot.id_prevision_sic = cm.id_prevision_sic
        WHERE ot.id_prevision_sic IS NOT NULL
        LIMIT 500 -- Limitamos a 500 para la demo para que sea rápido
    ");
    
    $ots = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<p>Encontradas <strong>" . count($ots) . "</strong> OTs para procesar.</p>";

    $inserted = 0;
    $historicoCount = 0;

    $pdo->beginTransaction();

    foreach ($ots as $ot) {
        // --- SIMULACIÓN DE HISTORIAL ---
        // Creamos un historial ficticio basado en la fecha programada
        
        $fechaProgramada = new DateTime($ot['fecha_programada']);
        
        // Determinar estado simulado basado en probabilidad
        $rand = rand(1, 100);
        $estadoFinal = 'pendiente';
        $hhReales = 0;
        $diasRetraso = 0;
        $vecesReprogramadas = 0;

        if ($rand <= 60) {
            // 60% Completadas
            $estadoFinal = 'completada';
            $hhReales = $ot['hh_planificadas'] * (0.9 + (rand(0, 20) / 100)); // Entre 90% y 110% de las horas plan
            
            // Simular retraso leve o puntual
            if (rand(1, 10) > 7) {
                $diasRetraso = rand(1, 5); // Retrasada 1-5 días
            } else {
                $diasRetraso = 0; // A tiempo
            }

        } elseif ($rand <= 80) {
            // 20% En Ejecución
            $estadoFinal = 'en_ejecucion';
            $hhReales = $ot['hh_planificadas'] * 0.5; // Mitad hecha
            $diasRetraso = 0;

        } elseif ($rand <= 90) {
            // 10% Reprogramadas
            $estadoFinal = 'reprogramada';
            $vecesReprogramadas = 1;
            $hhReales = 0;
            $diasRetraso = 0;

        } else {
            // 10% No Realizada / Cancelada
            $estadoFinal = 'no_realizada';
            $hhReales = 0;
            $diasRetraso = 0;
        }

        // --- INSERTAR EN HISTÓRICO (Bitácora) ---
        // Insertamos al menos un registro histórico que represente el "último reporte"
        $historicoStmt = $pdo->prepare("
            INSERT INTO ot_historico (
                codigo_ot, id_prevision_sic, fecha_carga, fuente, 
                fecha_programada, estado, hh_planificadas, hh_reales, 
                id_vertical, id_especialidad, id_equipo, nombre_equipo, nombre_protocolo
            ) VALUES (?, ?, NOW(), 'MANTENCION', ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $historicoStmt->execute([
            $ot['codigo_ot'],
            $ot['id_prevision_sic'],
            $ot['fecha_programada'],
            $estadoFinal,
            $ot['hh_planificadas'],
            $hhReales,
            $ot['id_vertical'],
            $ot['id_especialidad'],
            $ot['id_equipo'],
            $ot['nombre_equipamiento'] ?? 'Equipo Genérico',
            $ot['cod_especialidad'] ?? '' // Usamos el código como placeholder de protocolo si no tenemos nombre
        ]);
        $historicoCount++;

        // --- ACTUALIZAR/INSERTAR RESUMEN ACTUAL ---
        $resumenStmt = $pdo->prepare("
            INSERT INTO ot_resumen_actual (
                codigo_ot, id_prevision_sic, 
                primera_fecha_programada, primera_carga,
                ultima_fecha_programada, ultimo_estado, ultima_carga,
                total_hh_planificadas, total_hh_reales_acumuladas,
                veces_reprogramadas, dias_retraso,
                id_vertical, id_especialidad, nombre_equipo, tipo_mantenimiento
            ) VALUES (?, ?, ?, NOW(), ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                ultima_fecha_programada = VALUES(ultima_fecha_programada),
                ultimo_estado = VALUES(ultimo_estado),
                ultima_carga = VALUES(ultima_carga),
                total_hh_reales_acumuladas = VALUES(total_hh_reales_acumuladas),
                dias_retraso = VALUES(dias_retraso)
        ");

        $resumenStmt->execute([
            $ot['codigo_ot'],
            $ot['id_prevision_sic'],
            $ot['fecha_programada'],
            $ot['fecha_programada'],
            $estadoFinal,
            $ot['hh_planificadas'],
            $hhReales,
            $vecesReprogramadas,
            $diasRetraso,
            $ot['id_vertical'],
            $ot['id_especialidad'],
            $ot['nombre_equipamiento'] ?? 'Equipo Genérico',
            $ot['tipo'] ?? 'INTERNA'
        ]);
        
        $inserted++;
    }

    $pdo->commit();

    echo "<hr>";
    echo "<h2>✅ Proceso Finalizado</h2>";
    echo "<ul>";
    echo "<li>Registros insertados en <code>ot_resumen_actual</code>: <strong>{$inserted}</strong></li>";
    echo "<li>Entradas creadas en <code>ot_historico</code>: <strong>{$historicoCount}</strong></li>";
    echo "</ul>";
    echo "<p><strong>Próximo paso:</strong> Ve al Módulo 4 (KPIs) en la app web. Deberías ver datos en las fichas superiores.</p>";
    echo "<p style='color:red;'><em>Recuerda borrar este archivo <code>init_kpis.php</code> por seguridad.</em></p>";

} catch (\Throwable $e) {
    if (isset($pdo)) $pdo->rollBack();
    echo "<h3 style='color:red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</h3>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}