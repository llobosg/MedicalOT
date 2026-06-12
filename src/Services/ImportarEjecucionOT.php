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
            
            $hoja = $spreadsheet->getSheet(0);
            $this->log("Hoja utilizada: " . $hoja->getTitle());
            
            // 1. Detectar Encabezados y Limpiar Claves
            $filaEncabezados = 1;
            $encabezadosRaw = [];
            $encabezadosClean = [];
            
            for ($col = 1; $col <= 150; $col++) { // Aumentamos rango para asegurar lectura de columnas lejanas como BN
                $val = $hoja->getCell([$col, $filaEncabezados])->getValue();
                if ($val) {
                    $rawKey = trim((string)$val);
                    // Crear clave limpia: Mayúsculas, sin tildes, sin espacios
                    $cleanKey = strtoupper(strtr(utf8_decode($rawKey), utf8_decode('áéíóúñ'), 'aeioun'));
                    $cleanKey = preg_replace('/[^A-Z0-9_]/', '', $cleanKey);
                    
                    $encabezadosRaw[$col] = $rawKey;
                    if (!empty($cleanKey)) {
                        $encabezadosClean[$cleanKey] = $col;
                    }
                }
            }
            
            // Loguear las primeras 20 columnas detectadas para depuración
            $sampleKeys = array_slice(array_keys($encabezadosClean), 0, 20);
            $this->log("Columnas detectadas (Muestra): " . implode(', ', $sampleKeys));

                        // 2. Mapeo Dinámico Robusto
            // Buscamos coincidencias parciales o exactas en las claves limpias
            $mapa = [
                // ✅ CORRECCIÓN: Buscar NUMOT o IDPREVISION o CODIGOOT
                'id_prevision' => $this->buscarCol($encabezadosClean, ['NUMOT', 'NUMEROOT', 'IDPREVISION', 'CODIGOOT']), 
                
                'equipo' => $this->buscarCol($encabezadosClean, ['NOMBREEQUIPO', 'EQUIPO']),
                'estado_equipo' => $this->buscarCol($encabezadosClean, ['ESTADOEQUIPO']),
                'vertical' => $this->buscarCol($encabezadosClean, ['AREA', 'GERENCIA']), 
                'fecha_inicio' => $this->buscarCol($encabezadosClean, ['FECHAINIINTER', 'FECHAINICIO']),
                'hora_inicio' => $this->buscarCol($encabezadosClean, ['HORAINIINTER', 'HORAINICIO']),
                'fecha_termino' => $this->buscarCol($encabezadosClean, ['FECHAFININTER', 'FECHATERMINO']),
                'hora_termino' => $this->buscarCol($encabezadosClean, ['HORAFININTER', 'HORATERMINO']),
                'estado_ot' => $this->buscarCol($encabezadosClean, ['EST', 'ESTADO']),
                'situacion_final' => $this->buscarCol($encabezadosClean, ['SITUACIONFINAL']),
                'observaciones' => $this->buscarCol($encabezadosClean, ['OBSERVACIONES', 'OBSTECNICAS']),
                'tecnico' => $this->buscarCol($encabezadosClean, ['TECNICO', 'NOMBRETECNICO']),
                'especialidad' => $this->buscarCol($encabezadosClean, ['NOMBRESPECIALIDAD', 'ESPECIALIDAD']),
                'horas_reales' => $this->buscarCol($encabezadosClean, ['HORAS', 'HHREALES']),
                'gerencia' => $this->buscarCol($encabezadosClean, ['GERENCIA']),
                'subgerencia' => $this->buscarCol($encabezadosClean, ['SUBGERENCIA']),
                'proveedor' => $this->buscarCol($encabezadosClean, ['PROVEEDOR']),
                'contrato' => $this->buscarCol($encabezadosClean, ['CONTRATO']),
            ];

            if (!$mapa['id_prevision']) {
                // DEBUG: Mostrar qué columnas se parecen a NUMOT
                $similares = [];
                foreach ($encabezadosClean as $key => $col) {
                    if (strpos($key, 'NUM') !== false || strpos($key, 'OT') !== false) {
                        $similares[] = "$key (Col $col)";
                    }
                }
                throw new Exception("No se encontró la columna 'Num Ot'. Similares detectadas: " . implode(', ', $similares));
            }
            
            $this->log("Mapeo final ID Prev: Col {$mapa['id_prevision']}");
            $this->log("Mapeo final Técnico: Col " . ($mapa['tecnico'] ?? 'NO ENCONTRADA'));
            $this->log("Mapeo final Horas: Col " . ($mapa['horas_reales'] ?? 'NO ENCONTRADA'));

            $totalFilas = $hoja->getHighestRow();
            $this->log("Procesando $totalFilas filas...");
            
            $this->pdo->beginTransaction();
            
            for ($fila = $filaEncabezados + 1; $fila <= $totalFilas; $fila++) {
                try {
                    $this->procesarFilaDinamica($hoja, $fila, $mapa);
                } catch (Exception $e) {
                    $this->stats['errores']++;
                    if ($this->stats['errores'] <= 5) {
                        $this->log("❌ Error Fila $fila: " . $e->getMessage());
                    }
                }
                
                if ($fila % 100 === 0) {
                    gc_collect_cycles();
                }
            }
            
            $this->pdo->commit();
            
            $duracion = round(microtime(true) - $inicio, 2);
            $this->log("✅ Importación completada en $duracion segundos.");
            
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
    
    // Helper para buscar columna por múltiples nombres posibles
    private function buscarCol(array $encabezados, array $posiblesNombres) {
        foreach ($posiblesNombres as $nombre) {
            if (isset($encabezados[$nombre])) {
                return $encabezados[$nombre];
            }
        }
        return null;
    }

    private function procesarFilaDinamica($hoja, int $fila, array $mapa): void
    {
        $this->stats['total_registros']++;
        
        // 1. Obtener ID OT (Num Ot)
        $numOt = $this->obtenerValor($hoja, $fila, $mapa['id_prevision']);
        if (empty($numOt)) return; // Saltar vacíos
        
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

        // 3. Fechas y Horas (Usando las columnas mapeadas)
        $fIni = $this->obtenerValor($hoja, $fila, $mapa['fecha_inicio']);
        $hIni = $this->obtenerValor($hoja, $fila, $mapa['hora_inicio']);
        $fFin = $this->obtenerValor($hoja, $fila, $mapa['fecha_termino']);
        $hFin = $this->obtenerValor($hoja, $fila, $mapa['hora_termino']);
        
        $fechaInicioReal = $this->combinarFechaHora($fIni, $hIni);
        $fechaTerminoReal = $this->combinarFechaHora($fFin, $hFin);
        
        // Debug log para las primeras filas
        if ($this->stats['total_registros'] <= 5) {
             $this->log("DEBUG Fila {$this->stats['total_registros']}: F_Fin='$fFin', H_Fin='$hFin' -> Combina: '$fechaTerminoReal'");
        }
        
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
            'id_prevision_sic' => $numOt,
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
        
        // Limpiar la fecha: quitar horas si vienen pegadas (ej: "2026-04-30 00:00:00.0" -> "2026-04-30")
        $fClean = preg_replace('/\s+\d{2}:\d{2}.*/', '', trim((string)$fecha));
        
        // Si la hora es nula o vacía, asumir 00:00:00
        $hClean = '00:00:00';
        if (!empty($hora)) {
            // Limpiar la hora: quitar milisegundos (ej: "13:29:14.803" -> "13:29:14")
            $hClean = preg_replace('/\.\d+$/', '', trim((string)$hora));
        }
        
        // Combinar
        $fullString = "$fClean $hClean";
        
        // Validar y convertir
        $ts = strtotime($fullString);
        if ($ts) {
            return date('Y-m-d H:i:s', $ts);
        }
        
        return null;
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