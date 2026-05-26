<?php
/**
 * Servicio de Importación de Planificación HH
 * Procesa archivos Excel de dotación mensual y genera planificaciones
 * 
 * @author MedicalOT
 * @version 1.0
 */

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PDO;
use Exception;

class ImportarPlanificacionHH
{
    private PDO $pdo;
    private array $log = [];
    private array $stats = [
        'total_tecnicos' => 0,
        'tecnicos_creados' => 0,
        'tecnicos_actualizados' => 0,
        'planificaciones_mensuales' => 0,
        'planificaciones_diarias' => 0,
        'errores' => 0
    ];
    
    private array $mapaTurnos = [];
    private array $mapaComponentes = [];
    private int $año;
    private int $mes;
    
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
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $this->mapaTurnos[(string)$row['codigo']] = $row;
        }
        
        // Cargar componentes
        $stmt = $this->pdo->query("SELECT codigo, id, nombre FROM componentes WHERE activo = 1");
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $this->mapaComponentes[$row['nombre']] = $row;
        }
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
            
            // Buscar la hoja correcta
            $hoja = $this->buscarHojaDotacion($spreadsheet);
            if (!$hoja) {
                throw new Exception("No se encontró la hoja de dotación en el archivo");
            }
            
            $this->log("Hoja encontrada: " . $hoja->getTitle());
            
            // Identificar fila de encabezados
            $filaEncabezados = $this->identificarFilaEncabezados($hoja);
            if (!$filaEncabezados) {
                throw new Exception("No se pudo identificar la fila de encabezados");
            }
            
            $this->log("Fila de encabezados: $filaEncabezados");
            
            // Mapear columnas
            $mapaColumnas = $this->mapearColumnas($hoja, $filaEncabezados);
            $this->log("Columnas mapeadas: " . json_encode($mapaColumnas));
            
            // Procesar cada fila de técnico
            $totalFilas = $hoja->getHighestRow();
            $this->log("Total de filas: $totalFilas");
            
            for ($fila = $filaEncabezados + 1; $fila <= $totalFilas; $fila++) {
                try {
                    $this->procesarFilaTecnico($hoja, $fila, $mapaColumnas);
                } catch (Exception $e) {
                    $this->log("Error en fila $fila: " . $e->getMessage());
                    $this->stats['errores']++;
                }
            }
            
            $duracion = round(microtime(true) - $inicio, 2);
            $this->log("Importación completada en $duracion segundos");
            
            $this->actualizarRegistroImportacion($importacionId, 'completado', null, $duracion);
            
            return [
                'success' => true,
                'importacion_id' => $importacionId,
                'stats' => $this->stats,
                'log' => $this->log
            ];
            
        } catch (Exception $e) {
            $duracion = round(microtime(true) - $inicio, 2);
            $this->log("ERROR: " . $e->getMessage());
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
        $nombresPosibles = ['Dotación (2)', 'Dotación', 'Dotacion', 'Planificación'];
        
        foreach ($nombresPosibles as $nombre) {
            try {
                $hoja = $spreadsheet->getSheetByName($nombre);
                if ($hoja) return $hoja;
            } catch (Exception $e) {
                continue;
            }
        }
        
        // Si no encuentra por nombre, usar la primera hoja
        return $spreadsheet->getSheet(0);
    }
    
    /**
     * Identifica la fila que contiene los encabezados
     */
    private function identificarFilaEncabezados(Worksheet $hoja): ?int
    {
        $indicadores = ['#', 'AREA', 'RUT', 'CARGO', 'Componente'];
        
        for ($fila = 1; $fila <= 20; $fila++) {
            $coincidencias = 0;
            
            for ($col = 1; $col <= 15; $col++) {
                $valor = $hoja->getCellByColumnAndRow($col, $fila)->getValue();
                if ($valor && in_array(strtoupper(trim($valor)), $indicadores)) {
                    $coincidencias++;
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
    private function mapearColumnas(Worksheet $hoja, int $filaEncabezados): array
    {
        $mapa = [];
        $columnasEsperadas = [
            '#' => 'numero',
            'AREA' => 'area',
            'Responsable' => 'responsable',
            'RUT' => 'rut',
            'CARGO' => 'cargo',
            'Grupo' => 'grupo',
            'Componente' => 'componente',
            'Apellidos y Nombres' => 'nombre',
            'HH' => 'aporta_hh',
            'Estatus' => 'estatus',
            'Turno' => 'turno'
        ];
        
        $totalCols = $hoja->getHighestColumn();
        $totalCols = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($totalCols);
        
        for ($col = 1; $col <= $totalCols; $col++) {
            $valor = $hoja->getCellByColumnAndRow($col, $filaEncabezados)->getValue();
            if (!$valor) continue;
            
            $valorLimpio = trim($valor);
            
            // Buscar en columnas esperadas
            foreach ($columnasEsperadas as $nombre => $campo) {
                if (stripos($valorLimpio, $nombre) !== false) {
                    $mapa[$campo] = $col;
                    break;
                }
            }
            
            // Detectar columnas de días (1-31)
            if (is_numeric($valorLimpio)) {
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
        
        // Validar que sea una fila válida
        if (empty($rut) && empty($nombre)) {
            return;
        }
        
        $this->stats['total_tecnicos']++;
        
        $cargo = $this->obtenerValorCelda($hoja, $fila, $mapaColumnas['cargo'] ?? null);
        $componente = $this->obtenerValorCelda($hoja, $fila, $mapaColumnas['componente'] ?? null);
        $aportaHH = strtoupper($this->obtenerValorCelda($hoja, $fila, $mapaColumnas['aporta_hh'] ?? null)) === 'SI';
        $estatus = $this->obtenerValorCelda($hoja, $fila, $mapaColumnas['estatus'] ?? null);
        $turno = $this->obtenerValorCelda($hoja, $fila, $mapaColumnas['turno'] ?? null);
        
        // Si no aporta HH o está vacante, solo registrar pero no crear planificación
        if (!$aportaHH || stripos($estatus, 'vacante') !== false) {
            $this->log("Saltando técnico (no aporta HH o vacante): $nombre");
            return;
        }
        
        // Buscar o crear técnico
        $tecnicoId = $this->buscarOCrearTecnico($rut, $nombre, $cargo, $componente);
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
     */
    private function obtenerValorCelda(Worksheet $hoja, int $fila, ?int $col): ?string
    {
        if (!$col) return null;
        
        $valor = $hoja->getCellByColumnAndRow($col, $fila)->getValue();
        return $valor ? trim((string)$valor) : null;
    }
    
    /**
     * Busca o crea un técnico en la BD
     */
    private function buscarOCrearTecnico(?string $rut, string $nombre, ?string $cargo, ?string $componente): ?int
    {
        // Buscar por RUT
        if ($rut) {
            $stmt = $this->pdo->prepare("SELECT id FROM tecnicos WHERE rut = ? LIMIT 1");
            $stmt->execute([$rut]);
            $tecnico = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($tecnico) {
                $this->stats['tecnicos_actualizados']++;
                return $tecnico['id'];
            }
        }
        
        // Buscar por nombre
        $stmt = $this->pdo->prepare("SELECT id FROM tecnicos WHERE nombre = ? LIMIT 1");
        $stmt->execute([$nombre]);
        $tecnico = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($tecnico) {
            $this->stats['tecnicos_actualizados']++;
            return $tecnico['id'];
        }
        
        // Crear nuevo técnico
        $stmt = $this->pdo->prepare("
            INSERT INTO tecnicos (rut, nombre, cargo, activo, created_at)
            VALUES (?, ?, ?, 1, NOW())
        ");
        $stmt->execute([$rut, $nombre, $cargo]);
        
        $this->stats['tecnicos_creados']++;
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
            
            $valor = $hoja->getCellByColumnAndRow($col, $fila)->getValue();
            
            // Validar que el día exista en el mes
            if (!checkdate($this->mes, $dia, $this->año)) {
                continue;
            }
            
            $codigoTurno = $this->normalizarCodigoTurno($valor);
            $turno = $this->mapaTurnos[$codigoTurno] ?? null;
            
            $planificaciones[$dia] = [
                'codigo_turno' => $codigoTurno,
                'id_turno' => $turno['id'] ?? null,
                'horas' => $turno['hh_diarias'] ?? 0,
                'tipo_turno' => $turno['tipo'] ?? null
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
        
        $valor = trim((string)$valor);
        
        // Casos especiales
        if ($valor === '0' || $valor === 'D' || stripos($valor, 'descanso') !== false) {
            return '-1';
        }
        
        // Si es numérico, usar directamente
        if (is_numeric($valor)) {
            return (string)(int)$valor;
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
            if ($plan['codigo_turno'] === '-1') {
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
        
        $this->stats['planificaciones_mensuales']++;
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
        
        foreach ($planificacionesDiarias as $dia => $plan) {
            $fecha = sprintf('%04d-%02d-%02d', $this->año, $this->mes, $dia);
            $diaSemana = $this->obtenerDiaSemana($fecha);
            
            $tipoDia = $plan['codigo_turno'] === '-1' ? 'descanso' : 'laboral';
            $turnoTipo = $plan['codigo_turno'] === '-1' ? 'descanso' : $plan['tipo_turno'];
            
            $stmt->execute([
                $tecnicoId, $planificacionMensualId,
                $this->año, $this->mes, $dia, $fecha, $diaSemana,
                $plan['id_turno'], $plan['horas'],
                $tipoDia, $turnoTipo, $plan['codigo_turno']
            ]);
            
            $this->stats['planificaciones_diarias']++;
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
     * Crea el registro de importación
     */
    private function crearRegistroImportacion(string $archivo, ?int $usuarioId): int
    {
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
            $this->stats['planificaciones_mensuales'],
            $this->stats['errores'],
            json_encode($this->log),
            $id
        ]);
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