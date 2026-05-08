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
                /* ✅ LAYOUT BASE (Flexbox contenedor) */
        body { display: flex; flex-direction: column; min-height: 100vh; margin: 0; }
        .main-header { flex-shrink: 0; position: sticky; top: 0; z-index: 100; background: #fff; border-bottom: 2px solid var(--primary); padding: 0.75rem 1.5rem; display:flex; justify-content:space-between; align-items:center; }
        main.container { flex: 1; display: flex; flex-direction: column; min-height: 0; padding: 1.25rem; overflow: hidden; }
        .module-section { flex: 1; display: none; min-height: 0; overflow: hidden; }
        .module-section.active { display: flex; flex-direction: column; }
        
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
        
        /* ✅ OTs LAYOUT 80/20 (Scroll 100% aislado) */
        .ots-layout { display: grid; grid-template-columns: 78% 22%; gap: 1rem; flex: 1; min-height: 0; overflow: hidden; }
        .ots-left { display: grid; grid-template-rows: auto 1fr; gap: 0.75rem; min-height: 0; overflow: hidden; }
        
        /* ✅ TABLA SCROLLABLE INDEPENDIENTE */
        .table-scroll-wrapper { flex: 1; overflow-y: auto; overflow-x: auto; border: 1px solid #e2e8f0; border-radius: 0.5rem; background: #fff; min-height: 0; }
        .ots-table { width: 100%; min-width: 1300px; border-collapse: collapse; font-size: 0.8rem; }
        .ots-table th { background: #f1f5f9; padding: 0.7rem; text-align: left; position: sticky; top: 0; font-weight: 600; border-bottom: 2px solid #e2e8f0; z-index: 5; }
        .ots-table td { padding: 0.7rem; border-bottom: 1px solid #f1f5f9; white-space: nowrap; }
        .ots-table tr:hover { background: #f8fafc; cursor: pointer; }
        .ots-table tr.selected { background: #dbeafe; border-left: 3px solid var(--primary); }
        
        /* ✅ PANEL DERECHO 20% (Bordes redondeados + padding interno) */
        .ots-right { background: #fff; border-radius: 0.75rem; border: 1px solid #e2e8f0; display: flex; flex-direction: column; overflow: hidden; padding: 0.75rem 1rem; }
        .ots-right .detail-form { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
        #otDetailContent { flex: 1; overflow-y: auto; padding: 0 0.5rem; }
        .detail-actions { flex-shrink: 0; margin-top: auto; padding-top: 1rem; border-top: 1px solid #e2e8f0; display: flex; flex-direction: column; gap: 0.5rem; }
        
        .filters-panel { background: #fff; padding: 1rem; border-radius: 0.75rem; border: 1px solid #e2e8f0; }
        .search-container { position: relative; margin-bottom: 0.75rem; }
        .search-input { width: 100%; padding: 0.75rem 1rem; border: 2px solid #e2e8f0; border-radius: 0.75rem; font-size: 0.95rem; }
        .search-input:focus { border-color: var(--primary); outline: none; }
        .search-dropdown { position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #e2e8f0; border-radius: 0.5rem; max-height: 220px; overflow-y: auto; z-index: 50; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); margin-top: 4px; display: none; }
        .search-dropdown.show { display: block; }
        .search-item { padding: 0.75rem 1rem; cursor: pointer; border-bottom: 1px solid #f1f5f9; background: #fff; color: #156b7d; font-weight: 500; font-size: 0.9rem; }
        .search-item:hover { background: #f8fafc; }
        .filters-row { display: grid; grid-template-columns: repeat(3, 1fr) auto; gap: 0.5rem; align-items: end; }
        .filters-row select { padding: 0.5rem; border: 1px solid #e2e8f0; border-radius: 0.5rem; font-size: 0.85rem; background: #f8fafc; }
        .pagination-controls { display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; color: #475569; }
        .btn-nav { padding: 0.4rem 0.8rem; background: #e2e8f0; border: none; border-radius: 0.5rem; cursor: pointer; font-size: 0.8rem; transition: all 0.2s; }
        .btn-nav:hover:not(:disabled) { background: var(--primary); color: #fff; }
        .btn-nav:disabled { opacity: 0.5; cursor: not-allowed; background: #f1f5f9; }
        
        .badge { padding: 2px 6px; border-radius: 10px; font-size: 0.7rem; color: #fff; font-weight: 600; }
        .b-pen{background:#f59e0b} .b-asi{background:#5fb8d4} .b-pro{background:#10b981} .b-cer{background:#64748b}
        .detail-form label { display: block; font-size: 0.75rem; color: #64748b; margin: 0.5rem 0 0.25rem; }
        .detail-form input, .detail-form select, .detail-form textarea { width: 100%; padding: 0.5rem; border: 1px solid #e2e8f0; border-radius: 0.5rem; font-size: 0.85rem; }
        .btn-save { background: #10b981; color: #fff; padding: 0.6rem; border-radius: 0.5rem; border: none; cursor: pointer; }
        .btn-cancel { background: #6b7280; color: #fff; padding: 0.6rem; border-radius: 0.5rem; border: none; cursor: pointer; }
        
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
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        
        /* ✅ BITÁCORA: Espacio inferior extra */
        #loadHistory { margin-bottom: 0; }
        #loadHistory tbody { display: table; width: 100%; table-layout: fixed; }
        #loadHistory tr:last-child td { padding-bottom: 1.25rem; }
        
        .logout-btn { background: none; border: none; cursor: pointer; padding: 0.5rem; border-radius: 0.5rem; transition: background 0.3s ease; }
        .logout-btn:hover { background: #fee2e2; }
        .logout-btn svg { width: 20px; height: 20px; color: #ef4444; }
        
        .import-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255,255,255,0.9); backdrop-filter: blur(3px); z-index: 9998; flex-direction: column; align-items: center; justify-content: center; }
        .import-overlay.active { display: flex; animation: fadeIn 0.2s ease; }
        .spinner { width: 48px; height: 48px; border: 4px solid #e2e8f0; border-top: 4px solid var(--primary); border-radius: 50%; animation: spin 0.8s linear infinite; margin-bottom: 1.25rem; }
        .import-text { color: #1e293b; font-weight: 600; font-size: 1rem; }
        .import-sub { color: #64748b; font-size: 0.85rem; margin-top: 0.4rem; }
        
        .main-footer { flex-shrink: 0; margin-top: auto; background: #fff; border-top: 1px solid #e2e8f0; padding: 0.75rem; text-align: center; font-size: 0.8rem; color: #475569; display: flex; align-items: center; justify-content: center; gap: 0.5rem; box-shadow: 0 -2px 8px rgba(0,0,0,0.03); }
        .main-footer img { height: 24px; opacity: 0.8; transition: opacity 0.3s; }
        .main-footer img:hover { opacity: 1; }
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
            <button class="home-icon-btn" onclick="showModule('home')" title="Volver al Home">
                <svg width="22" height="22" fill="none" stroke="var(--primary)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
            </button>
        </div>
        <div class="header-right">
            <div class="header-datetime"><div id="clock">--:--</div><div style="font-size:0.7rem;">MedicalOT v1.0</div></div>
            <button class="logout-btn" onclick="logout()" title="Cerrar Sesión"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg></button>
            <div class="menu-dots"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path></svg></div>
            <div class="header-user"><div class="user-avatar"><?php echo strtoupper(substr($user['name'], 0, 2)); ?></div><span><?php echo htmlspecialchars($user['name']); ?></span></div>
        </div>
    </header>

    <main class="container">
        <section id="home" class="module-section active">
             <!-- Bienvenida -->
            <div class="card" style="margin-bottom: 2rem;">
                <div class="card-body" style="text-align: center; padding: 3rem;">
                    <img src="/img/logohospitalantofagasta.jpeg" alt="Hospital" style="width: 80px; height: 80px; object-fit: contain; margin-bottom: 1rem; opacity: 0.8;">
                    <h2 style="font-size: 2rem; color: var(--primary-dark); margin-bottom: 0.5rem;">
                        Bienvenido a MedicalOT v.1
                    </h2>
                    <p style="color: var(--gray-600); font-size: 1.1rem; margin-bottom: 1.5rem;">
                        Sistema de Gestión de Órdenes de Trabajo Hospitalarias
                    </p>
                    <div class="pulse-line" style="max-width: 400px; margin: 2rem auto;"></div>
                    <p style="color: var(--gray-700);">
                        <strong><?php echo htmlspecialchars($user['name']); ?></strong> - 
                        <?php echo htmlspecialchars($user['role_name']); ?>
                    </p>
                </div>
            </div>
            <div class="home-grid">
                <?php if($isAdmin): ?>
                <div class="home-card" onclick="showModule('carga-sic')"><span class="home-card-icon">📥</span><div class="home-card-title">Carga SIC</div><div class="home-card-desc">Importar y validar planificaciones</div></div>
                <?php endif; ?>
                <div class="home-card" onclick="showModule('ots')"><span class="home-card-icon">📋</span><div class="home-card-title">OTs</div><div class="home-card-desc">Gestión y seguimiento</div></div>
                <div class="home-card" onclick="showModule('tracking')"><span class="home-card-icon">📡</span><div class="home-card-title">Tracking</div><div class="home-card-desc">Avance en terreno</div></div>
                <div class="home-card" onclick="showModule('kpis')"><span class="home-card-icon">📊</span><div class="home-card-title">KPIs</div><div class="home-card-desc">Indicadores y métricas</div></div>
                <?php if($isAdmin || $user['role'] === 'admin_cont'): ?>
                <div class="home-card" onclick="showModule('contratistas')"><span class="home-card-icon">🤝</span><div class="home-card-title">Contratistas</div><div class="home-card-desc">Mantenedor de proveedores</div></div>
                <?php endif; ?>
            </div>
        </section>

        <?php if($isAdmin): ?>
        <section id="carga-sic" class="module-section">
            <div style="max-width:800px; margin:0 auto;">
                <h3 style="margin-bottom:1rem;">📥 Carga y Validación SIC</h3>
                <div class="upload-zone" id="dropZone"><input type="file" id="sicFile" accept=".csv" style="display:none"><p style="font-weight:600;">Arrastra tu archivo SIC o haz clic aquí</p><p style="font-size:0.8rem; color:var(--gray-600);">Solo archivos .csv | Máx 50MB</p></div>
                <div id="sicSummary" class="summary-box"><h4>📋 Resumen de Validación</h4><div id="sicLog" style="font-size:0.9rem; margin:0.5rem 0;"></div><button class="btn-volver" style="background:var(--primary); margin-top:0.5rem;" onclick="confirmLoad()">✅ Confirmar Carga</button></div>
                <table style="width:100%; margin-top:1.5rem; border-collapse:collapse; background:#fff; border-radius:0.75rem; overflow:hidden;"><thead><tr style="background:#f1f5f9;"><th style="padding:0.75rem; text-align:center;">Fecha</th><th style="text-align:center;">Hora</th><th style="text-align:center;">Nuevas</th><th style="text-align:center;">Omitidas</th></tr></thead><tbody id="loadHistory"></tbody></table>
                <button class="btn-volver" onclick="showModule('home')">🏠 Volver a Home</button>
            </div>
        </section>
        <?php endif; ?>

        <section id="ots" class="module-section">
            <div class="ots-layout">
                <div class="ots-left">
                    <div class="card filters-panel">
                        <div class="search-container"><input type="text" class="search-input" id="otSearch" placeholder="🔍 Buscar por OT, protocolo, equipo, área..." oninput="handleSearch()"><div class="search-dropdown" id="searchDropdown"></div></div>
                        <div class="filters-row">
                            <select id="fEsp" onchange="applyFilters()"><option value="">Todas Especialidades</option></select>
                            <select id="fEstado" onchange="applyFilters()"><option value="">Todos Estados</option><option value="pendiente">pendiente</option><option value="asignada">asignada</option><option value="en_proceso">en_proceso</option><option value="cerrada">cerrada</option></select>
                            <select id="fMes" onchange="applyFilters()"><option value="">Todos Meses</option><option value="enero">Enero</option><option value="febrero">Febrero</option><option value="marzo">Marzo</option><option value="abril">Abril</option><option value="mayo">Mayo</option><option value="junio">Junio</option><option value="julio">Julio</option><option value="agosto">Agosto</option><option value="septiembre">Septiembre</option><option value="octubre">Octubre</option><option value="noviembre">Noviembre</option><option value="diciembre">Diciembre</option></select>
                            <div class="pagination-controls"><button class="btn-nav" id="prevPage" onclick="changePage(-1)" disabled>◀ Anterior</button><span id="pageInfo">-</span><button class="btn-nav" id="nextPage" onclick="changePage(1)">Siguiente ▶</button></div>
                        </div>
                    </div>
                    <div class="table-scroll-wrapper">
                        <table class="ots-table"><thead><tr><th>OT</th><th>Fecha</th><th>ID SIC</th><th>Protocolo</th><th>Familia</th><th>Periodicidad</th><th>Nombre</th><th>Área</th><th>Equipo</th><th>Serie</th><th>Ubicación</th><th>Especialidad</th><th>Proveedor</th><th>HH</th><th>Estado</th></tr></thead><tbody id="otsTableBody"><tr><td colspan="15" style="text-align:center; padding:2rem; color:#64748b;">Cargando datos...</td></tr></tbody></table>
                    </div>
                </div>
                <div class="ots-right detail-form" id="otDetailPanel">
                    <h4 style="margin-bottom:1rem;">📝 Detalle / Edición OT</h4>
                    <div id="otDetailContent"><p style="color:#94a3b8; text-align:center; margin-top:2rem;">Selecciona una OT para ver detalles</p></div>
                    <div class="detail-actions" id="otActions" style="display:none;"><button class="btn-save" onclick="saveOT()">💾 Guardar</button><button class="btn-cancel" onclick="clearDetail()">❌ Cancelar</button><button class="btn-volver" style="background:#64748b; margin-top:0;" onclick="showModule('home')">🏠 Volver a Home</button></div>
                </div>
            </div>
        </section>

        <section id="tracking" class="module-section">
            <div style="max-width:900px; margin:0 auto;">
                <h3 style="margin-bottom:1rem;">📡 Tracking en Terreno</h3>
                <div style="display:flex; gap:1rem; margin-bottom:1.5rem;"><select style="padding:0.5rem; border-radius:0.5rem; border:1px solid #e2e8f0; flex:1;"><option>Filtrar por Estado</option><option>en_proceso</option><option>pausada</option><option>cerrada</option></select><select style="padding:0.5rem; border-radius:0.5rem; border:1px solid #e2e8f0; flex:2;"><option>Seleccionar OT</option><option>OT-2026-003 - Estanque Criogénico</option><option>OT-2026-007 - Torres Enfriamiento</option></select></div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.5rem;">
                    <div style="background:#fff; padding:1.5rem; border-radius:0.75rem; border:1px solid #e2e8f0;"><h4>Progreso & Timeline</h4><div class="progress-container" style="margin:1rem 0;"><div class="progress-bar" style="width:65%;"></div></div><p style="font-size:0.85rem; margin-bottom:0.5rem;"><strong>65%</strong> completado - 18.2 HH / 28.0 HH</p><div style="border-left:2px solid #e2e8f0; padding-left:1rem; margin:1rem 0;"><div style="margin-bottom:1rem;"><div style="font-size:0.75rem; color:#64748b;">10/05/2026 08:30</div><div>Carga desde SIC - Admin Hospital</div></div><div style="margin-bottom:1rem;"><div style="font-size:0.75rem; color:#64748b;">11/05/2026 09:15</div><div>Asignado a Pool ClimA - Sup. Pérez</div></div><div style="margin-bottom:1rem;"><div style="font-size:0.75rem; color:#64748b;">12/05/2026 10:00</div><div>Inicio trabajo - Tec. Juan López</div></div></div></div>
                    <div style="background:#fff; padding:1.5rem; border-radius:0.75rem; border:1px solid #e2e8f0;"><h4>Evidencias & Incidencias</h4><div style="display:grid; grid-template-columns:repeat(3,1fr); gap:0.5rem; margin:1rem 0;"><div style="background:#f1f5f9; border-radius:0.5rem; height:60px; display:flex; align-items:center; justify-content:center; font-size:0.8rem; color:#64748b; border:1px dashed #cbd5e1;">📷 Foto 1</div><div style="background:#f1f5f9; border-radius:0.5rem; height:60px; display:flex; align-items:center; justify-content:center; font-size:0.8rem; color:#64748b; border:1px dashed #cbd5e1;">📄 PDF</div><div style="background:#f1f5f9; border-radius:0.5rem; height:60px; display:flex; align-items:center; justify-content:center; font-size:0.8rem; color:#64748b; border:1px dashed #cbd5e1;">➕ Agregar</div></div><button style="background:var(--primary); color:#fff; padding:0.6rem 1rem; border-radius:0.5rem; border:none; cursor:pointer; display:flex; align-items:center; gap:0.5rem; margin:1rem 0; width:100%; justify-content:center;">⚠️ Reportar Incidencia</button><table style="width:100%; border-collapse:collapse; font-size:0.8rem; margin-top:0.5rem;"><thead><tr><th style="padding:0.5rem; border:1px solid #e2e8f0; text-align:left;">Fecha</th><th style="padding:0.5rem; border:1px solid #e2e8f0; text-align:left;">Tipo</th><th style="padding:0.5rem; border:1px solid #e2e8f0; text-align:left;">Descripción</th></tr></thead><tbody><tr><td style="padding:0.5rem; border:1px solid #e2e8f0;">12/05 14:20</td><td style="padding:0.5rem; border:1px solid #e2e8f0; color:#f59e0b;">Material</td><td style="padding:0.5rem; border:1px solid #e2e8f0;">Falta filtro HEPA repuesto</td></tr></tbody></table></div>
                </div>
            </div>
        </section>

        <section id="kpis" class="module-section">
            <div style="max-width:900px; margin:0 auto;">
                <h3 style="margin-bottom:1rem;">📊 Indicadores de Gestión</h3>
                <div class="kpi-grid"><div class="kpi-card"><div style="font-size:0.8rem; color:#64748b;">SLA Cumplimiento</div><div class="kpi-val" style="color:#10b981;">94%</div></div><div class="kpi-card"><div style="font-size:0.8rem; color:#64748b;">HH Presup/Real</div><div class="kpi-val" style="color:#f59e0b;">102%</div></div><div class="kpi-card"><div style="font-size:0.8rem; color:#64748b;">OTs Cerradas/Mes</div><div class="kpi-val">47</div></div><div class="kpi-card"><div style="font-size:0.8rem; color:#64748b;">En Proceso</div><div class="kpi-val" style="color:var(--primary);">12</div></div></div>
                <h4 style="margin-bottom:0.5rem;">Distribución de HH por Categoría</h4>
                <div class="pills-container"><button class="pill-btn active" onclick="updateKPIs(this, 'Especialidad')">Especialidad</button><button class="pill-btn" onclick="updateKPIs(this, 'Área')">Área</button><button class="pill-btn" onclick="updateKPIs(this, 'Equipo')">Equipo</button></div>
                <div class="progress-container"><div class="progress-bar" id="kpiBar" style="width:65%;"></div></div>
                <div class="top-list" id="topList"></div>
            </div>
        </section>

        <section id="contratistas" class="module-section">
            <div style="max-width:900px; margin:0 auto;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;"><h3>🤝 Mantenedor de Contratistas</h3><button style="background:var(--primary); color:#fff; padding:0.6rem 1rem; border-radius:0.5rem; border:none; cursor:pointer; display:flex; align-items:center; gap:0.5rem;" onclick="openModal()">➕ Nuevo Contratista</button></div>
                <table style="width:100%; background:#fff; border-radius:0.75rem; overflow:hidden; border-collapse:collapse;"><thead><tr style="background:#f1f5f9;"><th style="padding:0.75rem; text-align:left;">RUT</th><th>Razón Social</th><th>Especialidad</th><th>Contacto</th><th>Acciones</th></tr></thead><tbody><tr><td style="padding:0.75rem;">76.543.210-K</td><td>Servicios ClimaSpa</td><td>M-CLIMATIZACION</td><td>contacto@clima.cl</td><td><button style="padding:0.25rem 0.5rem; cursor:pointer;" onclick="openModal('edit')">✏️</button> <button style="padding:0.25rem 0.5rem; cursor:pointer; color:#ef4444;">🗑️</button></td></tr><tr><td style="padding:0.75rem;">96.876.540-1</td><td>Mantenciones Valdivia</td><td>M-ELECTROMECANICA</td><td>admin@valdivia.cl</td><td><button style="padding:0.25rem 0.5rem; cursor:pointer;" onclick="openModal('edit')">✏️</button> <button style="padding:0.25rem 0.5rem; cursor:pointer; color:#ef4444;">🗑️</button></td></tr></tbody></table>
            </div>
        </section>
    </main>

    <div class="modal-overlay" id="contratistaModal">
        <div class="modal-box">
            <h3 id="modalTitle">Nuevo Contratista</h3>
            <div style="display:grid; gap:0.75rem; margin-top:1rem;"><input placeholder="RUT" style="padding:0.5rem; border:1px solid #e2e8f0; border-radius:0.5rem;"><input placeholder="Razón Social" style="padding:0.5rem; border:1px solid #e2e8f0; border-radius:0.5rem;"><select style="padding:0.5rem; border:1px solid #e2e8f0; border-radius:0.5rem;"><option>Seleccionar Especialidad</option><option>M-CLIMATIZACION</option><option>M-GASFITERIA</option></select><input placeholder="Email Contacto" style="padding:0.5rem; border:1px solid #e2e8f0; border-radius:0.5rem;"></div>
            <div class="modal-actions"><button class="btn-cancel" onclick="closeModal()">Cancelar</button><button class="btn-save" onclick="closeModal(); Toast.success('Contratista guardado correctamente')">Guardar</button></div>
        </div>
    </div>

    <div id="importOverlay" class="import-overlay">
        <div class="spinner"></div>
        <div class="import-text">Procesando archivo SIC...</div>
        <div class="import-sub" id="progressText">Validando registros y sincronizando catálogos</div>
        <div class="progress-container" style="width:300px; margin-top:1rem;"><div class="progress-bar" id="progressBar" style="width:0%;"></div></div>
    </div>

    <footer class="main-footer">
        <img src="/img/logomedicalot.png" alt="MedicalOT">
        <span><strong>© 2026 Sistema MedicalOT - Hospital de Antofagasta</strong></span>
    </footer>

    <script>
        let currentFilters = { page: 1, search: '', esp: '', estado: '', mes: '' };
        let searchTimeout, selectedOTData = null, currentPage = 1, totalPages = 1;

        function showModule(id) {
            document.querySelectorAll('.module-section').forEach(s => s.classList.remove('active'));
            const target = document.getElementById(id);
            if(target) target.classList.add('active');
            if(id === 'ots') { currentPage = 1; loadOTs(); }
            if(id === 'kpis') updateKPIs(document.querySelector('.pill-btn.active'), 'Especialidad');
        }

        setInterval(() => { document.getElementById('clock').textContent = new Date().toLocaleString('es-CL', {weekday:'short', year:'numeric', month:'short', day:'numeric', hour:'2-digit', minute:'2-digit'}); }, 1000);

        const Toast = {
            container: null, init() { if(!this.container) { this.container = document.createElement('div'); this.container.className = 'toast-container'; this.container.style.cssText = 'position:fixed;top:100px;right:1.5rem;z-index:9999;display:flex;flex-direction:column;gap:0.75rem;max-width:400px;'; document.body.appendChild(this.container); } },
            show(message, type='info', title=null, duration=4000) {
                this.init(); const toast = document.createElement('div'); toast.className = `toast ${type}`; toast.style.cssText = 'background:#fff;border-radius:0.75rem;box-shadow:0 10px 15px rgba(0,0,0,0.1);padding:1rem 1.25rem;display:flex;align-items:flex-start;gap:0.875rem;min-width:320px;border-left:4px solid;animation:slideInRight 0.3s ease;';
                const colors = {success:'#10b981',error:'#ef4444',warning:'#f59e0b',info:'#3b82f6'}; toast.style.borderLeftColor = colors[type];
                toast.innerHTML = `<div style="flex:1;"><div style="font-weight:600;font-size:0.9rem;margin-bottom:0.2rem;color:#1e293b;">${title || (type==='success'?'✅ Éxito':'ℹ️ Aviso')}</div><div style="font-size:0.85rem;color:#64748b;">${message}</div></div><button onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;padding:0.2rem;color:#94a3b8;">✕</button>`;
                this.container.appendChild(toast); setTimeout(() => { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 300); }, duration);
            }, success(m,t){this.show(m,'success',t)}, error(m,t){this.show(m,'error',t)}, info(m,t){this.show(m,'info',t)}
        };

        const dropZone = document.getElementById('dropZone');
        if(dropZone) {
            dropZone.addEventListener('click', () => document.getElementById('sicFile').click());
            dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('dragover'); });
            dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
            dropZone.addEventListener('drop', e => { e.preventDefault(); dropZone.classList.remove('dragover'); handleFile(e.dataTransfer.files[0]); });
            document.getElementById('sicFile').addEventListener('change', e => handleFile(e.target.files[0]));
        }

        let pollingInterval = null;
        async function handleFile(file) {
            if(!file) return; const fileName = file.name.trim(); const ext = fileName.split('.').pop().toLowerCase();
            if(ext !== 'csv') { Toast.error(`Extensión inválida: "${ext}". Solo .csv`); return; }
            const summary = document.getElementById('sicSummary'), log = document.getElementById('sicLog'), overlay = document.getElementById('importOverlay'), progressBar = document.getElementById('progressBar'), progressText = document.getElementById('progressText');
            log.innerHTML = `<p>📤 Iniciando carga de ${fileName}...</p>`; summary.classList.add('show'); overlay.classList.add('active'); progressBar.style.width = '0%'; progressText.textContent = 'Preparando...';
            pollingInterval = setInterval(updateProgress, 600);
            const formData = new FormData(); formData.append('sicFile', file);
            try {
                const res = await fetch('/api/import_sic.php', { method: 'POST', body: formData }); const rawText = await res.text();
                let data; try { data = JSON.parse(rawText); } catch { throw new Error('El servidor no devolvió JSON válido.'); }
                if(!res.ok || !data.success) throw new Error(data.error || `Error HTTP ${res.status}`);
                log.innerHTML = `<p style="color:#10b981; font-weight:600;">✅ Proceso finalizado</p><p>📥 Registros leídos: ${data.total}</p><p style="color:#10b981;">✅ ${data.inserted} OTs nuevas importadas</p>${data.skipped > 0 ? `<p style="color:#f59e0b;">⚠️ ${data.skipped} OTs duplicadas omitidas</p>` : ''}${data.errors?.length ? `<p style="color:#ef4444;">❌ ${data.errors.length} errores de validación</p>` : ''}`;
                addToHistory(data.inserted, data.skipped, data.total); Toast.success(`Carga completada: ${data.inserted} nuevos`); document.getElementById('sicFile').value = ''; progressBar.style.width = '100%'; progressText.textContent = '¡Proceso finalizado!';
            } catch (err) { log.innerHTML = `<p style="color:#ef4444;">❌ Error: ${err.message}</p>`; Toast.error(err.message, 'Carga Fallida'); progressBar.style.backgroundColor = '#ef4444'; }
            finally { clearInterval(pollingInterval); setTimeout(() => overlay.classList.remove('active'), 1500); }
        }

        function updateProgress() { fetch('/api/sic_progress.php').then(r => r.json()).then(p => { document.getElementById('progressBar').style.width = p.percent + '%'; document.getElementById('progressText').textContent = `${p.current} / ${p.total} registros (${p.percent}%)`; if(p.status === 'completed' || p.status === 'error') clearInterval(pollingInterval); }).catch(() => {}); }

        function addToHistory(inserted, skipped, total) {
            const history = JSON.parse(localStorage.getItem('sic_history') || '[]'); const now = new Date();
            history.unshift({ date: now.toLocaleDateString(), time: now.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}), total, inserted, duplicates: skipped });
            localStorage.setItem('sic_history', JSON.stringify(history.slice(0, 50))); renderHistory();
        }
        function renderHistory() {
            const history = JSON.parse(localStorage.getItem('sic_history') || '[]'); const tbody = document.getElementById('loadHistory'); if(!tbody) return;
            tbody.innerHTML = history.map(h => `<tr><td>${h.date}</td><td>${h.time}</td><td style="color:#10b981; font-weight:600;">${h.inserted}</td><td style="color:#f59e0b; font-weight:500;">${h.duplicates}</td></tr>`).join('');
        }
        document.addEventListener('DOMContentLoaded', renderHistory);
        function confirmLoad() { document.getElementById('sicSummary').classList.remove('show'); Toast.success('Carga confirmada y registrada en historial'); }

        async function loadOTs() {
            const tbody = document.getElementById('otsTableBody');
            if(!tbody) return;
            tbody.innerHTML = '<tr><td colspan="15" style="text-align:center; padding:2rem;">⏳ Cargando datos reales...</td></tr>';
            
            // Sincronizar estado global con filtros
            currentFilters.page = currentPage;
            const params = new URLSearchParams();
            Object.entries(currentFilters).forEach(([k,v]) => { if(v) params.set(k, v); });
            params.set('limit', '50');

            try {
                const res = await fetch(`/api/ots.php?${params.toString()}`);
                const data = await res.json();
                if(!data.success) throw new Error(data.error);

                renderOTTable(data.data);
                updatePagination(data.page, data.totalPages, data.total);
                populateSpecialtyFilter(data.data);
            } catch (err) {
                tbody.innerHTML = `<tr><td colspan="15" style="text-align:center; color:#ef4444; padding:2rem;">❌ ${err.message}</td></tr>`;
                console.error('📦 Error OTs:', err);
            }
        }

        function renderOTTable(ots) {
            const tbody = document.getElementById('otsTableBody');
            if(!ots || ots.length === 0) {
                tbody.innerHTML = '<tr><td colspan="15" style="text-align:center; padding:2rem; color:#64748b;">No hay registros que coincidan</td></tr>';
                return;
            }
            const stateColors = { 'pendiente': '#f59e0b', 'asignada': '#5fb8d4', 'en_proceso': '#10b981', 'cerrada': '#64748b' };
            tbody.innerHTML = ots.map(o => {
                const fecha = o.fecha_programada ? new Date(o.fecha_programada).toLocaleDateString('es-CL') : '-';
                const badge = o.estado === 'pendiente' ? 'b-pen' : o.estado === 'asignada' ? 'b-asi' : o.estado === 'en_proceso' ? 'b-pro' : 'b-cer';
                const otColor = stateColors[o.estado] || '#000', bgColor = otColor + '15';
                return `<tr onclick="selectOT('${o.codigo_ot}')"><td style="font-weight:700; color:${otColor}; background:${bgColor}; border-radius:4px; padding:0.7rem;">${o.codigo_ot}</td><td>${fecha}</td><td>${o.codigo_ot}</td><td>${o.nombre_protocolo || '-'}</td><td>${o.familia || '-'}</td><td>${o.periodicidad || '-'}</td><td>${o.nombre_equipo || '-'}</td><td>${o.nombre_area || '-'}</td><td>${o.nombre_equipo || '-'}</td><td>${o.serie || '-'}</td><td>${o.area_ubicacion || '-'}</td><td>${o.nombre_especialidad || '-'}</td><td>${o.nombre_proveedor || '-'}</td><td>${o.hh_programadas || 0}</td><td><span class="badge ${badge}">${o.estado}</span></td></tr>`;
            }).join('');
        }

        function updatePagination(page, totalPagesVal, total) {
            currentPage = page;
            totalPages = totalPagesVal || 1;
            currentFilters.page = currentPage; // Sincronización crítica
            document.getElementById('pageInfo').textContent = `📄 ${currentPage}/${totalPages} | ${total} registros`;
            document.getElementById('prevPage').disabled = currentPage <= 1;
            document.getElementById('nextPage').disabled = currentPage >= totalPages;
        }

        function changePage(delta) {
            const newPage = currentPage + delta;
            if(newPage >= 1 && newPage <= totalPages) {
                currentPage = newPage;
                loadOTs(); // Ya sincroniza currentFilters.page internamente
            }
        }

        function applyFilters() {
            currentFilters.search = document.getElementById('otSearch').value.trim();
            currentFilters.esp    = document.getElementById('fEsp').value;
            currentFilters.estado = document.getElementById('fEstado').value;
            currentFilters.mes    = document.getElementById('fMes').value;
            currentPage = 1; // Reset a página 1 al cambiar filtros
            loadOTs();
        }

        function handleSearch() {
            clearTimeout(searchTimeout); searchTimeout = setTimeout(() => applyFilters(), 300);
            const val = document.getElementById('otSearch').value.trim().toLowerCase(); const dd = document.getElementById('searchDropdown');
            if(!val) { dd.classList.remove('show'); return; }
            fetch(`/api/ots.php?search=${encodeURIComponent(val)}&limit=10&page=1`).then(r => r.json()).then(data => {
                if(!data.success || !data.data.length) { dd.classList.remove('show'); return; }
                dd.innerHTML = data.data.map(o => `<div class="search-item" onclick="selectOT('${o.codigo_ot}'); dd.classList.remove('show');">${o.codigo_ot} - ${o.nombre_equipo || o.nombre_protocolo}</div>`).join(''); dd.classList.add('show');
            }).catch(() => {});
        }

        async function selectOT(codigoOt) {
            document.querySelectorAll('.ots-table tr').forEach(r => r.classList.remove('selected')); event.target.closest('tr').classList.add('selected');
            fetch(`/api/ots.php?search=${encodeURIComponent(codigoOt)}&limit=1&page=1`).then(r => r.json()).then(data => {
                if(!data.success || !data.data.length) return; selectedOTData = data.data[0];
                document.getElementById('otDetailContent').innerHTML = `<label>Código OT</label><input value="${selectedOTData.codigo_ot}" readonly><label>Fecha Programada</label><input type="date" value="${selectedOTData.fecha_programada || ''}"><label>Turno</label><input value="${selectedOTData.turno || '-'}"><label>Protocolo</label><input value="${selectedOTData.nombre_protocolo || '-'}"><label>Equipo</label><input value="${selectedOTData.nombre_equipo || '-'}"><label>Área</label><input value="${selectedOTData.nombre_area || '-'}"><label>Especialidad</label><input value="${selectedOTData.nombre_especialidad || '-'}"><label>HH Programadas</label><input type="number" value="${selectedOTData.hh_programadas || 0}" step="0.01"><label>Estado</label><select><option ${selectedOTData.estado==='pendiente'?'selected':''}>pendiente</option><option ${selectedOTData.estado==='asignada'?'selected':''}>asignada</option><option ${selectedOTData.estado==='en_proceso'?'selected':''}>en_proceso</option><option ${selectedOTData.estado==='cerrada'?'selected':''}>cerrada</option></select><label>Observaciones</label><textarea rows="4" placeholder="Notas técnicas..."></textarea>`;
                document.getElementById('otActions').style.display = 'flex';
            }).catch(() => {});
        }

        function clearDetail() { document.getElementById('otDetailContent').innerHTML = '<p style="color:#94a3b8; text-align:center; margin-top:2rem;">Selecciona una OT para ver detalles</p>'; document.getElementById('otActions').style.display = 'none'; document.querySelectorAll('.ots-table tr').forEach(r => r.classList.remove('selected')); selectedOTData = null; }
        function saveOT() { Toast.info('Guardado simulado. Se conectará al backend en Módulo 4.'); }

        function populateSpecialtyFilter(data) {
            const espSet = new Set(data.map(o => o.cod_especialidad).filter(Boolean)); const espMap = new Map(data.map(o => [o.cod_especialidad, o.nombre_especialidad]).filter(x=>x[0]));
            const select = document.getElementById('fEsp'); if(!select) return; const current = select.value; select.innerHTML = '<option value="">Todas Especialidades</option>';
            espSet.forEach(code => { select.innerHTML += `<option value="${code}">${espMap.get(code)}</option>`; }); select.value = current;
        }

        function updateKPIs(btn, cat) {
            document.querySelectorAll('.pill-btn').forEach(b => b.classList.remove('active')); btn.classList.add('active');
            const width = cat === 'Especialidad' ? 65 : cat === 'Área' ? 42 : 28; document.getElementById('kpiBar').style.width = width + '%';
            const top5Data = { 'Especialidad': [['M-CLIMATIZACION', '45%'], ['M-ELECTROMECANICA', '25%'], ['M-GASFITERIA', '15%'], ['M-POLIVALENTE', '10%'], ['OTROS', '5%']], 'Área': [['Pabellones Quirúrgicos', '30%'], ['UCI / UCI Pediátrica', '20%'], ['Laboratorios Clínicos', '15%'], ['Central Alimentos', '10%'], ['Administración', '5%']], 'Equipo': [['Fancoils & Splits', '40%'], ['Chillers & Bombas', '25%'], ['UMAs & Ductos', '15%'], ['Torres Enfriamiento', '10%'], ['Cámaras Frío', '10%']] };
            document.getElementById('topList').innerHTML = `<h5 style="margin-bottom:0.5rem; color:#334155;">Top 5 ${cat}</h5>` + top5Data[cat].map(item => `<div class="top-item"><span>${item[0]}</span><strong>${item[1]}</strong></div>`).join('');
        }

        function openModal(mode) { document.getElementById('contratistaModal').classList.add('show'); document.getElementById('modalTitle').textContent = mode === 'edit' ? 'Editar Contratista' : 'Nuevo Contratista'; }
        function closeModal() { document.getElementById('contratistaModal').classList.remove('show'); }
        function logout() { fetch('/logout.php', { method: 'POST', credentials: 'same-origin' }).then(() => window.location.href = '/login.php').catch(() => window.location.href = '/login.php'); }

        Toast.init();
        if(document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', () => { if(document.getElementById('ots').classList.contains('active')) loadOTs(); }); }
        else { if(document.getElementById('ots').classList.contains('active')) loadOTs(); }
    </script>
</body>
</html>