<?php
session_start();
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
        .drop-zone {
            border: 3px dashed #dee2e6;
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            transition: all 0.3s;
            cursor: pointer;
        }
        .drop-zone:hover, .drop-zone.dragover {
            border-color: #0d6efd;
            background: #f8f9fa;
        }
        .log-container {
            max-height: 300px;
            overflow-y: auto;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            font-family: monospace;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-hospital"></i> MedicalOT
            </a>
        </div>
    </nav>

    <div class="container mt-4">
        <h2><i class="bi bi-calendar-check"></i> Importar Planificación HH</h2>
        
        <div class="row mt-4">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <form id="formImportar">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Año</label>
                                    <select class="form-select" name="año" required>
                                        <?php for ($i = date('Y'); $i >= 2020; $i--): ?>
                                            <option value="<?= $i ?>"><?= $i ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Mes</label>
                                    <select class="form-select" name="mes" required>
                                        <option value="1">Enero</option>
                                        <option value="2">Febrero</option>
                                        <option value="3">Marzo</option>
                                        <option value="4">Abril</option>
                                        <option value="5">Mayo</option>
                                        <option value="6">Junio</option>
                                        <option value="7">Julio</option>
                                        <option value="8">Agosto</option>
                                        <option value="9">Septiembre</option>
                                        <option value="10">Octubre</option>
                                        <option value="11">Noviembre</option>
                                        <option value="12">Diciembre</option>
                                    </select>
                                </div>
                            </div>

                            <div class="drop-zone mb-3" id="dropZone">
                                <i class="bi bi-cloud-upload" style="font-size: 48px;"></i>
                                <h5>Arrastra tu archivo Excel aquí</h5>
                                <p class="text-muted">o haz clic para seleccionar</p>
                                <input type="file" name="archivo" accept=".xlsx,.xls" style="display: none;">
                                <div id="fileName"></div>
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg w-100" id="btnImportar" disabled>
                                <i class="bi bi-upload"></i> Importar Planificación
                            </button>
                        </form>
                    </div>
                </div>

                <div class="card mt-4" id="cardProgreso" style="display: none;">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-hourglass-split"></i> Proceso de Importación</h5>
                    </div>
                    <div class="card-body">
                        <div class="progress mb-3">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                 id="progressBar" style="width: 0%"></div>
                        </div>
                        <div class="log-container" id="logContainer"></div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-info-circle"></i> Instrucciones</h6>
                    </div>
                    <div class="card-body">
                        <p><strong>Formato del archivo:</strong></p>
                        <ul class="small">
                            <li>Excel (.xlsx, .xls)</li>
                            <li>Hoja: "Dotación (2)"</li>
                            <li>Columnas: #, AREA, RUT, CARGO, Componente, Nombre, HH, Turno</li>
                            <li>Días 1-31 con códigos de turno</li>
                        </ul>
                        <p><strong>Códigos de turno:</strong></p>
                        <ul class="small">
                            <li>1-13: Turnos definidos</li>
                            <li>-1 o D: Descanso</li>
                            <li>0: Sin turno</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const dropZone = document.getElementById('dropZone');
        const fileInput = dropZone.querySelector('input[type="file"]');
        const fileName = document.getElementById('fileName');
        const btnImportar = document.getElementById('btnImportar');
        const formImportar = document.getElementById('formImportar');

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
                fileName.innerHTML = `<strong>${file.name}</strong> (${size} MB)`;
                btnImportar.disabled = false;
            }
        }

        formImportar.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(formImportar);
            const cardProgreso = document.getElementById('cardProgreso');
            const progressBar = document.getElementById('progressBar');
            const logContainer = document.getElementById('logContainer');
            
            cardProgreso.style.display = 'block';
            btnImportar.disabled = true;
            logContainer.innerHTML = '<div>📤 Enviando archivo...</div>';
            progressBar.style.width = '20%';

            try {
                const response = await fetch('api/importar_planificacion.php', {
                    method: 'POST',
                    body: formData
                });

                progressBar.style.width = '80%';
                const result = await response.json();

                progressBar.style.width = '100%';

                if (result.success) {
                    logContainer.innerHTML += `
                        <div class="text-success mt-2">✅ ${result.message}</div>
                        <div class="mt-2">
                            <strong>Estadísticas:</strong><br>
                            Técnicos procesados: ${result.stats.total_tecnicos}<br>
                            Técnicos creados: ${result.stats.tecnicos_creados}<br>
                            Planificaciones creadas: ${result.stats.planificaciones_mensuales}
                        </div>
                    `;
                } else {
                    logContainer.innerHTML += `
                        <div class="text-danger mt-2">❌ Error: ${result.error}</div>
                    `;
                }
            } catch (error) {
                logContainer.innerHTML += `
                    <div class="text-danger mt-2">❌ Error de conexión: ${error.message}</div>
                `;
            } finally {
                btnImportar.disabled = false;
            }
        });
    </script>
</body>
</html>