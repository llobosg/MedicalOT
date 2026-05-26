<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
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
    public function procesarArchivo(string $rutaArchivo, ?int $año = null, ?int $mes = null, ?int $usuarioId = null): array
    {
        $inicio = microtime(true);
        
        try {
            $this->log("Iniciando importación: " . basename($rutaArchivo));
            
            // Cargar archivo Excel
            $spreadsheet = IOFactory::load($rutaArchivo);
            
            // Buscar la hoja correcta
            $hoja = $this->buscarHojaDotacion($spreadsheet);
            if (!$hoja) {
                throw new Exception("No se encontró la hoja 'Dotación (2)' o 'Dotación' en el archivo");
            }
            
            $this->log("Hoja encontrada: " . $hoja->getTitle());
            
            // 🔥 AUTO-DETECTAR AÑO Y MES DESDE EL EXCEL (tiene prioridad)
            $mesesInfo = $this->detectarMesDesdeExcel($hoja);
            
            // La auto-detección SIEMPRE tiene prioridad sobre el formulario
            if (isset($mesesInfo['mes']) && $mesesInfo['mes'] > 0) {
                $mes = $mesesInfo['mes'];
                $this->log("✅ Mes detectado automáticamente desde Excel: " . $this->obtenerNombreMes($mes));
            }
            
            if (isset($mesesInfo['año']) && $mesesInfo['año'] > 2000) {
                $año = $mesesInfo['año'];
                $this->log("✅ Año detectado automáticamente desde Excel: $año");
            }
            
            // Validar que tengamos año y mes
            if (!$año || !$mes) {
                throw new Exception("No se pudo detectar el año/mes del Excel. Por favor selecciónelos manualmente.");
            }
            
            $this->año = $año;
            $this->mes = $mes;
            
            $this->log("Período a procesar: " . $this->obtenerNombreMes($mes) . " $año");
            
            // Identificar fila de encabezados
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
            
            return [
                'success' => true,
                'stats' => $this->stats,
                'log' => $this->log,
                'periodo' => [
                    'año' => $this->año,
                    'mes' => $this->mes,
                    'mes_nombre' => $this->obtenerNombreMes($this->mes)
                ]
            ];
            
        } catch (Exception $e) {
            $duracion = round(microtime(true) - $inicio, 2);
            $this->log("❌ ERROR FATAL: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'stats' => $this->stats,
                'log' => $this->log
            ];
        }
    }
    
    /**
     * 🔥 Detecta el mes y año desde el Excel
     * Busca en las primeras filas: nombres de meses o fechas como 4/1/26
     */
    private function detectarMesDesdeExcel($hoja): array
    {
        $resultado = ['mes' => null, 'año' => null];
        
        // Mapa de nombres de meses en español
        $mapaMeses = [
            'enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4,
            'mayo' => 5, 'junio' => 6, 'julio' => 7, 'agosto' => 8,
            'septiembre' => 9, 'octubre' => 10, 'noviembre' => 11, 'diciembre' => 12
        ];
        
        // Buscar en las primeras 15 filas y 35 columnas
        for ($fila = 1; $fila <= 15; $fila++) {
            for ($col = 1; $col <= 35; $col++) {
                $valor = $hoja->getCell([$col, $fila])->getValue();
                
                if (!$valor) continue;
                
                $valorStr = trim((string)$valor);
                $valorLower = strtolower($valorStr);
                
                // 1. Buscar nombre del mes (ej: "abril", "Abril")
                foreach ($mapaMeses as $nombre => $numero) {
                    if ($valorLower === $nombre || stripos($valorStr, $nombre) !== false) {
                        $resultado['mes'] = $numero;
                        
                        // Intentar extraer año de celdas cercanas
                        for ($col2 = max(1, $col - 5); $col2 <= min(35, $col + 5); $col2++) {
                            $valorCercano = $hoja->getCell([$col2, $fila])->getValue();
                            if ($valorCercano && preg_match('/(\d{4})/', (string)$valorCercano, $matches)) {
                                $resultado['año'] = (int)$matches[1];
                                break 2;
                            }
                        }
                        
                        // Si encontramos mes pero no año, usar año actual
                        if (!$resultado['año']) {
                            $resultado['año'] = (int)date('Y');
                        }
                        
                        return $resultado;
                    }
                }
                
                // 2. Buscar formato de fecha (ej: "4/1/26", "01/04/2026")
                if (preg_match('/(\d{1,2})\/(\d{1,2})\/(\d{2,4})/', $valorStr, $matches)) {
                    $dia = (int)$matches[1];
                    $mesNum = (int)$matches[2];
                    $añoNum = (int)$matches[3];
                    
                    // Si el año es de 2 dígitos, convertir a 4
                    if ($añoNum < 100) {
                        $añoNum += 2000;
                    }
                    
                    // Validar que sea una fecha válida
                    if ($mesNum >= 1 && $mesNum <= 12 && checkdate($mesNum, $dia, $añoNum)) {
                        $resultado['mes'] = $mesNum;
                        $resultado['año'] = $añoNum;
                        return $resultado;
                    }
                }
            }
        }
        
        return $resultado;
    }
    
    /**
     * Busca la hoja de dotación en el spreadsheet
     */
    private function buscarHojaDotacion($spreadsheet)
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
     */
    private function identificarFilaEncabezados($hoja): ?int
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
     */
    private function mapearColumnas($hoja, int $filaEncabezados): array
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
                $dia = (int)$matches[2];
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
    private function procesarFilaTecnico($hoja, int $fila, array $mapaColumnas): void
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
            return;
        }
        
        if ($aportaHH !== 'SI') {
            $this->log("Fila $fila: Técnico sin HH (HH=$aportaHH): $nombre");
            return;
        }
        
        $cargo = $this->obtenerValorCelda($hoja, $fila, $mapaColumnas['cargo'] ?? null);
        $componente = $this->obtenerValorCelda($hoja, $fila, $mapaColumnas['componente'] ?? null);
        
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
        $planificacionMensualId = $this->crearPlanificacionMensual($tecnicoId, $planificacionesDiarias);
        
        // Crear planificaciones diarias
        $this->crearPlanificacionesDiarias($tecnicoId, $planificacionMensualId, $planificacionesDiarias);
    }
    
    /**
     * Obtiene el valor de una celda de forma segura
     */
    private function obtenerValorCelda($hoja, int $fila, ?int $col): ?string
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
        
        // Buscar por RUT primero
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
        
        if (in_array('cargo', $columnasExistentes) && $cargo) {
            $sql .= ", cargo";
            $values .= ", ?";
            $params[] = $cargo;
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
    private function extraerPlanificacionesDiarias($hoja, int $fila, array $mapaColumnas): array
    {
        $planificaciones = [];
        
        for ($dia = 1; $dia <= 31; $dia++) {
            $col = $mapaColumnas["dia_$dia"] ?? null;
            if (!$col) continue;
            
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
            return '-1';
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
        if (stripos($valorStr, 'ERROR') !== false || stripos($valorStr, '#') !== false) {
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
        
        return '-1';
    }
    
    /**
     * Crea el registro de planificación mensual
     */
    private function crearPlanificacionMensual(int $tecnicoId, array $planificacionesDiarias): int
    {
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
        $stmt = $this->pdo->prepare("DELETE FROM planificacion_hh_mensual WHERE id_tecnico = ? AND año = ? AND mes = ?");
        $stmt->execute([$tecnicoId, $this->año, $this->mes]);
        
        // Insertar nueva planificación
        $stmt = $this->pdo->prepare("
            INSERT INTO planificacion_hh_mensual (
                id_tecnico, año, mes, mes_nombre,
                hh_planificadas_dia, hh_planificadas_noche, hh_planificadas_total,
                dias_laborales, dias_descanso, turnos_dia, turnos_noche, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $tecnicoId, $this->año, $this->mes, $mesNombre,
            $hhDia, $hhNoche, $hhTotal,
            $diasLaborales, $diasDescanso, $turnosDia, $turnosNoche
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
        $stmt = $this->pdo->prepare("DELETE FROM planificacion_hh_diaria WHERE id_tecnico = ? AND año = ? AND mes = ?");
        $stmt->execute([$tecnicoId, $this->año, $this->mes]);
        
        // Insertar nuevas planificaciones diarias
        $stmt = $this->pdo->prepare("
            INSERT INTO planificacion_hh_diaria (
                id_tecnico, id_planificacion_mensual, año, mes, dia, fecha, dia_semana,
                id_turno, horas_planificadas, tipo_dia, turno_tipo, codigo_turno, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
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
        }
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
     * Agrega un mensaje al log interno
     */
    private function log(string $mensaje): void
    {
        $timestamp = date('H:i:s');
        $this->log[] = "[$timestamp] $mensaje";
    }
}