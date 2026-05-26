<?php
session_start();
define('APP_ENTRY_POINT', true);

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importar Planificación HH - MedicalOT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container-main {
            max-width: 1200px;
            margin: 0 auto;
        }
        .card-custom {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            background: white;
        }
        .drop-zone {
            border: 3px dashed #667eea;
            border-radius: 15px;
            padding: 50px 30px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            background: #f8f9ff;
        }
        .drop-zone:hover, .drop-zone.dragover {
            border-color: #764ba2;
            background: #eef0ff;
            transform: scale(1.02);
        }
        .drop-zone i {
            font-size: 64px;
            color: #667eea;
            margin-bottom: 20px;
        }
        .drop-zone h4 {
            color: #333;
            font-weight: 600;
        }
        .file-selected {
            background: #d4edda;
            border: 2px solid #28a745;
            padding: 15px;
            border-radius: 10px;
            margin-top: 15px;
        }
        .file-selected i {
            color: #28a745;
            font-size: 24px;
            margin-right: 10px;
        }
        .btn-import {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px 30px;
            font-weight: 600;
            font-size: 16px;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        .btn-import:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .btn-import:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .log-container {
            max-height: 400px;
            overflow-y: auto;
            background: #1e1e1e;
            color: #d4d4d4;
            border-radius: 10px;
            padding: 15px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            line-height: 1.6;
        }
        .log-entry {
            margin-bottom: 5px;
            padding: 3px 0;
            border-bottom: 1px solid #333;
        }
        .log-entry.error {
            color: #f48771;
            font-weight: bold;
        }
        .log-entry.success {
            color: #89d185;
        }
        .log-entry.warning {
            color: #cca700;
        }
        .log-entry.info {
            color: #6fb3d2;
        }
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            text-align: center;
        }
        .stats-card h3 {
            font-size: 42px;
            font-weight: 700;
            margin: 10px 0;
        }
        .stats-card p {
            opacity: 0.9;
            font-size: 14px;
            margin: 0;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        .header-title {
            color: white;
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
            text-align: center;
        }
        .header-subtitle {
            color: rgba(255,255,255,0.9);
            text-align: center;
            margin-bottom: 30px;
        }
        .info-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }
        .info-box i {
            color: #856404;
            font-size: 20px;
            margin-right: 10px;
        }
        .progress-custom {
            height: 8px;
            border-radius: 10px;
            overflow: hidden;
            background: #e9ecef;
        }
        .progress-bar-custom {
            height: 100%;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            transition: width 0.5s ease;
        }
        .form-select, .form-control {
            border-radius: 10px;
            padding: 10px 15px;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }
        .form-select:focus, .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .historial-item {
            padding: 12px;
            border-bottom: 1px solid #e9ecef;
            transition: background 0.2s;
        }
        .historial-item:hover {
            background: #f8f9fa;
        }
        .historial-item:last-child {
            border-bottom: none;
        }
        .badge-status {
            font-size: 11px;
            padding: 4px 10px;
            border-radius: 12px;
        }
    </style>
