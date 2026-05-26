<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PDO;
use Exception;

class ImportarPlanificacionHH
{
    private $pdo;
    private $log = [];
    private $stats = [
        'total_tecnicos' => 0,
        'tecnicos_creados' => 0,
        'tecnicos_actualizados' => 0,
        'tecnicos_omitidos' => 0,
        'planificaciones_creadas' => 0,
        'errores' => 0
    ];
    
    private $mapaTurnos = [];
    private $mapaComponentes = [];
    private $año;
    private $mes;
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->cargarMapas();
    }
    
    /**
     * Carga los mapas de turnos y componentes desde la BD
     */
    private function cargarMapas(): void
    {
        // Cargar tipos de turno
        $stmt = $this->pdo->query("SELECT codigo, id, hh_diarias, tipo FROM tipos_turno WHERE activo = 1");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->mapaTurnos[(string)$row['codigo']] = $row;
        }
        
        // Agregar descanso explícitamente
        $this->mapaTurnos['-1'] = ['id' => null, 'hh_diarias' => 0, 'tipo' => 'descanso'];
        $this->mapaTurnos['0'] = ['id' => null, 'hh_diarias' => 0, 'tipo' => 'descanso'];
        
        // Cargar componentes
        $stmt = $this->pdo->query("SELECT codigo, id, nombre, nombre_corto FROM componentes WHERE activo = 1");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->mapaComponentes[$row['codigo']] = $row;
            if (!empty($row['nombre_corto'])) {
                $this->mapaComponentes[strtoupper($row['nombre_corto'])] = $row;
            }
        }
        
        $this->log("Mapas cargados: " . count($this->mapaTurnos) . " turnos, " . count($this->mapaComponentes) . " componentes");
    }
    
    /**
     * Procesa un archivo Excel de planificación
     */
    public function procesarArchivo(string $rutaArchivo, int $año, int $mes, ?int $usuarioId = null): array
    {
        $this->año = $año;
        $this->mes = $mes;
        
        $inicio = microtime(true);
        $importacionId = $this->crearRegistroImportacion($rutaArchivo, $usuarioId);
        
        try {
            $this->log("Iniciando importación: " . basename($rutaArchivo));
            $this->log("Período: $año-$mes");
            
            // Cargar archivo Excel
            $spreadsheet = IOFactory::load($rutaArchivo);
            
            // Buscar la hoja "Dotación (2)" o "Dotación"
            $hoja = $this->buscarHojaDotacion($spreadsheet);
            if (!$hoja) {
                throw new Exception("No se encontró la hoja 'Dotación (2)' o 'Dotación' en el archivo");
            }
            
            $this->log("Hoja encontrada: " . $hoja->getTitle());
            
            // Identificar fila de encabezados (busca "#", "AREA", "RUT")
            $filaEncabezados = $this->identificarFilaEncabezados($hoja);
            if (!$filaEncabezados) {
                throw new Exception("No se pudo identificar la fila de encabezados (buscando #, AREA, RUT)");
            }
            
            $this->log("Fila de encabezados encontrada en: $filaEncabezados");
            
            // Mapear columnas
            $mapaColumnas = $this->mapearColumnas($hoja, $filaEncabezados);
            $this->log("Columnas mapeadas: " . count($mapaColumnas) . " columnas totales");
            
            // Procesar cada fila de técnico
            $totalFilas = $hoja->getHighestRow();
            $this->log("Total de filas en hoja: $totalFilas");
            
            for ($fila = $filaEncabezados + 1; $fila <= $totalFilas; $fila++) {
                try {
                    $this->procesarFilaTecnico($hoja, $fila, $mapaColumnas);
                } catch (Exception $e) {
                    $this->log("Error en fila $fila: " . $e->getMessage());
                    $this->stats['errores']++;
                }
            }
            
            $duracion = round(microtime(true) - $inicio, 2);
            $this->log("✅ Importación completada en $duracion segundos");
            $this->log("Estadísticas finales: " . json_encode($this->stats));
            
            $this->actualizarRegistroImportacion($importacionId, 'completado', null, $duracion);
            
            return [
                'success' => true,
                'importacion_id' => $importacionId,
                'stats' => $this->stats,
                'log' => $this->log
            ];
            
        } catch (Exception $e) {
            $duracion = round(microtime(true) - $inicio, 2);
            $this->log("❌ ERROR FATAL: " . $e->getMessage());
            $this->actualizarRegistroImportacion($importacionId, 'error', $e->getMessage(), $duracion);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'stats' => $this->stats,
                'log' => $this->log
            ];
        }
    }
    
    /**
     * Busca la hoja de dotación en el spreadsheet
     */
    private function buscarHojaDotacion($spreadsheet): ?Worksheet
    {
        $nombresPosibles = ['Dotación (2)', 'Dotación', 'Dotacion', 'Dotacion (2)'];
        
        foreach ($nombresPosibles as $nombre) {
            try {
                $hoja = $spreadsheet->getSheetByName($nombre);
                if ($hoja) return $hoja;
            } catch (Exception $e) {
                continue;
            }
        }
        
        // Buscar por coincidencia parcial
        foreach ($spreadsheet->getAllSheets() as $hoja) {
            if (stripos($hoja->getTitle(), 'dotación') !== false || 
                stripos($hoja->getTitle(), 'dotacion') !== false) {
                return $hoja;
            }
        }
        
        return null;
    }
    
    /**
     * Identifica la fila que contiene los encabezados
     * ✅ CORREGIDO: usa getCell([$col, $fila]) en lugar de getCellByColumnAndRow
     */
    private function identificarFilaEncabezados(Worksheet $hoja): ?int
    {
        $indicadores = ['#', 'AREA', 'RUT', 'CARGO', 'COMPONENTE'];
        
        for ($fila = 1; $fila <= 20; $fila++) {
            $coincidencias = 0;
            
            for ($col = 1; $col <= 15; $col++) {
                $valor = $hoja->getCell([$col, $fila])->getValue();
                if ($valor) {
                    $valorUpper = strtoupper(trim((string)$valor));
                    foreach ($indicadores as $indicador) {
                        if ($valorUpper === $indicador || stripos($valorUpper, $indicador) !== false) {
                            $coincidencias++;
                            break;
                        }
                    }
                }
            }
            
            if ($coincidencias >= 3) {
                return $fila;
            }
        }
        
        return null;
    }
    
    /**
     * Mapea las columnas del Excel a nombres de campos
     * ✅ CORREGIDO: usa getCell([$col, $fila]) en lugar de getCellByColumnAndRow
     */
    private function mapearColumnas(Worksheet $hoja, int $filaEncabezados): array
    {
        $mapa = [];
        
        $totalCols = $hoja->getHighestColumn();
        $totalCols = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($totalCols);
        
        for ($col = 1; $col <= $totalCols; $col++) {
            $valor = $hoja->getCell([$col, $filaEncabezados])->getValue();
            if (!$valor) continue;
            
            $valorLimpio = strtoupper(trim((string)$valor));
            
            // Mapear columnas conocidas
            if ($valorLimpio === '#') {
                $mapa['numero'] = $col;
            } elseif ($valorLimpio === 'AREA') {
                $mapa['area'] = $col;
            } elseif ($valorLimpio === 'RESPONSABLE') {
                $mapa['responsable'] = $col;
            } elseif ($valorLimpio === 'RUT') {
                $mapa['rut'] = $col;
            } elseif ($valorLimpio === 'CARGO') {
                $mapa['cargo'] = $col;
            } elseif ($valorLimpio === 'GRUPO') {
                $mapa['grupo'] = $col;
            } elseif (stripos($valorLimpio, 'COMPONENTE') !== false) {
                $mapa['componente'] = $col;
            } elseif (stripos($valorLimpio, 'APELLIDOS') !== false || stripos($valorLimpio, 'NOMBRES') !== false) {
                $mapa['nombre'] = $col;
            } elseif ($valorLimpio === 'HH') {
                $mapa['aporta_hh'] = $col;
            } elseif (stripos($valorLimpio, 'ESTATUS') !== false || $valorLimpio === 'ESTADO') {
                $mapa['estatus'] = $col;
            } elseif (stripos($valorLimpio, 'TURNO') !== false && !is_numeric($valorLimpio)) {
                $mapa['turno'] = $col;
            }
            
            // Detectar columnas de días (fechas como 4/1/26 o números 1-31)
            if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{2,4})$/', $valorLimpio, $matches)) {
                $dia = (int)$matches[2]; // El día está en la segunda posición
                if ($dia >= 1 && $dia <= 31) {
                    $mapa["dia_$dia"] = $col;
                }
            } elseif (is_numeric($valorLimpio)) {
                $dia = (int)$valorLimpio;
                if ($dia >= 1 && $dia <= 31) {
                    $mapa["dia_$dia"] = $col;
                }
            }
        }
        
        return $mapa;
    }
    
    /**
     * Procesa una fila de técnico
     */
    private function procesarFilaTecnico(Worksheet $hoja, int $fila, array $mapaColumnas): void
    {
        // Extraer datos básicos
        $rut = $this->obtenerValorCelda($hoja, $fila, $mapaColumnas['rut'] ?? null);
        $nombre = $this->obtenerValorCelda($hoja, $fila, $mapaColumnas['nombre'] ?? null);
        $aportaHH = strtoupper($this->obtenerValorCelda($hoja, $fila, $mapaColumnas['aporta_hh'] ?? null) ?? '');
        $estatus = strtoupper($this->obtenerValorCelda($hoja, $fila, $mapaColumnas['estatus'] ?? null) ?? '');
        
        // Validar que sea una fila válida
        if (empty($rut) && empty($nombre)) {
            return;
        }
        
        $this->stats['total_tecnicos']++;
        
        // Filtrar: solo activos y que aportan HH
        if (stripos($estatus, 'VACANTE') !== false) {
            $this->log("Fila $fila: Técnico vacante omitido: $nombre");
            $this->stats['tecnicos_omitidos']++;
            return;
        }
        
        if ($aportaHH !== 'SI') {
            $this->log("Fila $fila: Técnico sin HH (HH=$aportaHH): $nombre");
            $this->stats['tecnicos_omitidos']++;
            return;
        }
        
        $cargo = $this->obtenerValorCelda($hoja, $fila, $mapaColumnas['cargo'] ?? null);
        $componente = $this->obtenerValorCelda($hoja, $fila, $mapaColumnas['componente'] ?? null);
        $area = $this->obtenerValorCelda($hoja, $fila, $mapaColumnas['area'] ?? null);
        $turno = $this->obtenerValorCelda($hoja, $fila, $mapaColumnas['turno'] ?? null);
        
        // Normalizar RUT
        $rutNormalizado = $this->normalizarRut($rut);
        
        // Buscar componente/especialidad
        $idEspecialidad = $this->buscarComponenteId($componente);
        
        // Buscar o crear técnico
        $tecnicoId = $this->buscarOCrearTecnico($rutNormalizado, $nombre, $cargo, $idEspecialidad);
        if (!$tecnicoId) {
            throw new Exception("No se pudo procesar técnico: $nombre");
        }
        
        // Extraer planificaciones diarias
        $planificacionesDiarias = $this->extraerPlanificacionesDiarias($hoja, $fila, $mapaColumnas);
        
        // Crear planificación mensual
        $planificacionMensualId = $this->crearPlanificacionMensual(
            $tecnicoId,
            $turno,
            $planificacionesDiarias
        );
        
        // Crear planificaciones diarias
        $this->crearPlanificacionesDiarias(
            $tecnicoId,
            $planificacionMensualId,
            $planificacionesDiarias
        );
    }
    
    /**
     * Obtiene el valor de una celda de forma segura
     * ✅ CORREGIDO: usa getCell([$col, $fila]) en lugar de getCellByColumnAndRow
     */
    private function obtenerValorCelda(Worksheet $hoja, int $fila, ?int $col): ?string
    {
        if (!$col) return null;
        
        try {
            $valor = $hoja->getCell([$col, $fila])->getValue();
            if ($valor === null || $valor === '') return null;
            
            $valorStr = trim((string)$valor);
            
            // Detectar valores inválidos
            if (stripos($valorStr, 'ERROR') !== false || 
                stripos($valorStr, '#N/A') !== false ||
                stripos($valorStr, '#REF') !== false ||
                stripos($valorStr, '#VALUE') !== false) {
                return null;
            }
            
            return $valorStr;
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Normaliza el RUT chileno
     */
    private function normalizarRut(?string $rut): ?string
    {
        if (!$rut) return null;
        
        // Limpiar caracteres no alfanuméricos excepto K/k
        $rut = preg_replace('/[^0-9kK]/', '', $rut);
        return strtoupper($rut);
    }
    
    /**
     * Busca el ID de componente basado en el nombre del Excel
     */
    private function buscarComponenteId(?string $componenteNombre): ?int
    {
        if (!$componenteNombre) return null;
        
        $componenteUpper = strtoupper(trim($componenteNombre));
        
        // Mapeo inteligente por palabras clave
        $mapeoPalabras = [
            'CLIMATIZACIÓN-PIC' => 'CLIMA_PIC',
            'CLIMATIZACION-PIC' => 'CLIMA_PIC',
            'PIC' => 'CLIMA_PIC',
            'CLIMATIZACIÓN' => 'CLIMA',
            'CLIMATIZACION' => 'CLIMA',
            'CLIMA' => 'CLIMA',
            'SACC' => 'SACC',
            'AUTOMATIZACIÓN' => 'SACC',
            'AUTOMATIZACION' => 'SACC',
            'CONTROL CENTRALIZADO' => 'SACC',
            'ENERGÍA' => 'ENERGIA',
            'ENERGIA' => 'ENERGIA',
            'ILUMINACIÓN' => 'ENERGIA',
            'ILUMINACION' => 'ENERGIA',
            'ELÉCTRICO' => 'ENERGIA',
            'ELECTRICO' => 'ENERGIA',
            'GASES' => 'GASES',
            'GASES CLÍNICOS' => 'GASES',
            'INFRAESTRUCTURA GENERAL' => 'INFRA_SANIT',
            'SISTEMA SANITARIO' => 'INFRA_SANIT',
            'MOBILIARIO' => 'INFRA_MOB',
            'ÁREAS VERDES' => 'AREAS_VERDES',
            'AREAS VERDES' => 'AREAS_VERDES',
            'PAISAJISMO' => 'AREAS_VERDES',
            'EXTERIORES' => 'AREAS_VERDES',
            'CORRIENTES DÉBILES' => 'CORRIENTES',
            'CORRIENTES DEBILES' => 'CORRIENTES',
            'PCI' => 'CORRIENTES',
            'CORREO NEUMÁTICO' => 'CORRIENTES',
            'CORREO NEUMATICO' => 'CORRIENTES',
        ];
        
        // Buscar por coincidencia de palabras clave
        foreach ($mapeoPalabras as $palabra => $codigo) {
            if (stripos($componenteUpper, $palabra) !== false) {
                if (isset($this->mapaComponentes[$codigo])) {
                    return $this->mapaComponentes[$codigo]['id'];
                }
            }
        }
        
        return null;
    }
    
    /**
     * Busca o crea un técnico en la BD
     */
    private function buscarOCrearTecnico(?string $rut, ?string $nombre, ?string $cargo, ?int $idEspecialidad): ?int
    {
        if (empty($nombre)) return null;
        
        // Verificar qué columnas existen en la tabla tecnicos
        $stmt = $this->pdo->query("SHOW COLUMNS FROM tecnicos");
        $columnasExistentes = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'Field');
        
        // Buscar por RUT primero (si existe la columna)
        if ($rut && in_array('rut', $columnasExistentes)) {
            $stmt = $this->pdo->prepare("SELECT id FROM tecnicos WHERE rut = ? LIMIT 1");
            $stmt->execute([$rut]);
            $tecnico = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($tecnico) {
                $this->stats['tecnicos_actualizados']++;
                return $tecnico['id'];
            }
        }
        
        // Buscar por nombre
        $stmt = $this->pdo->prepare("SELECT id FROM tecnicos WHERE nombre = ? LIMIT 1");
        $stmt->execute([$nombre]);
        $tecnico = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($tecnico) {
            // Actualizar RUT si está vacío
            if ($rut && in_array('rut', $columnasExistentes)) {
                $stmt = $this->pdo->prepare("UPDATE tecnicos SET rut = ? WHERE id = ? AND (rut IS NULL OR rut = '')");
                $stmt->execute([$rut, $tecnico['id']]);
            }
            $this->stats['tecnicos_actualizados']++;
            return $tecnico['id'];
        }
        
        // Crear nuevo técnico
        $sql = "INSERT INTO tecnicos (nombre, activo";
        $values = "VALUES (?, 1";
        $params = [$nombre];
        
        if (in_array('rut', $columnasExistentes) && $rut) {
            $sql .= ", rut";
            $values .= ", ?";
            $params[] = $rut;
        }
        
        if (in_array('id_especialidad', $columnasExistentes) && $idEspecialidad) {
            $sql .= ", id_especialidad";
            $values .= ", ?";
            $params[] = $idEspecialidad;
        }
        
        $sql .= ") $values)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        $this->stats['tecnicos_creados']++;
        $this->log("Técnico creado: $nombre (ID: " . $this->pdo->lastInsertId() . ")");
        return (int)$this->pdo->lastInsertId();
    }
    
    /**
     * Extrae las planificaciones diarias de una fila
     */
    private function extraerPlanificacionesDiarias(Worksheet $hoja, int $fila, array $mapaColumnas): array
    {
        $planificaciones = [];
        
        for ($dia = 1; $dia <= 31; $dia++) {
            $col = $mapaColumnas["dia_$dia"] ?? null;
            if (!$col) continue;
            
            // Validar que el día exista en el mes
            if (!checkdate($this->mes, $dia, $this->año)) {
                continue;
            }
            
            $valor = $this->obtenerValorCelda($hoja, $fila, $col);
            $codigoTurno = $this->normalizarCodigoTurno($valor);
            $turno = $this->mapaTurnos[$codigoTurno] ?? null;
            
            $planificaciones[$dia] = [
                'codigo_turno' => $codigoTurno,
                'id_turno' => $turno['id'] ?? null,
                'horas' => $turno['hh_diarias'] ?? 0,
                'tipo_turno' => $turno['tipo'] ?? 'descanso'
            ];
        }
        
        return $planificaciones;
    }
    
    /**
     * Normaliza el código de turno
     */
    private function normalizarCodigoTurno($valor): string
    {
        if ($valor === null || $valor === '') {
            return '-1'; // Descanso
        }
        
        $valorStr = trim((string)$valor);
        
        // Casos especiales que se consideran descanso
        $descansos = ['-1', '0', 'D', 'V', 'DESCANSO', 'VACANTE', 'EN PROCESO', 'INGRESO'];
        foreach ($descansos as $d) {
            if (strtoupper($valorStr) === $d) {
                return '-1';
            }
        }
        
        // Detectar errores de Excel
        if (stripos($valorStr, 'ERROR') !== false || 
            stripos($valorStr, '#') !== false) {
            return '-1';
        }
        
        // Detectar números negativos grandes (errores)
        if (is_numeric($valorStr) && (float)$valorStr < -1) {
            return '-1';
        }
        
        // Si es numérico positivo, usar directamente
        if (is_numeric($valorStr)) {
            $num = (int)$valorStr;
            if ($num >= 1 && $num <= 13) {
                return (string)$num;
            }
        }
        
        return '-1'; // Por defecto, descanso
    }
    
    /**
     * Crea el registro de planificación mensual
     */
    private function crearPlanificacionMensual(int $tecnicoId, ?string $turno, array $planificacionesDiarias): int
    {
        // Calcular estadísticas
        $hhDia = 0;
        $hhNoche = 0;
        $diasLaborales = 0;
        $diasDescanso = 0;
        $turnosDia = 0;
        $turnosNoche = 0;
        
        foreach ($planificacionesDiarias as $plan) {
            if ($plan['codigo_turno'] === '-1' || $plan['codigo_turno'] === '0') {
                $diasDescanso++;
            } else {
                $diasLaborales++;
                if ($plan['tipo_turno'] === 'noche') {
                    $hhNoche += $plan['horas'];
                    $turnosNoche++;
                } else {
                    $hhDia += $plan['horas'];
                    $turnosDia++;
                }
            }
        }
        
        $hhTotal = $hhDia + $hhNoche;
        $mesNombre = $this->obtenerNombreMes($this->mes);
        
        // Eliminar planificación anterior si existe
        $stmt = $this->pdo->prepare("
            DELETE FROM planificacion_hh_mensual 
            WHERE id_tecnico = ? AND año = ? AND mes = ?
        ");
        $stmt->execute([$tecnicoId, $this->año, $this->mes]);
        
        // Insertar nueva planificación
        $stmt = $this->pdo->prepare("
            INSERT INTO planificacion_hh_mensual (
                id_tecnico, año, mes, mes_nombre,
                hh_planificadas_dia, hh_planificadas_noche, hh_planificadas_total,
                dias_laborales, dias_descanso,
                turnos_dia, turnos_noche,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $tecnicoId, $this->año, $this->mes, $mesNombre,
            $hhDia, $hhNoche, $hhTotal,
            $diasLaborales, $diasDescanso,
            $turnosDia, $turnosNoche
        ]);
        
        $this->stats['planificaciones_creadas']++;
        return (int)$this->pdo->lastInsertId();
    }
    
    /**
     * Crea los registros de planificación diaria
     */
    private function crearPlanificacionesDiarias(int $tecnicoId, int $planificacionMensualId, array $planificacionesDiarias): void
    {
        // Eliminar planificaciones diarias anteriores
        $stmt = $this->pdo->prepare("
            DELETE FROM planificacion_hh_diaria 
            WHERE id_tecnico = ? AND año = ? AND mes = ?
        ");
        $stmt->execute([$tecnicoId, $this->año, $this->mes]);
        
        // Insertar nuevas planificaciones diarias
        $stmt = $this->pdo->prepare("
            INSERT INTO planificacion_hh_diaria (
                id_tecnico, id_planificacion_mensual,
                año, mes, dia, fecha, dia_semana,
                id_turno, horas_planificadas,
                tipo_dia, turno_tipo, codigo_turno,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $insertados = 0;
        foreach ($planificacionesDiarias as $dia => $plan) {
            $fecha = sprintf('%04d-%02d-%02d', $this->año, $this->mes, $dia);
            $diaSemana = $this->obtenerDiaSemana($fecha);
            
            $tipoDia = ($plan['codigo_turno'] === '-1' || $plan['codigo_turno'] === '0') ? 'descanso' : 'laboral';
            $turnoTipo = $tipoDia === 'descanso' ? 'descanso' : ($plan['tipo_turno'] ?? 'dia');
            
            $stmt->execute([
                $tecnicoId, $planificacionMensualId,
                $this->año, $this->mes, $dia, $fecha, $diaSemana,
                $plan['id_turno'], $plan['horas'],
                $tipoDia, $turnoTipo, $plan['codigo_turno']
            ]);
            $insertados++;
        }
        
        $this->log("Técnico ID $tecnicoId: $insertados días planificados");
    }
    
    /**
     * Obtiene el nombre del mes en español
     */
    private function obtenerNombreMes(int $mes): string
    {
        $meses = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
        ];
        return $meses[$mes] ?? 'Desconocido';
    }
    
    /**
     * Obtiene el día de la semana en español
     */
    private function obtenerDiaSemana(string $fecha): string
    {
        $dias = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
        return $dias[date('w', strtotime($fecha))];
    }
    
    /**
     * Crea el registro de importación
     */
    private function crearRegistroImportacion(string $archivo, ?int $usuarioId): int
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO importaciones_planificacion (
                    nombre_archivo, año, mes, mes_nombre,
                    usuario_id, estado, created_at
                ) VALUES (?, ?, ?, ?, ?, 'procesando', NOW())
            ");
            
            $stmt->execute([
                basename($archivo),
                $this->año,
                $this->mes,
                $this->obtenerNombreMes($this->mes),
                $usuarioId
            ]);
            
            return (int)$this->pdo->lastInsertId();
        } catch (Exception $e) {
            $this->log("Error creando registro importación: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Actualiza el registro de importación
     */
    private function actualizarRegistroImportacion(
        int $id,
        string $estado,
        ?string $error,
        float $duracion
    ): void {
        if ($id <= 0) return;
        
        try {
            $stmt = $this->pdo->prepare("
                UPDATE importaciones_planificacion SET
                    estado = ?,
                    mensaje_error = ?,
                    tiempo_procesamiento = ?,
                    total_tecnicos = ?,
                    tecnicos_creados = ?,
                    tecnicos_actualizados = ?,
                    registros_exitosos = ?,
                    registros_fallidos = ?,
                    log_detalle = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                $estado,
                $error,
                $duracion,
                $this->stats['total_tecnicos'],
                $this->stats['tecnicos_creados'],
                $this->stats['tecnicos_actualizados'],
                $this->stats['planificaciones_creadas'],
                $this->stats['errores'],
                json_encode($this->log, JSON_UNESCAPED_UNICODE),
                $id
            ]);
        } catch (Exception $e) {
            $this->log("Error actualizando registro importación: " . $e->getMessage());
        }
    }
    
    /**
     * Agrega un mensaje al log
     */
    private function log(string $mensaje): void
    {
        $timestamp = date('H:i:s');
        $this->log[] = "[$timestamp] $mensaje";
    }
}