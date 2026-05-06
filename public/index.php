<?php
define('APP_ENTRY_POINT', true);
require_once __DIR__ . '/../includes/layout.php';
requireLogin();

$user = getCurrentUser();
$isAdmin = ($user['role'] === 'admin_hosp');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MedicalOT | Hospital de Antofagasta</title>
    <link rel="stylesheet" href="/css/medicalot.css">
    <style>
        /* ESTILOS ESPECÍFICOS PARA DASHBOARD */
        .module-section { display: none; animation: fadeIn 0.3s ease; }
        .module-section.active { display: block; }
        
        .home-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-top: 1.5rem; }
        .home-card { background: #fff; border-radius: 1rem; padding: 1.5rem; text-align: center; cursor: pointer; transition: all 0.3s; border: 2px solid transparent; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .home-card:hover { transform: translateY(-4px); border-color: var(--primary); }
        .home-card-icon { font-size: 2rem; margin-bottom: 0.75rem; display: block; }
        .home-card-title { font-size: 1rem; font-weight: 700; color: #1e293b; }
        .home-card-desc { font-size: 0.8rem; color: #64748b; margin-top: 0.25rem; }
        
        .upload-zone { border: 2px dashed #cbd5e1; border-radius: 1rem; padding: 2.5rem; text-align: center; background: #f8fafc; transition: all 0.3s; margin-bottom: 1.5rem; cursor: pointer; }
        .upload-zone:hover, .upload-zone.dragover { border-color: var(--primary); background: #e0f2fe; }
        .summary-box { background: #fff; border-radius: 0.75rem; padding: 1.25rem; margin: 1.5rem 0; box-shadow: 0 4px 6px rgba(0,0,0,0.1); display: none; border-left: 5px solid var(--success); }
        .summary-box.show { display: block; animation: slideInUp 0.4s ease; }
        .btn-volver { background: linear-gradient(135deg, #6366f1, #8b5cf6); color: #fff; padding: 0.75rem 1.5rem; border-radius: 0.75rem; border: none; cursor: pointer; font-weight: 600; margin-top: 1.5rem; display: inline-flex; align-items: center; gap: 0.5rem; transition: all 0.3s; }
        .btn-volver:hover { opacity: 0.9; transform: translateY(-2px); }
        
        .ots-layout { display: grid; grid-template-columns: 80% 20%; gap: 1rem; height: calc(100vh - 180px); }
        .ots-left { display: grid; grid-template-rows: auto 1fr; gap: 1rem; }
        .ots-right { background: #fff; border-radius: 1rem; padding: 1rem; overflow-y: auto; border: 1px solid #e2e8f0; display: flex; flex-direction: column; }
        .search-container { position: relative; margin-bottom: 0.75rem; }
        .search-input { width: 100%; padding: 0.75rem 1rem; border: 2px solid #e2e8f0; border-radius: 0.75rem; font-size: 0.95rem; }
        .search-input:focus { border-color: var(--primary); outline: none; }
        .search-dropdown { position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #e2e8f0; border-radius: 0.5rem; max-height: 220px; overflow-y: auto; z-index: 50; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); margin-top: 4px; display: none; }
        .search-dropdown.show { display: block; }
        .search-item { padding: 0.75rem 1rem; cursor: pointer; border-bottom: 1px solid #f1f5f9; background: #fff; color: #156b7d; font-weight: 500; font-size: 0.9rem; }
        .search-item:hover { background: #f8fafc; }
        .filters-row { display: grid; grid-template-columns: repeat(5, 1fr); gap: 0.5rem; }
        .filters-row select { padding: 0.5rem; border: 1px solid #e2e8f0; border-radius: 0.5rem; font-size: 0.85rem; background: #f8fafc; }
        .table-scroll-wrapper { overflow: auto; border: 1px solid #e2e8f0; border-radius: 0.75rem; background: #fff; }
        .ots-table { width: 100%; min-width: 1100px; border-collapse: collapse; font-size: 0.8rem; }
        .ots-table th { background: #f1f5f9; padding: 0.75rem; text-align: left; position: sticky; top: 0; font-weight: 600; border-bottom: 2px solid #e2e8f0; }
        .ots-table td { padding: 0.75rem; border-bottom: 1px solid #f1f5f9; white-space: nowrap; }
        .ots-table tr:hover { background: #f8fafc; cursor: pointer; }
        .ots-table tr.selected { background: #dbeafe; border-left: 3px solid var(--primary); }
        .badge { padding: 2px 6px; border-radius: 10px; font-size: 0.7rem; color: #fff; font-weight: 600; }
        .b-pen{background:#f59e0b} .b-asi{background:#5fb8d4} .b-pro{background:#10b981} .b-cer{background:#64748b}
        .detail-form label { display: block; font-size: 0.75rem; color: #64748b; margin: 0.5rem 0 0.25rem; }
        .detail-form input, .detail-form select { width: 100%; padding: 0.5rem; border: 1px solid #e2e8f0; border-radius: 0.5rem; font-size: 0.85rem; }
        .detail-actions { display: flex; flex-direction: column; gap: 0.5rem; margin-top: auto; padding-top: 1rem; border-top: 1px solid #e2e8f0; }
        .btn-save { background: #10b981; color: #fff; padding: 0.6rem; border-radius: 0.5rem; border: none; cursor: pointer; }
        .btn-cancel { background: #6b7280; color: #fff; padding: 0.6rem; border-radius: 0.5rem; border: none; cursor: pointer; }
        .hidden { display: none !important; }
        
        .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.5rem; }
        .kpi-card { background: #fff; padding: 1rem; border-radius: 0.75rem; text-align: center; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .kpi-val { font-size: 1.5rem; font-weight: 700; margin: 0.5rem 0; }
        .pills-container { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 1rem; }
        .pill-btn { padding: 0.5rem 1rem; border-radius: 2rem; border: 1px solid #cbd5e1; background: #fff; cursor: pointer; font-size: 0.85rem; transition: all 0.3s; }
        .pill-btn.active { background: var(--primary); color: #fff; border-color: var(--primary); }
        .progress-container { height: 12px; background: #e2e8f0; border-radius: 6px; overflow: hidden; margin-bottom: 1rem; }
        .progress-bar { height: 100%; background: linear-gradient(90deg, #10b981 0%, #fbbf24 50%, #ef4444 100%); width: 0%; transition: width 0.6s ease; }
        .top-list { background: #f8fafc; padding: 1rem; border-radius: 0.75rem; }
        .top-item { display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid #e2e8f0; font-size: 0.85rem; }
        .top-item:last-child { border-bottom: none; }
        
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.4); display: none; justify-content: center; align-items: center; z-index: 200; }
        .modal-overlay.show { display: flex; }
        .modal-box { background: #fff; padding: 1.5rem; border-radius: 1rem; width: 90%; max-width: 500px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
        .modal-actions { display: flex; gap: 0.5rem; margin-top: 1rem; justify-content: flex-end; }
        
        @keyframes slideInUp { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    </style>
</head>
<body>
    <div class="app-background"></div>

    <header class="main-header">
        <div class="header-left">
            <img src="/img/logohospitalantofagasta.jpeg" alt="Hospital" class="header-logo">
            <div>
                <div class="header-module-title">Hospital de Antofagasta</div>
                <div class="header-role"><?php echo htmlspecialchars($user['role_name']); ?></div>
            </div>
            <button class="home-icon-btn" onclick="showModule('home')" title="Volver al Home" style="background:none;border:none;cursor:pointer;padding:0.5rem;margin-left:0.5rem;">
                <svg width="22" height="22" fill="none" stroke="var(--primary)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
            </button>
        </div>
        <div class="header-right">
            <div class="header-datetime"><div id="clock">--:--</div><div style="font-size:0.7rem;">MedicalOT v1.0</div></div>
            <div class="header-user"><div class="user-avatar"><?php echo strtoupper(substr($user['name'], 0, 2)); ?></div><span><?php echo htmlspecialchars($user['name']); ?></span></div>
            <div class="menu-dots"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path></svg></div>
        </div>
    </header>

    <main class="container">
        <!-- HOME -->
        <section id="home" class="module-section active">
            <div style="text-align:center; margin-bottom:1.5rem;">
                <h2 style="color:var(--primary-dark);">Bienvenido al Panel de Control</h2>
                <p style="color:var(--gray-600);">Gestión integral de mantenimiento hospitalario</p>
            </div>
            <div class="home-grid">
                <?php if($isAdmin): ?>
                <div class="home-card" onclick="showModule('carga-sic')">
                    <span class="home-card-icon">📥</span>
                    <div class="home-card-title">Carga SIC</div>
                    <div class="home-card-desc">Importar y validar planificaciones</div>
                </div>
                <?php endif; ?>
                <div class="home-card" onclick="showModule('ots')">
                    <span class="home-card-icon">📋</span>
                    <div class="home-card-title">OTs</div>
                    <div class="home-card-desc">Gestión y seguimiento</div>
                </div>
                <div class="home-card" onclick="showModule('tracking')">
                    <span class="home-card-icon">📡</span>
                    <div class="home-card-title">Tracking</div>
                    <div class="home-card-desc">Avance en terreno</div>
                </div>
                <div class="home-card" onclick="showModule('kpis')">
                    <span class="home-card-icon">📊</span>
                    <div class="home-card-title">KPIs</div>
                    <div class="home-card-desc">Indicadores y métricas</div>
                </div>
                <?php if($isAdmin || $user['role'] === 'admin_cont'): ?>
                <div class="home-card" onclick="showModule('contratistas')">
                    <span class="home-card-icon">🤝</span>
                    <div class="home-card-title">Contratistas</div>
                    <div class="home-card-desc">Mantenedor de proveedores</div>
                </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- CARGA SIC -->
        <?php if($isAdmin): ?>
        <section id="carga-sic" class="module-section">
            <div style="max-width:800px; margin:0 auto;">
                <h3 style="margin-bottom:1rem;">📥 Carga y Validación SIC</h3>
                <div class="upload-zone" id="dropZone">
                    <input type="file" id="sicFile" accept=".csv" style="display:none">
                    <p style="font-weight:600;">Arrastra tu archivo SIC o haz clic aquí</p>
                    <p style="font-size:0.8rem; color:var(--gray-600);">Solo archivos .csv | Máx 50MB</p>
                </div>
                <div id="sicSummary" class="summary-box">
                    <h4>📋 Resumen de Validación</h4>
                    <div id="sicLog" style="font-size:0.9rem; margin:0.5rem 0;"></div>
                    <button class="btn-volver" style="background:var(--primary); margin-top:0.5rem;" onclick="confirmLoad()">✅ Confirmar Carga</button>
                </div>
                <table style="width:100%; margin-top:1.5rem; border-collapse:collapse; background:#fff; border-radius:0.75rem; overflow:hidden;">
                    <thead><tr style="background:#f1f5f9;"><th style="padding:0.75rem; text-align:left;">Fecha</th><th>Hora</th><th>Nuevos</th><th>Omitidos</th></tr></thead>
                    <tbody id="loadHistory"></tbody>
                </table>
                <button class="btn-volver" onclick="showModule('home')">🏠 Volver a Home</button>
            </div>
        </section>
        <?php endif; ?>

        <!-- OTs -->
        <section id="ots" class="module-section">
            <div class="ots-layout">
                <div class="ots-left">
                    <div style="background:#fff; padding:1rem; border-radius:0.75rem; border:1px solid #e2e8f0;">
                        <div class="search-container">
                            <input type="text" class="search-input" id="otSearch" placeholder="🔍 Buscar OT, protocolo, equipo, área..." oninput="handleSearch()">
                            <div class="search-dropdown" id="searchDropdown"></div>
                        </div>
                        <div class="filters-row">
                            <select id="fEsp"><option>Todas Especialidades</option><option>M-CLIMATIZACION</option><option>M-ELECTROMECANICA</option></select>
                            <select id="fEstado"><option>Todos Estados</option><option>pendiente</option><option>asignada</option><option>en_proceso</option><option>cerrada</option></select>
                            <select id="fMes"><option>Todos Meses</option><option>Enero</option><option>Febrero</option><option>Marzo</option></select>
                            <select id="fGrupo"><option>Todos Grupos</option><option>Pool ClimA</option><option>Pool ElecB</option></select>
                            <select id="fTipo"><option>Todos Tipos</option><option>Preventiva</option><option>Correctiva</option></select>
                        </div>
                    </div>
                    <div class="table-scroll-wrapper">
                        <table class="ots-table">
                            <thead><tr><th>OT</th><th>Fecha</th><th>ID SIC</th><th>Protocolo</th><th>Área</th><th>Equipo</th><th>Especialidad</th><th>HH</th><th>Grupo</th><th>Estado</th></tr></thead>
                            <tbody id="otsTableBody"></tbody>
                        </table>
                    </div>
                </div>
                <div class="ots-right detail-form" id="otDetailPanel">
                    <h4 style="margin-bottom:1rem;">📝 Detalle / Edición</h4>
                    <div id="otDetailContent"><p style="color:#94a3b8; text-align:center; margin-top:2rem;">Selecciona una OT para ver detalles</p></div>
                    <div class="detail-actions" id="otActions" style="display:none;">
                        <button class="btn-save" onclick="alert('Guardado correctamente')">💾 Guardar</button>
                        <button class="btn-cancel" onclick="clearDetail()">❌ Cancelar</button>
                        <button class="btn-volver" style="background:#64748b; margin-top:0;" onclick="showModule('home')">🏠 Volver a Home</button>
                    </div>
                </div>
            </div>
        </section>

        <!-- TRACKING -->
        <section id="tracking" class="module-section">
            <div style="max-width:900px; margin:0 auto;">
                <h3 style="margin-bottom:1rem;">📡 Tracking en Terreno</h3>
                <div style="display:flex; gap:1rem; margin-bottom:1.5rem;">
                    <select style="padding:0.5rem; border-radius:0.5rem; border:1px solid #e2e8f0; flex:1;"><option>Filtrar por Estado</option><option>en_proceso</option><option>pausada</option><option>cerrada</option></select>
                    <select style="padding:0.5rem; border-radius:0.5rem; border:1px solid #e2e8f0; flex:2;"><option>Seleccionar OT</option><option>OT-2026-003 - Estanque Criogénico</option><option>OT-2026-007 - Torres Enfriamiento</option></select>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.5rem;">
                    <div style="background:#fff; padding:1.5rem; border-radius:0.75rem; border:1px solid #e2e8f0;">
                        <h4>Progreso & Timeline</h4>
                        <div class="progress-container" style="margin:1rem 0;"><div class="progress-bar" style="width:65%;"></div></div>
                        <p style="font-size:0.85rem; margin-bottom:0.5rem;"><strong>65%</strong> completado - 18.2 HH / 28.0 HH</p>
                        <div style="border-left:2px solid #e2e8f0; padding-left:1rem; margin:1rem 0;">
                            <div style="margin-bottom:1rem;"><div style="font-size:0.75rem; color:#64748b;">10/05/2026 08:30</div><div>Carga desde SIC - Admin Hospital</div></div>
                            <div style="margin-bottom:1rem;"><div style="font-size:0.75rem; color:#64748b;">11/05/2026 09:15</div><div>Asignado a Pool ClimA - Sup. Pérez</div></div>
                            <div style="margin-bottom:1rem;"><div style="font-size:0.75rem; color:#64748b;">12/05/2026 10:00</div><div>Inicio trabajo - Tec. Juan López</div></div>
                        </div>
                    </div>
                    <div style="background:#fff; padding:1.5rem; border-radius:0.75rem; border:1px solid #e2e8f0;">
                        <h4>Evidencias & Incidencias</h4>
                        <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:0.5rem; margin:1rem 0;">
                            <div style="background:#f1f5f9; border-radius:0.5rem; height:60px; display:flex; align-items:center; justify-content:center; font-size:0.8rem; color:#64748b; border:1px dashed #cbd5e1;">📷 Foto 1</div>
                            <div style="background:#f1f5f9; border-radius:0.5rem; height:60px; display:flex; align-items:center; justify-content:center; font-size:0.8rem; color:#64748b; border:1px dashed #cbd5e1;">📄 PDF</div>
                            <div style="background:#f1f5f9; border-radius:0.5rem; height:60px; display:flex; align-items:center; justify-content:center; font-size:0.8rem; color:#64748b; border:1px dashed #cbd5e1;">➕ Agregar</div>
                        </div>
                        <button style="background:var(--primary); color:#fff; padding:0.6rem 1rem; border-radius:0.5rem; border:none; cursor:pointer; display:flex; align-items:center; gap:0.5rem; margin:1rem 0; width:100%; justify-content:center;">⚠️ Reportar Incidencia</button>
                        <table style="width:100%; border-collapse:collapse; font-size:0.8rem; margin-top:0.5rem;">
                            <thead><tr><th style="padding:0.5rem; border:1px solid #e2e8f0; text-align:left;">Fecha</th><th style="padding:0.5rem; border:1px solid #e2e8f0; text-align:left;">Tipo</th><th style="padding:0.5rem; border:1px solid #e2e8f0; text-align:left;">Descripción</th></tr></thead>
                            <tbody><tr><td style="padding:0.5rem; border:1px solid #e2e8f0;">12/05 14:20</td><td style="padding:0.5rem; border:1px solid #e2e8f0; color:#f59e0b;">Material</td><td style="padding:0.5rem; border:1px solid #e2e8f0;">Falta filtro HEPA repuesto</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <!-- KPIs -->
        <section id="kpis" class="module-section">
            <div style="max-width:900px; margin:0 auto;">
                <h3 style="margin-bottom:1rem;">📊 Indicadores de Gestión</h3>
                <div class="kpi-grid">
                    <div class="kpi-card"><div style="font-size:0.8rem; color:#64748b;">SLA Cumplimiento</div><div class="kpi-val" style="color:#10b981;">94%</div></div>
                    <div class="kpi-card"><div style="font-size:0.8rem; color:#64748b;">HH Presup/Real</div><div class="kpi-val" style="color:#f59e0b;">102%</div></div>
                    <div class="kpi-card"><div style="font-size:0.8rem; color:#64748b;">OTs Cerradas/Mes</div><div class="kpi-val">47</div></div>
                    <div class="kpi-card"><div style="font-size:0.8rem; color:#64748b;">En Proceso</div><div class="kpi-val" style="color:var(--primary);">12</div></div>
                </div>
                <h4 style="margin-bottom:0.5rem;">Distribución de HH por Categoría</h4>
                <div class="pills-container">
                    <button class="pill-btn active" onclick="updateKPIs(this, 'Especialidad')">Especialidad</button>
                    <button class="pill-btn" onclick="updateKPIs(this, 'Área')">Área</button>
                    <button class="pill-btn" onclick="updateKPIs(this, 'Equipo')">Equipo</button>
                </div>
                <div class="progress-container"><div class="progress-bar" id="kpiBar" style="width:65%;"></div></div>
                <div class="top-list" id="topList"></div>
            </div>
        </section>

        <!-- CONTRATISTAS -->
        <section id="contratistas" class="module-section">
            <div style="max-width:900px; margin:0 auto;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                    <h3>🤝 Mantenedor de Contratistas</h3>
                    <button style="background:var(--primary); color:#fff; padding:0.6rem 1rem; border-radius:0.5rem; border:none; cursor:pointer; display:flex; align-items:center; gap:0.5rem;" onclick="openModal()">➕ Nuevo Contratista</button>
                </div>
                <table style="width:100%; background:#fff; border-radius:0.75rem; overflow:hidden; border-collapse:collapse;">
                    <thead><tr style="background:#f1f5f9;"><th style="padding:0.75rem; text-align:left;">RUT</th><th>Razón Social</th><th>Especialidad</th><th>Contacto</th><th>Acciones</th></tr></thead>
                    <tbody>
                        <tr><td style="padding:0.75rem;">76.543.210-K</td><td>Servicios ClimaSpa</td><td>M-CLIMATIZACION</td><td>contacto@clima.cl</td><td><button style="padding:0.25rem 0.5rem; cursor:pointer;" onclick="openModal('edit')">✏️</button> <button style="padding:0.25rem 0.5rem; cursor:pointer; color:#ef4444;">🗑️</button></td></tr>
                        <tr><td style="padding:0.75rem;">96.876.540-1</td><td>Mantenciones Valdivia</td><td>M-ELECTROMECANICA</td><td>admin@valdivia.cl</td><td><button style="padding:0.25rem 0.5rem; cursor:pointer;" onclick="openModal('edit')">✏️</button> <button style="padding:0.25rem 0.5rem; cursor:pointer; color:#ef4444;">🗑️</button></td></tr>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <footer class="main-footer">
        <img src="/img/logomedicalot.png" alt="MedicalOT" style="height:24px; opacity:0.8;">
        <span>© 2026 Hospital de Antofagasta - Sistema MedicalOT</span>
    </footer>

    <div class="modal-overlay" id="contratistaModal">
        <div class="modal-box">
            <h3 id="modalTitle">Nuevo Contratista</h3>
            <div style="display:grid; gap:0.75rem; margin-top:1rem;">
                <input placeholder="RUT" style="padding:0.5rem; border:1px solid #e2e8f0; border-radius:0.5rem;">
                <input placeholder="Razón Social" style="padding:0.5rem; border:1px solid #e2e8f0; border-radius:0.5rem;">
                <select style="padding:0.5rem; border:1px solid #e2e8f0; border-radius:0.5rem;"><option>Seleccionar Especialidad</option><option>M-CLIMATIZACION</option><option>M-GASFITERIA</option></select>
                <input placeholder="Email Contacto" style="padding:0.5rem; border:1px solid #e2e8f0; border-radius:0.5rem;">
            </div>
            <div class="modal-actions">
                <button class="btn-cancel" onclick="closeModal()">Cancelar</button>
                <button class="btn-save" onclick="closeModal(); Toast.success('Contratista guardado correctamente')">Guardar</button>
            </div>
        </div>
    </div>

    <script>
        // DATOS MOCK (Se reemplazarán con fetch a la BD en producción)
        const otsData = [
            {id:'OT-2026-001', fecha:'11/05/2026', idSic:'396562', proto:'IA11', area:'Correo Central', equipo:'Transferencia', esp:'M-POLIVALENTE', hh:1.33, grupo:'Pool PoliA', estado:'pendiente'},
            {id:'OT-2026-002', fecha:'12/05/2026', idSic:'397888', proto:'I713', area:'Lab. Inmunohematología', equipo:'Tanque N2', esp:'M-ELECTROMECANICA', hh:2.0, grupo:'Pool ElecB', estado:'asignada'},
            {id:'OT-2026-003', fecha:'14/05/2026', idSic:'402710', proto:'I106', area:'Pabellones', equipo:'Revestimientos', esp:'M-POLIVALENTE', hh:2.0, grupo:'Pool PoliA', estado:'en_proceso'}
        ];
        const top5Data = {
            'Especialidad': [['M-CLIMATIZACION', '45%'], ['M-ELECTROMECANICA', '25%'], ['M-GASFITERIA', '15%'], ['M-POLIVALENTE', '10%'], ['OTROS', '5%']],
            'Área': [['Pabellones Quirúrgicos', '30%'], ['UCI / UCI Pediátrica', '20%'], ['Laboratorios Clínicos', '15%'], ['Central Alimentos', '10%'], ['Administración', '5%']],
            'Equipo': [['Fancoils & Splits', '40%'], ['Chillers & Bombas', '25%'], ['UMAs & Ductos', '15%'], ['Torres Enfriamiento', '10%'], ['Cámaras Frío', '10%']]
        };

        // NAVEGACIÓN
        function showModule(id) {
            document.querySelectorAll('.module-section').forEach(s => s.classList.remove('active'));
            document.getElementById(id).classList.add('active');
            if(id === 'ots') renderOTs();
            if(id === 'kpis') updateKPIs(document.querySelector('.pill-btn.active'), 'Especialidad');
        }

        // RELOJ
        setInterval(() => {
            document.getElementById('clock').textContent = new Date().toLocaleString('es-CL', {
                weekday:'short', year:'numeric', month:'short', day:'numeric', hour:'2-digit', minute:'2-digit'
            });
        }, 1000);

        // TOAST SYSTEM
        const Toast = {
            container: null,
            init() {
                if (!this.container) {
                    this.container = document.createElement('div');
                    this.container.className = 'toast-container';
                    this.container.style.cssText = 'position:fixed;top:100px;right:1.5rem;z-index:9999;display:flex;flex-direction:column;gap:0.75rem;max-width:400px;';
                    document.body.appendChild(this.container);
                }
            },
            show(message, type='info', title=null, duration=5000) {
                this.init();
                const toast = document.createElement('div');
                toast.className = `toast ${type}`;
                toast.style.cssText = 'background:#fff;border-radius:0.75rem;box-shadow:0 10px 15px rgba(0,0,0,0.1);padding:1rem 1.25rem;display:flex;align-items:flex-start;gap:0.875rem;min-width:320px;border-left:4px solid;animation:slideInRight 0.4s ease;';
                const colors = {success:'#10b981',error:'#ef4444',warning:'#f59e0b',info:'#3b82f6'};
                toast.style.borderLeftColor = colors[type];
                toast.innerHTML = `
                    <div style="flex:1;"><div style="font-weight:600;font-size:0.95rem;margin-bottom:0.25rem;color:#1e293b;">${title || (type==='success'?'✅ Éxito':'⚠️ Aviso')}</div><div style="font-size:0.85rem;color:#64748b;">${message}</div></div>
                    <button onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;padding:0.25rem;color:#94a3b8;">✕</button>`;
                this.container.appendChild(toast);
                setTimeout(() => { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 300); }, duration);
            },
            success(m, t) { this.show(m, 'success', t); },
            error(m, t) { this.show(m, 'error', t); }
        };

        // CARGA SIC
        const dropZone = document.getElementById('dropZone');
        if(dropZone) {
            dropZone.addEventListener('click', () => document.getElementById('sicFile').click());
            dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('dragover'); });
            dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
            dropZone.addEventListener('drop', e => { e.preventDefault(); dropZone.classList.remove('dragover'); handleFile(e.dataTransfer.files[0]); });
            document.getElementById('sicFile').addEventListener('change', e => handleFile(e.target.files[0]));
        }

        async function handleFile(file) {
            if (!file) return;
            
            // Extracción robusta de extensión
            const fileName = file.name.trim();
            const ext = fileName.split('.').pop().toLowerCase();
            
            // 🐛 LOG DE DEBUGGING (Revisa la consola del navegador con F12)
            console.log('🔍 Extensión detectada:', ext, '| Nombre real:', fileName, '| Tamaño:', file.size);
            
            if (ext !== 'csv') {
                Toast.error(`Formato no válido. Se detectó: "${ext}". Solo se aceptan archivos .csv`);
                return;
            }
            
            const summary = document.getElementById('sicSummary');
            const log = document.getElementById('sicLog');
            log.innerHTML = `<p>📤 Enviando ${fileName} al servidor...</p>`;
            summary.classList.add('show');

            const formData = new FormData();
            formData.append('sicFile', file);

            try {
                const res = await fetch('/api/import_sic.php', { method: 'POST', body: formData });
                const rawText = await res.text();

                let data;
                try {
                    data = JSON.parse(rawText);
                } catch (jsonErr) {
                    console.error("❌ Respuesta no JSON recibida:", rawText.substring(0, 300));
                    throw new Error('El servidor respondió con HTML en lugar de JSON. Revisa los logs de Railway.');
                }

                if (!data.success && data.error) {
                    throw new Error(data.error);
                }
                if (!res.ok) {
                    throw new Error(`Error HTTP: ${res.status}`);
                }

                log.innerHTML = `
                    <p style="color:#10b981;">✅ Archivo recibido y procesado</p>
                    <p>🔍 Validando integridad y columnas SIC...</p>
                    <p>🆔 Revisando duplicados por hash...</p>
                    <p style="color:#10b981;">✅ ${data.inserted} registros nuevos importados.</p>
                    ${data.skipped > 0 ? `<p style="color:#f59e0b;">⚠️ ${data.skipped} registros omitidos (duplicados).</p>` : ''}
                    ${data.errors && data.errors.length > 0 ? `<p style="color:#ef4444;">❌ ${data.errors.length} errores en filas específicas.</p>` : ''}
                `;
                
                const tbody = document.getElementById('loadHistory');
                const now = new Date();
                tbody.insertAdjacentHTML('afterbegin', 
                    `<tr><td style="padding:0.75rem;">${now.toLocaleDateString()}</td><td>${now.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'})}</td>
                    <td style="color:#10b981; font-weight:600;">${data.inserted}</td>
                    <td style="color:#f59e0b;">${data.skipped}</td></tr>`
                );
                Toast.success(`Carga completada: ${data.inserted} nuevos, ${data.skipped} omitidos`);
                document.getElementById('sicFile').value = '';

            } catch (err) {
                log.innerHTML = `<p style="color:#ef4444;">❌ Error: ${err.message}</p>`;
                Toast.error(err.message, 'Carga Fallida');
            }
        }

        function confirmLoad() {
            document.getElementById('sicSummary').classList.remove('show');
            Toast.success('Carga confirmada y registrada en historial');
        }

        // OTs LOGIC
        function renderOTs() {
            document.getElementById('otsTableBody').innerHTML = otsData.map(o => `
                <tr onclick="selectOT(this, '${o.id}')">
                    <td>${o.id}</td><td>${o.fecha}</td><td>${o.idSic}</td><td>${o.proto}</td><td>${o.area}</td><td>${o.equipo}</td><td>${o.esp}</td><td>${o.hh}</td><td>${o.grupo}</td>
                    <td><span class="badge b-${o.estado.replace('en_proceso','pro').replace('asignada','asi').replace('pendiente','pen').replace('cerrada','cer')}">${o.estado}</span></td>
                </tr>`).join('');
        }

        function handleSearch() {
            const val = document.getElementById('otSearch').value.toLowerCase();
            const dd = document.getElementById('searchDropdown');
            if(!val) { dd.classList.remove('show'); return; }
            const matches = otsData.filter(o => Object.values(o).join(' ').toLowerCase().includes(val));
            dd.innerHTML = matches.map(o => `<div class="search-item" onclick="selectOTById('${o.id}'); dd.classList.remove('show');">${o.id} - ${o.equipo} (${o.esp})</div>`).join('');
            dd.classList.add('show');
        }

        function selectOTById(id) {
            const row = document.querySelector(`tr[onclick*="${id}"]`);
            if(row) selectOT(row, id);
        }

        function selectOT(row, id) {
            document.querySelectorAll('.ots-table tr').forEach(r => r.classList.remove('selected'));
            row.classList.add('selected');
            const o = otsData.find(x => x.id === id);
            document.getElementById('otDetailContent').innerHTML = `
                <label>ID SIC</label><input value="${o.idSic}" readonly>
                <label>Protocolo / Equipo</label><input value="${o.proto} - ${o.equipo}">
                <label>HH Asignadas</label><input type="number" value="${o.hh}">
                <label>Estado</label><select><option ${o.estado==='pendiente'?'selected':''}>pendiente</option><option ${o.estado==='asignada'?'selected':''}>asignada</option><option ${o.estado==='en_proceso'?'selected':''}>en_proceso</option><option ${o.estado==='cerrada'?'selected':''}>cerrada</option></select>
            `;
            document.getElementById('otActions').style.display = 'flex';
        }

        function clearDetail() {
            document.getElementById('otDetailContent').innerHTML = '<p style="color:#94a3b8; text-align:center; margin-top:2rem;">Selecciona una OT para ver detalles</p>';
            document.getElementById('otActions').style.display = 'none';
            document.querySelectorAll('.ots-table tr').forEach(r => r.classList.remove('selected'));
        }

        // KPIs
        function updateKPIs(btn, cat) {
            document.querySelectorAll('.pill-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            const width = cat === 'Especialidad' ? 65 : cat === 'Área' ? 42 : 28;
            document.getElementById('kpiBar').style.width = width + '%';
            document.getElementById('topList').innerHTML = `<h5 style="margin-bottom:0.5rem; color:#334155;">Top 5 ${cat}</h5>` + 
                top5Data[cat].map(item => `<div class="top-item"><span>${item[0]}</span><strong>${item[1]}</strong></div>`).join('');
        }

        // CONTRATISTAS MODAL
        function openModal(mode) {
            document.getElementById('contratistaModal').classList.add('show');
            document.getElementById('modalTitle').textContent = mode === 'edit' ? 'Editar Contratista' : 'Nuevo Contratista';
        }
        function closeModal() { document.getElementById('contratistaModal').classList.remove('show'); }

        // INIT
        renderOTs();
        updateKPIs(document.querySelector('.pill-btn.active'), 'Especialidad');
        Toast.init();
    </script>
</body>
</html>