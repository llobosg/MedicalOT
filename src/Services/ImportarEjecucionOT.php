<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PDO;
use Exception;

class ImportarEjecucionOT
{
    private $pdo;
    private $log = [];
    private $stats = [
        'total_registros' => 0,
        'insertados' => 0,
        'actualizados' => 0,
        'errores' => 0,
        'sin_vinculo_ot' => 0,
        'sin_vinculo_tecnico' => 0
    ];
    
    private $mapaTecnicos = [];
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->cargarMapaTecnicos();
    }
    
    private function cargarMapaTecnicos(): void
    {
        // Cargamos técnicos por RUT y por NOMBRE COMPLETO para maximizar coincidencias
        $stmt = $this->pdo->query("SELECT id, rut, nombre FROM tecnicos WHERE activo = 1");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!empty($row['rut'])) {
                $this->mapaTecnicos[$this->normalizarRut($row['rut'])] = $row['id'];
            }
            // Indexar por nombre en mayúsculas y sin espacios extra
            $this->mapaTecnicos[strtoupper(trim($row['nombre']))] = $row['id'];
            
            // Intentar indexar por primer nombre + apellido si es común
            $partes = explode(' ', trim($row['nombre']));
            if (count($partes) >= 2) {
                $claveCorta = strtoupper($partes[0] . ' ' . end($partes));
                $this->mapaTecnicos[$claveCorta] = $row['id'];
            }
        }
        $this->log("Mapa de técnicos cargado: " . count($this->mapaTecnicos) . " entradas.");
    }
    
    public function procesarArchivo(string $rutaArchivo): array
    {
        $inicio = microtime(true);
        
        try {
            $this->log("Iniciando importación de Ejecución Real...");
            
            $reader = IOFactory::createReaderForFile($rutaArchivo);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($rutaArchivo);
            
            // Usar la primera hoja activa
            $hoja = $spreadsheet->getSheet(0);
            $this->log("Hoja utilizada: " . $hoja->getTitle());
            
            // Detectar fila de encabezados buscando "Num Ot" o "Año Ot"
            $filaEncabezados = 1;
            $encabezados = [];
            for ($col = 1; $col <= 50; $col++) {
                $val = $hoja->getCell([$col, $filaEncabezados])->getValue();
                if ($val) {
                    $encabezados[strtoupper(trim((string)$val))] = $col;
                }
            }
            
            // Mapeo dinámico basado en nombres de columna del CSV
            $mapa = [
                'id_prevision' => $encabezados['NUM OT'] ?? null, // Columna B usualmente
                'equipo' => $encabezados['NOMBRE EQUIPO'] ?? $encabezados['EQUIPO'] ?? null,
                'estado_equipo' => $encabezados['ESTADO EQUIPO'] ?? null,
                'vertical' => $encabezados['ÁREA'] ?? $encabezados['AREA'] ?? null, // Usamos Área como Vertical temporal
                'fecha_inicio' => $encabezados['FECHA INI INTER'] ?? null,
                'hora_inicio' => $encabezados['HORA INI INTER'] ?? null,
                'fecha_termino' => $encabezados['FECHA FIN INTER'] ?? null,
                'hora_termino' => $encabezados['HORA FIN INTER'] ?? null,
                'estado_ot' => $encabezados['EST.'] ?? null,
                'situacion_final' => $encabezados['SITUACIÓN FINAL'] ?? null,
                'observaciones' => $encabezados['OBSERVACIONES TÉCNICAS GENERALES'] ?? null,
                'tecnico' => $encabezados['TÉCNICO'] ?? $encabezados['TECNICO'] ?? null,
                'especialidad' => $encabezados['NOMBRE ESPECIALIDAD'] ?? null,
                'horas_reales' => $encabezados['HORAS'] ?? null,
                'gerencia' => $encabezados['GERENCIA'] ?? null,
            );

            if (!$mapa['id_prevision']) {
                throw new Exception("No se encontró la columna 'Num Ot' para vincular registros.");
            }
            
            $totalFilas = $hoja->getHighestRow();
            $this->log("Procesando $totalFilas filas...");
            
            // Deshabilitar autocommit para velocidad
            $this->pdo->beginTransaction();
            
            for ($fila = $filaEncabezados + 1; $fila <= $totalFilas; $fila++) {
                try {
                    $this->procesarFilaDinamica($hoja, $fila, $mapa);
                } catch (Exception $e) {
                    $this->stats['errores']++;
                    if ($this->stats['errores'] <= 5) {
                        $this->log(" Error Fila $fila: " . $e->getMessage());
                    }
                }
                
                // Liberar memoria cada 100 filas
                if ($fila % 100 === 0) {
                    gc_collect_cycles();
                }
            }
            
            $this->pdo->commit();
            
            $duracion = round(microtime(true) - $inicio, 2);
            $this->log("✅ Importación completada en $duracion segundos.");
            $this->log("Estadísticas: " . json_encode($this->stats));
            
            return [
                'success' => true,
                'stats' => $this->stats,
                'log' => $this->log
            ];
            
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'stats' => $this->stats,
                'log' => $this->log
            ];
        }
    }
    
    private function procesarFilaDinamica($hoja, int $fila, array $mapa): void
    {
        $this->stats['total_registros']++;
        
        // 1. Obtener ID OT (Num Ot)
        $numOt = $this->obtenerValor($hoja, $fila, $mapa['id_prevision']);
        if (empty($numOt)) return; // Saltar vacíos
        
        // Normalizar ID OT: Si en BD es "SIC-24828" y en CSV es "24828", ajustar aquí
        // Asumimos que en BD se guarda como string numérico o con prefijo. 
        // Para seguridad, buscamos por coincidencia parcial o exacta.
        
        // 2. Buscar Técnico
        $nomTecnico = $this->obtenerValor($hoja, $fila, $mapa['tecnico']);
        $idTecnico = null;
        if (!empty($nomTecnico)) {
            $key = strtoupper(trim($nomTecnico));
            if (isset($this->mapaTecnicos[$key])) {
                $idTecnico = $this->mapaTecnicos[$key];
            } else {
                // Intentar buscar por RUT si el nombre viene sucio
                $rutKey = $this->normalizarRut($nomTecnico);
                if (isset($this->mapaTecnicos[$rutKey])) {
                    $idTecnico = $this->mapaTecnicos[$rutKey];
                } else {
                    $this->stats['sin_vinculo_tecnico']++;
                }
            }
        }

        // 3. Fechas y Horas
        $fIni = $this->obtenerValor($hoja, $fila, $mapa['fecha_inicio']);
        $hIni = $this->obtenerValor($hoja, $fila, $mapa['hora_inicio']);
        $fFin = $this->obtenerValor($hoja, $fila, $mapa['fecha_termino']);
        $hFin = $this->obtenerValor($hoja, $fila, $mapa['hora_termino']);
        
        $fechaInicioReal = $this->combinarFechaHora($fIni, $hIni);
        $fechaTerminoReal = $this->combinarFechaHora($fFin, $hFin);
        
        $duracionMin = null;
        if ($fechaInicioReal && $fechaTerminoReal) {
            $diff = strtotime($fechaTerminoReal) - strtotime($fechaInicioReal);
            if ($diff > 0) $duracionMin = floor($diff / 60);
        }

        // 4. Horas Reales (Formato HH:MM a Decimal)
        $hrsTexto = $this->obtenerValor($hoja, $fila, $mapa['horas_reales']);
        $hhReales = $this->convertirHoraADecimal($hrsTexto);

        // 5. Preparar Datos
        $data = [
            'id_prevision_sic' => $numOt, // Guardamos tal cual viene del CSV
            'id_tecnico' => $idTecnico,
            'nombre_equipo' => $this->obtenerValor($hoja, $fila, $mapa['equipo']),
            'estado_equipo' => $this->obtenerValor($hoja, $fila, $mapa['estado_equipo']),
            'vertical_nombre' => $this->obtenerValor($hoja, $fila, $mapa['vertical']),
            'gerencia' => $this->obtenerValor($hoja, $fila, $mapa['gerencia']),
            'fecha_inicio_reales' => $fechaInicioReal,
            'fecha_termino_reales' => $fechaTerminoReal,
            'duracion_minutos_reales' => $duracionMin,
            'estado_final_ot' => $this->obtenerValor($hoja, $fila, $mapa['estado_ot']),
            'situacion_final' => $this->obtenerValor($hoja, $fila, $mapa['situacion_final']),
            'observaciones_cierre' => $this->obtenerValor($hoja, $fila, $mapa['observaciones']),
            'especialidad_ejecucion' => $this->obtenerValor($hoja, $fila, $mapa['especialidad']),
            'hh_consumidas_reales' => $hhReales,
        ];
        
        // 6. Upsert Logic
        $check = $this->pdo->prepare("SELECT id FROM ejecucion_ot_real WHERE id_prevision_sic = ? LIMIT 1");
        $check->execute([$numOt]);
        $exists = $check->fetch(PDO::FETCH_ASSOC);
        
        if ($exists) {
            // UPDATE
            $sql = "UPDATE ejecucion_ot_real SET 
                id_tecnico=?, nombre_equipo=?, estado_equipo=?, vertical_nombre=?, gerencia=?,
                fecha_inicio_reales=?, fecha_termino_reales=?, duracion_minutos_reales=?,
                estado_final_ot=?, situacion_final=?, observaciones_cierre=?, especialidad_ejecucion=?,
                hh_consumidas_reales=?, updated_at=NOW()
                WHERE id_prevision_sic=?";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $data['id_tecnico'], $data['nombre_equipo'], $data['estado_equipo'], $data['vertical_nombre'], $data['gerencia'],
                $data['fecha_inicio_reales'], $data['fecha_termino_reales'], $data['duracion_minutos_reales'],
                $data['estado_final_ot'], $data['situacion_final'], $data['observaciones_cierre'], $data['especialidad_ejecucion'],
                $data['hh_consumidas_reales'], $numOt
            ]);
            $this->stats['actualizados']++;
        } else {
            // INSERT
            $sql = "INSERT INTO ejecucion_ot_real (
                id_prevision_sic, id_tecnico, nombre_equipo, estado_equipo, vertical_nombre, gerencia,
                fecha_inicio_reales, fecha_termino_reales, duracion_minutos_reales,
                estado_final_ot, situacion_final, observaciones_cierre, especialidad_ejecucion,
                hh_consumidas_reales, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $data['id_prevision_sic'], $data['id_tecnico'], $data['nombre_equipo'], $data['estado_equipo'], $data['vertical_nombre'], $data['gerencia'],
                $data['fecha_inicio_reales'], $data['fecha_termino_reales'], $data['duracion_minutos_reales'],
                $data['estado_final_ot'], $data['situacion_final'], $data['observaciones_cierre'], $data['especialidad_ejecucion'],
                $data['hh_consumidas_reales']
            ]);
            $this->stats['insertados']++;
        }
    }
    
    // Helpers
    private function obtenerValor($hoja, $fila, $col) {
        if (!$col) return null;
        $val = $hoja->getCell([$col, $fila])->getValue();
        return ($val === null || $val === '') ? null : trim((string)$val);
    }
    
    private function normalizarRut($rut) {
        if (!$rut) return null;
        return strtoupper(preg_replace('/[^0-9kK]/', '', $rut));
    }
    
    private function combinarFechaHora($fecha, $hora) {
        if (!$fecha) return null;
        // Si la fecha ya viene con hora en el string (ej: 2026-04-30 06:00:00.0)
        if (strpos($fecha, ':') !== false) {
            $ts = strtotime($fecha);
            if ($ts) return date('Y-m-d H:i:s', $ts);
        }
        
        // Si son campos separados
        $fClean = preg_replace('/\s+\d{2}:\d{2}:\d{2}.*/', '', $fecha); // Limpiar si viene basura
        $hClean = $hora ? preg_replace('/\.\d+$/', '', $hora) : '00:00:00';
        
        $full = "$fClean $hClean";
        $ts = strtotime($full);
        return $ts ? date('Y-m-d H:i:s', $ts) : null;
    }
    
    private function convertirHoraADecimal($texto) {
        if (!$texto) return 0.0;
        // Formato "4:00" o "4.00"
        if (strpos($texto, ':') !== false) {
            list($h, $m) = explode(':', $texto);
            return floatval($h) + (floatval($m) / 60);
        }
        return floatval($texto);
    }
    
    private function log($msg) {
        $this->log[] = "[" . date('H:i:s') . "] $msg";
    }
}