</head>
<body>
    <div class="container-main">
        <div class="header-title">
            <i class="bi bi-calendar-check"></i> Importar Planificación HH
        </div>
        <p class="header-subtitle">
            Carga masiva de planificación mensual de horas-hombre desde Excel
        </p>

        <div class="row g-4">
            <!-- Columna Principal -->
            <div class="col-lg-8">
                <div class="card-custom p-4">
                    <h4 class="mb-4">
                        <i class="bi bi-cloud-upload text-primary"></i> Cargar Archivo Excel
                    </h4>

                    <form id="formImportar">
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Año</label>
                                <select class="form-select" id="año" name="año" required>
                                    <?php for ($i = date('Y'); $i >= 2020; $i--): ?>
                                        <option value="<?= $i ?>" <?= $i == date('Y') ? 'selected' : '' ?>>
                                            <?= $i ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Mes</label>
                                <select class="form-select" id="mes" name="mes" required>
                                    <option value="1" <?= date('n') == 1 ? 'selected' : '' ?>>Enero</option>
                                    <option value="2" <?= date('n') == 2 ? 'selected' : '' ?>>Febrero</option>
                                    <option value="3" <?= date('n') == 3 ? 'selected' : '' ?>>Marzo</option>
                                    <option value="4" <?= date('n') == 4 ? 'selected' : '' ?>>Abril</option>
                                    <option value="5" <?= date('n') == 5 ? 'selected' : '' ?>>Mayo</option>
                                    <option value="6" <?= date('n') == 6 ? 'selected' : '' ?>>Junio</option>
                                    <option value="7" <?= date('n') == 7 ? 'selected' : '' ?>>Julio</option>
                                    <option value="8" <?= date('n') == 8 ? 'selected' : '' ?>>Agosto</option>
                                    <option value="9" <?= date('n') == 9 ? 'selected' : '' ?>>Septiembre</option>
                                    <option value="10" <?= date('n') == 10 ? 'selected' : '' ?>>Octubre</option>
                                    <option value="11" <?= date('n') == 11 ? 'selected' : '' ?>>Noviembre</option>
                                    <option value="12" <?= date('n') == 12 ? 'selected' : '' ?>>Diciembre</option>
                                </select>
                            </div>
                        </div>

                        <div class="drop-zone mb-3" id="dropZone">
                            <i class="bi bi-cloud-arrow-up"></i>
                            <h4>Arrastra tu archivo Excel aquí</h4>
                            <p class="text-muted mb-0">o haz clic para seleccionar</p>
                            <input type="file" id="archivo" name="archivo" accept=".xlsx,.xls" style="display: none;">
                        </div>

                        <div id="fileName"></div>

                        <button type="submit" class="btn btn-import text-white w-100 mt-3" id="btnImportar" disabled>
                            <i class="bi bi-upload"></i> Importar Planificación
                        </button>
                    </form>
                </div>

                <!-- Progreso y Logs -->
                <div class="card-custom p-4 mt-4" id="cardProgreso" style="display: none;">
                    <h4 class="mb-3">
                        <i class="bi bi-activity text-primary"></i> Proceso de Importación
                    </h4>
                    
                    <div class="progress-custom mb-3">
                        <div class="progress-bar-custom" id="progressBar" style="width: 0%"></div>
                    </div>
                    
                    <div class="log-container" id="logContainer"></div>
                </div>

                <!-- Resultado -->
                <div class="card-custom p-4 mt-4" id="cardResultado" style="display: none;">
                    <h4 class="mb-4">
                        <i class="bi bi-check-circle text-success"></i> Importación Completada
                    </h4>
                    <div id="resultadoContenido"></div>
                </div>
            </div>

            <!-- Columna Lateral -->
            <div class="col-lg-4">
                <!-- Instrucciones -->
                <div class="card-custom p-4 mb-4">
                    <h5 class="mb-3">
                        <i class="bi bi-info-circle text-primary"></i> Instrucciones
                    </h5>
                    <div class="info-box mb-3">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Formato esperado:</strong>
                        <ul class="small mt-2 mb-0">
                            <li>Archivo Excel (.xlsx, .xls)</li>
                            <li>Hoja: <strong>"Dotación (2)"</strong></li>
                            <li>Columnas: #, AREA, Responsable, RUT, Cargo, Grupo, Componente, Apellidos y Nombres, HH, Estatus, Turno</li>
                            <li>Columnas de días del mes (1-31) con códigos de turno</li>
                        </ul>
                    </div>
                    
                    <h6 class="mt-3">Códigos de turno válidos:</h6>
                    <ul class="small">
                        <li><strong>1-13</strong>: Turnos definidos en la BD</li>
                        <li><strong>-1</strong> o <strong>D</strong>: Día de descanso</li>
                        <li><strong>V</strong>: Vacante</li>
                        <li>Solo se procesan técnicos con <strong>HH = "SI"</strong> y <strong>Estatus = "Activo"</strong></li>
                    </ul>
                </div>

                <!-- Historial de Importaciones -->
                <div class="card-custom p-4">
                    <h5 class="mb-3">
                        <i class="bi bi-clock-history text-primary"></i> Importaciones Recientes
                    </h5>
                    <div id="importacionesRecientes" style="max-height: 400px; overflow-y: auto;">
                        <p class="text-muted text-center small">Cargando...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('archivo');
        const fileNameDiv = document.getElementById('fileName');
        const btnImportar = document.getElementById('btnImportar');
        const formImportar = document.getElementById('formImportar');

        // Eventos de drag and drop
        dropZone.addEventListener('click', () => fileInput.click());
        
        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('dragover');
        });

        dropZone.addEventListener('dragleave', () => {
            dropZone.classList.remove('dragover');
        });

        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('dragover');
            
            if (e.dataTransfer.files.length > 0) {
                fileInput.files = e.dataTransfer.files;
                actualizarNombreArchivo();
            }
        });

        fileInput.addEventListener('change', actualizarNombreArchivo);

        function actualizarNombreArchivo() {
            if (fileInput.files.length > 0) {
                const file = fileInput.files[0];
                const size = (file.size / 1024 / 1024).toFixed(2);
                fileNameDiv.innerHTML = `
                    <div class="file-selected">
                        <i class="bi bi-file-earmark-excel"></i>
                        <strong>${file.name}</strong>
                        <span class="text-muted ms-2">(${size} MB)</span>
                    </div>
                `;
                btnImportar.disabled = false;
            } else {
                fileNameDiv.innerHTML = '';
                btnImportar.disabled = true;
            }
        }

        formImportar.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(formImportar);
            const cardProgreso = document.getElementById('cardProgreso');
            const cardResultado = document.getElementById('cardResultado');
            const progressBar = document.getElementById('progressBar');
            const logContainer = document.getElementById('logContainer');
            
            cardProgreso.style.display = 'block';
            cardResultado.style.display = 'none';
            btnImportar.disabled = true;
            logContainer.innerHTML = '';
            progressBar.style.width = '10%';

            addLog('📤 Iniciando importación...', 'info');
            
            try {
                addLog('📡 Enviando archivo al servidor...', 'info');
                progressBar.style.width = '30%';

                const response = await fetch('api/importar_planificacion.php', {
                    method: 'POST',
                    body: formData
                });

                progressBar.style.width = '70%';
                addLog('⚙️ Procesando respuesta del servidor...', 'info');

                const result = await response.json();

                progressBar.style.width = '100%';

                // Mostrar logs del servidor
                if (result.log && Array.isArray(result.log)) {
                    result.log.forEach(log => {
                        let tipo = 'info';
                        if (log.includes('ERROR') || log.includes('❌')) tipo = 'error';
                        else if (log.includes('✅') || log.includes('completada')) tipo = 'success';
                        else if (log.includes('⚠️') || log.includes('omitido')) tipo = 'warning';
                        addLog(log, tipo);
                    });
                }

                if (result.success) {
                    addLog('✅ Importación completada exitosamente', 'success');
                    mostrarResultado(result);
                    cargarImportacionesRecientes();
                } else {
                    addLog('❌ Error: ' + (result.error || 'Error desconocido'), 'error');
                    alert('Error en la importación: ' + (result.error || 'Error desconocido'));
                }

            } catch (error) {
                addLog('❌ Error de conexión: ' + error.message, 'error');
                alert('Error de conexión: ' + error.message);
            } finally {
                setTimeout(() => {
                    btnImportar.disabled = false;
                }, 2000);
            }
        });

        function addLog(mensaje, tipo = 'info') {
            const logContainer = document.getElementById('logContainer');
            const entry = document.createElement('div');
            entry.className = 'log-entry ' + tipo;
            entry.textContent = mensaje;
            logContainer.appendChild(entry);
            logContainer.scrollTop = logContainer.scrollHeight;
        }

        function mostrarResultado(result) {
            const cardResultado = document.getElementById('cardResultado');
            cardResultado.style.display = 'block';
            const stats = result.stats;
            
            document.getElementById('resultadoContenido').innerHTML = `
                <div class="stats-grid">
                    <div class="stats-card">
                        <p><i class="bi bi-people"></i> Técnicos Procesados</p>
                        <h3>${stats.total_tecnicos}</h3>
                    </div>
                    <div class="stats-card" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                        <p><i class="bi bi-person-plus"></i> Técnicos Creados</p>
                        <h3>${stats.tecnicos_creados}</h3>
                    </div>
                    <div class="stats-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                        <p><i class="bi bi-person-check"></i> Técnicos Actualizados</p>
                        <h3>${stats.tecnicos_actualizados}</h3>
                    </div>
                    <div class="stats-card" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                        <p><i class="bi bi-calendar-check"></i> Planificaciones</p>
                        <h3>${stats.planificaciones_creadas}</h3>
                    </div>
                    <div class="stats-card" style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); color: #333;">
                        <p><i class="bi bi-person-dash"></i> Técnicos Omitidos</p>
                        <h3>${stats.tecnicos_omitidos}</h3>
                    </div>
                    <div class="stats-card" style="background: ${stats.errores > 0 ? 'linear-gradient(135deg, #eb3349 0%, #f45c43 100%)' : 'linear-gradient(135deg, #56ab2f 0%, #a8e063 100%)'};">
                        <p><i class="bi bi-exclamation-triangle"></i> Errores</p>
                        <h3>${stats.errores}</h3>
                    </div>
                </div>
            `;
        }

        async function cargarImportacionesRecientes() {
            try {
                const response = await fetch('api/importaciones_recientes.php');
                const data = await response.json();
                
                const container = document.getElementById('importacionesRecientes');
                
                if (data.success && data.importaciones && data.importaciones.length > 0) {
                    container.innerHTML = data.importaciones.map(imp => {
                        const statusColor = imp.estado === 'completado' ? 'success' : 
                                          imp.estado === 'error' ? 'danger' : 'warning';
                        const statusIcon = imp.estado === 'completado' ? 'check-circle' : 
                                         imp.estado === 'error' ? 'x-circle' : 'hourglass-split';
                        
                        return `
                            <div class="historial-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong class="small">${imp.mes_nombre || ''} ${imp.año}</strong>
                                        <div class="small text-muted">
                                            ${new Date(imp.created_at).toLocaleString('es-ES')}
                                        </div>
                                    </div>
                                    <span class="badge badge-status bg-${statusColor}">
                                        <i class="bi bi-${statusIcon}"></i> ${imp.estado}
                                    </span>
                                </div>
                                <div class="small mt-2">
                                    <i class="bi bi-file-earmark"></i> ${imp.nombre_archivo || 'N/A'}
                                </div>
                                <div class="small text-muted mt-1">
                                    ${imp.total_tecnicos || 0} técnicos, 
                                    ${imp.registros_exitosos || 0} planificaciones
                                    ${imp.tiempo_procesamiento ? ` · ${imp.tiempo_procesamiento}s` : ''}
                                </div>
                            </div>
                        `;
                    }).join('');
                } else {
                    container.innerHTML = '<p class="text-muted text-center small">No hay importaciones recientes</p>';
                }
            } catch (error) {
                console.error('Error cargando importaciones:', error);
                document.getElementById('importacionesRecientes').innerHTML = 
                    '<p class="text-danger text-center small">Error al cargar historial</p>';
            }
        }

        // Cargar historial al iniciar
        cargarImportacionesRecientes();
        
        // Actualizar mes automáticamente según la fecha actual
        document.getElementById('mes').value = new Date().getMonth() + 1;
    </script>
</body>
</html>