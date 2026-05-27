<?php
require_once __DIR__ . '/../config.php';
requireLogin();
$user = getCurrentUser();
$isAdmin = ($user['role'] === 'admin_hosp');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Registro de Asistencia - MedicalOT</title>
<link rel="stylesheet" href="/css/medicalot.css">
<style>
    .asistencia-container { max-width: 1000px; margin: 2rem auto; padding: 1rem; }
    .header-asistencia { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
    .date-picker { padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 0.5rem; }
    .attendance-table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 0.75rem; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
    .attendance-table th { background: #f1f5f9; padding: 0.75rem; text-align: left; font-size: 0.85rem; color: #64748b; }
    .attendance-table td { padding: 0.75rem; border-bottom: 1px solid #f1f5f9; }
    .status-select { padding: 0.4rem; border: 1px solid #cbd5e1; border-radius: 0.3rem; width: 100%; }
    .btn-save-all { background: #10b981; color: white; padding: 0.75rem 1.5rem; border: none; border-radius: 0.5rem; cursor: pointer; font-weight: 600; margin-top: 1rem; }
    .btn-save-all:hover { background: #059669; }
</style>
</head>
<body>
<div class="app-background"></div>
<header class="main-header">
    <div class="header-left">
        <img src="/img/logo_siglo_21.png" alt="Hospital" class="header-logo">
        <div>
            <div class="header-module-title">Hospital de Antofagasta</div>
            <div class="header-role"><?php echo htmlspecialchars($user['role_name']); ?></div>
        </div>
        <button class="home-icon-btn" onclick="window.location.href='index.php'" title="Volver al Home">
            <svg width="22" height="22" fill="none" stroke="var(--primary)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
        </button>
    </div>
</header>

<main class="container">
    <div class="asistencia-container">
        <div class="header-asistencia">
            <h2 style="margin:0; color:#1e293b;">📅 Registro de Asistencia Diaria</h2>
            <input type="date" id="fechaAsistencia" class="date-picker" value="<?php echo date('Y-m-d'); ?>" onchange="cargarAsistencia()">
        </div>

        <div class="card" style="padding:0;">
            <table class="attendance-table">
                <thead>
                    <tr>
                        <th>Técnico</th>
                        <th>Vertical</th>
                        <th>Turno Planificado</th>
                        <th>Estado Real</th>
                        <th>Observaciones</th>
                    </tr>
                </thead>
                <tbody id="tablaAsistenciaBody">
                    <tr><td colspan="5" style="text-align:center; padding:2rem; color:#94a3b8;">Seleccione una fecha para cargar lista</td></tr>
                </tbody>
            </table>
        </div>

        <button class="btn-save-all" onclick="guardarAsistencia()">💾 Guardar Asistencia</button>
    </div>
</main>

<script>
let asistenciaData = [];

async function cargarAsistencia() {
    const fecha = document.getElementById('fechaAsistencia').value;
    if (!fecha) return;

    const tbody = document.getElementById('tablaAsistenciaBody');
    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:2rem;">⏳ Cargando...</td></tr>';

    try {
        // Endpoint hipotético: debe devolver técnicos con su turno planificado para esa fecha
        const res = await fetch(`/api/asistencia.php?action=get_daily_list&fecha=${fecha}`);
        const data = await res.json();
        
        if (!data.success) throw new Error(data.error);
        asistenciaData = data.data;

        if (asistenciaData.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:2rem; color:#94a3b8;">No hay técnicos programados para esta fecha</td></tr>';
            return;
        }

        tbody.innerHTML = asistenciaData.map((t, index) => `
            <tr>
                <td style="font-weight:600;">${t.nombre}</td>
                <td>${t.vertical_nombre || '-'}</td>
                <td>${t.turno_planificado || 'Descanso'}</td>
                <td>
                    <select class="status-select" id="status_${index}" data-id="${t.tecnico_id}">
                        <option value="presente" ${t.estado_real === 'presente' ? 'selected' : ''}>✅ Presente</option>
                        <option value="ausente" ${t.estado_real === 'ausente' ? 'selected' : ''}>❌ Ausente</option>
                        <option value="licencia" ${t.estado_real === 'licencia' ? 'selected' : ''}>🏥 Licencia</option>
                        <option value="vacaciones" ${t.estado_real === 'vacaciones' ? 'selected' : ''}>🏖️ Vacaciones</option>
                    </select>
                </td>
                <td>
                    <input type="text" id="obs_${index}" value="${t.observaciones || ''}" placeholder="Notas..." style="width:100%; padding:0.4rem; border:1px solid #cbd5e1; border-radius:0.3rem;">
                </td>
            </tr>
        `).join('');

    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="5" style="text-align:center; color:#ef4444; padding:2rem;">❌ Error: ${err.message}</td></tr>`;
    }
}

async function guardarAsistencia() {
    const fecha = document.getElementById('fechaAsistencia').value;
    const registros = [];

    asistenciaData.forEach((t, index) => {
        const status = document.getElementById(`status_${index}`).value;
        const obs = document.getElementById(`obs_${index}`).value;
        registros.push({
            tecnico_id: t.tecnico_id,
            fecha: fecha,
            estado: status,
            observaciones: obs
        });
    });

    try {
        const res = await fetch('/api/asistencia.php?action=save_daily', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ registros })
        });
        const data = await res.json();
        if (data.success) {
            alert('✅ Asistencia guardada correctamente');
        } else {
            alert('❌ Error: ' + data.error);
        }
    } catch (err) {
        alert('❌ Error de conexión');
    }
}

// Cargar al iniciar
document.addEventListener('DOMContentLoaded', () => {
    cargarAsistencia();
});
</script>
</body>
</html>