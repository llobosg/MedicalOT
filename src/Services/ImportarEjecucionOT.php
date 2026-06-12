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
        'sin_vinculo' => 0
    ];
    
    private $mapaTecnicos = []; // Para búsqueda rápida por RUT/Nombre
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->cargarMapaTecnicos();
    }
    
    private function cargarMapaTecnicos(): void
    {
        // Cargamos todos los técnicos activos para buscar por RUT o Nombre rápidamente
        $stmt = $this->pdo->query("SELECT id, rut, nombre FROM tecnicos WHERE activo = 1");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!empty($row['rut'])) {
                $this->mapaTecnicos[$this->normalizarRut($row['rut'])] = $row['id'];
            }
            // Mapeo por nombre exacto como fallback
            $this->mapaTecnicos[strtoupper(trim($row['nombre']))] = $row['id'];
        }
        $this->log("Mapa de técnicos cargado: " . count($this->mapaTecnicos) . " registros");
    }
    
    public function procesarArchivo(string $rutaArchivo): array
    {
        $inicio = microtime(true);
        
        try {
            $this->log("Iniciando importación de Ejecución Real...");
            
            // Cargar Excel
            $reader = IOFactory::createReaderForFile($rutaArchivo);
            if ($ext === 'csv') {
                $reader->setDelimiter(','); // O ';' dependiendo de tu CSV
                $reader->setEnclosure('"');
            }
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($rutaArchivo);
            
            // Buscar hoja "Estado Final" o similar
            $hoja = $this->buscarHoja($spreadsheet, ['Estado Final', 'Ejecucion', 'Cierre']);
            if (!$hoja) {
                throw new Exception("No se encontró la hoja 'Estado Final' o similar.");
            }
            
            $this->log("Hoja encontrada: " . $hoja->getTitle());
            
            // Detectar encabezados (Fila 1 usualmente)
            $filaEncabezados = 1; 
            $mapaColumnas = $this->mapearColumnasEjecucion($hoja, $filaEncabezados);
            
            if (empty($mapaColumnas['id_prevision'])) {
                throw new Exception("No se encontró la columna clave 'id_prevision' (Col B o similar).");
            }
            
            $totalFilas = $hoja->getHighestRow();
            $this->log("Procesando $totalFilas filas...");
            
            for ($fila = $filaEncabezados + 1; $fila <= $totalFilas; $fila++) {
                try {
                    $this->procesarFilaEjecucion($hoja, $fila, $mapaColumnas);
                } catch (Exception $e) {
                    $this->stats['errores']++;
                    // Loguear solo los primeros 5 errores para no saturar
                    if ($this->stats['errores'] <= 5) {
                        $this->log("❌ Error fila $fila: " . $e->getMessage());
                    }
                }
            }

            // Limpieza final
            unset($hoja);
            unset($spreadsheet);
            gc_collect_cycles();
            
            $duracion = round(microtime(true) - $inicio, 2);
            $this->log("✅ Importación completada en $duracion segundos.");
            $this->log("Estadísticas: " . json_encode($this->stats));
            
            return [
                'success' => true,
                'stats' => $this->stats,
                'log' => $this->log
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'stats' => $this->stats,
                'log' => $this->log
            ];
        }
    }
    
    private function mapearColumnasEjecucion($hoja, int $fila): array
    {
        $mapa = [];
        // Mapeo basado en letras de columna proporcionadas (A=1, B=2...)
        // Ajustamos índices base 1 de PhpSpreadsheet
        
        $mapa['id_prevision'] = 2;  // B
        $mapa['equipo'] = 11;       // K
        $mapa['estado_equipo'] = 15;// O
        $mapa['observaciones_p'] = 16; // P
        $mapa['vertical'] = 19;     // S
        $mapa['fecha_inicio'] = 28; // AB
        $mapa['fecha_termino'] = 30;// AD
        $mapa['estado_ot'] = 31;    // AE
        $mapa['situacion_final'] = 32; // AF
        $mapa['turno'] = 35;        // AI
        $mapa['gerencia'] = 41;     // AO
        $mapa['subgerencia'] = 42;  // AP
        $mapa['contrato'] = 44;     // AR
        $mapa['observaciones_as'] = 45; // AS
        $mapa['proveedor'] = 46;    // AT
        $mapa['tecnico'] = 48;      // AV
        $mapa['especialidad'] = 50; // AX
        $mapa['tipo_personal'] = 64;// BL
        $mapa['horas_reales'] = 66; // BN
        
        return $mapa;
    }
    
    private function procesarFilaEjecucion($hoja, int $fila, array $mapa): void
    {
        $this->stats['total_registros']++;
        
        // 1. Obtener ID Previsión (LLAVE MAESTRA)
        $idPrevision = $this->obtenerValor($hoja, $fila, $mapa['id_prevision']);
        if (empty($idPrevision)) {
            return; // Saltar filas vacías
        }
        
        // 2. Buscar Técnico
        $nombreTecnicoRaw = $this->obtenerValor($hoja, $fila, $mapa['tecnico']);
        $idTecnico = null;
        if (!empty($nombreTecnicoRaw)) {
            // Intentar buscar por RUT primero si parece RUT, sino por nombre
            $key = $this->normalizarRut($nombreTecnicoRaw);
            if (isset($this->mapaTecnicos[$key])) {
                $idTecnico = $this->mapaTecnicos[$key];
            } else {
                // Fallback nombre exacto
                $keyName = strtoupper(trim($nombreTecnicoRaw));
                if (isset($this->mapaTecnicos[$keyName])) {
                    $idTecnico = $this->mapaTecnicos[$keyName];
                }
            }
        }
        
        if (!$idTecnico && !empty($nombreTecnicoRaw)) {
            $this->stats['sin_vinculo']++;
        }

        // 3. Calcular Duración si es posible
        $fechaInicio = $this->parseFechaHora($this->obtenerValor($hoja, $fila, $mapa['fecha_inicio']));
        $fechaTermino = $this->parseFechaHora($this->obtenerValor($hoja, $fila, $mapa['fecha_termino']));
        $duracionMin = null;
        
        if ($fechaInicio && $fechaTermino) {
            $diff = strtotime($fechaTermino) - strtotime($fechaInicio);
            $duracionMin = floor($diff / 60);
        }
        
        // 4. Preparar Datos
        $data = [
            'id_prevision_sic' => $idPrevision,
            'id_tecnico' => $idTecnico,
            'nombre_equipo' => $this->obtenerValor($hoja, $fila, $mapa['equipo']),
            'estado_equipo' => $this->obtenerValor($hoja, $fila, $mapa['estado_equipo']),
            'vertical_nombre' => $this->obtenerValor($hoja, $fila, $mapa['vertical']),
            'gerencia' => $this->obtenerValor($hoja, $fila, $mapa['gerencia']),
            'subgerencia' => $this->obtenerValor($hoja, $fila, $mapa['subgerencia']),
            'fecha_inicio_reales' => $fechaInicio,
            'fecha_termino_reales' => $fechaTermino,
            'duracion_minutos_reales' => $duracionMin,
            'estado_final_ot' => $this->obtenerValor($hoja, $fila, $mapa['estado_ot']),
            'situacion_final' => $this->obtenerValor($hoja, $fila, $mapa['situacion_final']),
            'observaciones_cierre' => $this->obtenerValor($hoja, $fila, $mapa['observaciones_as']),
            'turno_ejecucion' => $this->obtenerValor($hoja, $fila, $mapa['turno']),
            'proveedor_ejecucion' => $this->obtenerValor($hoja, $fila, $mapa['proveedor']),
            'tipo_personal' => $this->obtenerValor($hoja, $fila, $mapa['tipo_personal']),
            'especialidad_ejecucion' => $this->obtenerValor($hoja, $fila, $mapa['especialidad']),
            'hh_consumidas_reales' => floatval($this->obtenerValor($hoja, $fila, $mapa['horas_reales']) ?? 0),
            'contrato_referencia' => $this->obtenerValor($hoja, $fila, $mapa['contrato'])
        ];
        
        // 5. Upsert (Insertar o Actualizar)
        // Usamos ON DUPLICATE KEY UPDATE asumiendo que id_prevision_sic podría repetirse si se re-importa el cierre
        // Pero idealmente, cada ejecución es única. Si es un cierre final, actualizamos.
        
        $sql = "INSERT INTO ejecucion_ot_real (
            id_prevision_sic, id_tecnico, nombre_equipo, estado_equipo, vertical_nombre,
            gerencia, subgerencia, fecha_inicio_reales, fecha_termino_reales, duracion_minutos_reales,
            estado_final_ot, situacion_final, observaciones_cierre, turno_ejecucion,
            proveedor_ejecucion, tipo_personal, especialidad_ejecucion, hh_consumidas_reales,
            contrato_referencia, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            id_tecnico = VALUES(id_tecnico),
            fecha_termino_reales = VALUES(fecha_termino_reales),
            estado_final_ot = VALUES(estado_final_ot),
            hh_consumidas_reales = VALUES(hh_consumidas_reales),
            updated_at = NOW()";
            
        // Nota: Para que ON DUPLICATE KEY funcione bien, necesitaríamos una UNIQUE KEY en id_prevision_sic
        // Si no la tenemos, simplemente INSERTAMOS siempre (historial de intentos) o hacemos SELECT previo.
        // Para simplificar y asegurar integridad de "último estado", haremos un DELETE previo de esa OT si ya existía cierre, o simple INSERT.
        // Dado que es "Estado Final", asumimos que es único por OT.
        
        // Estrategia Simple: INSERT IGNORE o REPLACE si definimos PK.
        // Vamos a usar INSERT directo. Si falla por duplicado (si agregamos unique key luego), se maneja.
        // Por ahora, INSERT simple.
        
        $stmt = $this->pdo->prepare(str_replace("ON DUPLICATE KEY UPDATE...", "", $sql)); // Limpiamos para insert simple si no hay UK
        
        // Mejor estrategia: Verificar si ya existe cierre para esta OT. Si sí, UPDATE. Si no, INSERT.
        $check = $this->pdo->prepare("SELECT id FROM ejecucion_ot_real WHERE id_prevision_sic = ? LIMIT 1");
        $check->execute([$idPrevision]);
        $exists = $check->fetch(PDO::FETCH_ASSOC);
        
        if ($exists) {
            // UPDATE
            $sqlUpd = "UPDATE ejecucion_ot_real SET 
                id_tecnico=?, nombre_equipo=?, estado_equipo=?, vertical_name=?,
                gerencia=?, subgerencia=?, fecha_inicio_reales=?, fecha_termino_reales=?,
                duracion_minutos_reales=?, estado_final_ot=?, situacion_final=?,
                observaciones_cierre=?, turno_ejecucion=?, proveedor_ejecucion=?,
                tipo_personal=?, especialidad_ejecucion=?, hh_consumidas_reales=?,
                contrato_referencia=?, updated_at=NOW()
                WHERE id_prevision_sic=?";
                
            $stmt = $this->pdo->prepare($sqlUpd);
            $stmt->execute([
                $data['id_tecnico'], $data['nombre_equipo'], $data['estado_equipo'], $data['vertical_nombre'],
                $data['gerencia'], $data['subgerencia'], $data['fecha_inicio_reales'], $data['fecha_termino_reales'],
                $data['duracion_minutos_reales'], $data['estado_final_ot'], $data['situacion_final'],
                $data['observaciones_cierre'], $data['turno_ejecucion'], $data['proveedor_ejecucion'],
                $data['tipo_personal'], $data['especialidad_ejecucion'], $data['hh_consumidas_reales'],
                $data['contrato_referencia'], $idPrevision
            ]);
            $this->stats['actualizados']++;
        } else {
            // INSERT
            $sqlIns = "INSERT INTO ejecucion_ot_real (
                id_prevision_sic, id_tecnico, nombre_equipo, estado_equipo, vertical_nombre,
                gerencia, subgerencia, fecha_inicio_reales, fecha_termino_reales, duracion_minutos_reales,
                estado_final_ot, situacion_final, observaciones_cierre, turno_ejecucion,
                proveedor_ejecucion, tipo_personal, especialidad_ejecucion, hh_consumidas_reales,
                contrato_referencia
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->pdo->prepare($sqlIns);
            $stmt->execute([
                $data['id_prevision_sic'], $data['id_tecnico'], $data['nombre_equipo'], $data['estado_equipo'], $data['vertical_nombre'],
                $data['gerencia'], $data['subgerencia'], $data['fecha_inicio_reales'], $data['fecha_termino_reales'], $data['duracion_minutos_reales'],
                $data['estado_final_ot'], $data['situacion_final'], $data['observaciones_cierre'], $data['turno_ejecucion'],
                $data['proveedor_ejecucion'], $data['tipo_personal'], $data['especialidad_ejecucion'], $data['hh_consumidas_reales'],
                $data['contrato_referencia']
            ]);
            $this->stats['insertados']++;
        }
        // ✅ LIBERAR MEMORIA CADA 100 REGISTROS
        if ($this->stats['total_registros'] % 100 === 0) {
            gc_collect_cycles();
            // Opcional: Loguear progreso cada 100
            // $this->log("Procesados: {$this->stats['total_registros']}");
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
    
    private function parseFechaHora($val) {
        if (!$val) return null;
        // PhpSpreadsheet devuelve objetos DateTime o floats si es formato Excel serial
        if ($val instanceof \DateTimeInterface) {
            return $val->format('Y-m-d H:i:s');
        }
        if (is_numeric($val)) {
            // Convertir Excel serial date to PHP timestamp
            $unixTimestamp = ($val - 25569) * 86400;
            return date('Y-m-d H:i:s', $unixTimestamp);
        }
        // Intentar parsear string
        $ts = strtotime($val);
        if ($ts) return date('Y-m-d H:i:s', $ts);
        return null;
    }
    
    private function buscarHoja($spreadsheet, $nombresPosibles) {
        foreach ($nombresPosibles as $nombre) {
            try {
                $hoja = $spreadsheet->getSheetByName($nombre);
                if ($hoja) return $hoja;
            } catch (\Exception $e) {}
        }
        // Fallback: primera hoja
        return $spreadsheet->getSheet(0);
    }
    
    private function log($msg) {
        $this->log[] = "[" . date('H:i:s') . "] $msg";
    }
}