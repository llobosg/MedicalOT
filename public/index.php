<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MedicalOT | Hospital de Antofagasta</title>
<style>
  :root {
    --primary: #1E88A0; --primary-dark: #156B7D; --primary-light: #4FA8C0;
    --secondary: #5FB8D4; --accent: #7EC8E3;
    --white: #FFFFFF; --gray-50: #F8FAFC; --gray-100: #F1F5F9; --gray-200: #E2E8F0;
    --gray-300: #CBD5E1; --gray-600: #475569; --gray-700: #334155; --gray-800: #1E293B;
    --success: #10B981; --warning: #F59E0B; --error: #EF4444;
    --shadow: 0 4px 6px -1px rgba(0,0,0,0.1); --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1);
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    --card-bg: #ffffff;
  }
  * { box-sizing:border-box; margin:0; padding:0; font-family: system-ui, -apple-system, sans-serif; }
  body { background: var(--gray-50); color: var(--gray-800); min-height: 100vh; display: flex; flex-direction: column; }
  
  /* BACKGROUND */
  .app-background { position:fixed; top:0; left:0; width:100vw; height:100vh; background-image:url('/img/hospitalantofagasta.jpeg'); background-size:cover; background-position:center; opacity:0.12; z-index:-1; pointer-events:none; }

  /* HEADER */
  .main-header { background: var(--white); border-bottom: 2px solid var(--primary); padding: 0.75rem 1.5rem; display:flex; justify-content:space-between; align-items:center; position:sticky; top:0; z-index:100; }
  .header-left, .header-right { display:flex; align-items:center; gap:1rem; }
  .header-logo { height:45px; object-fit:contain; }
  .header-module-title { font-size:1.1rem; font-weight:700; color:var(--primary-dark); }
  .header-role { font-size:0.8rem; color:var(--gray-600); }
  .home-icon-btn { background:none; border:none; cursor:pointer; padding:0.5rem; margin-left:0.5rem; }
  .header-datetime { text-align:right; font-size:0.85rem; color:var(--gray-600); line-height:1.3; }
  .header-user { display:flex; align-items:center; gap:0.5rem; padding:0.4rem 0.8rem; background:var(--gray-100); border-radius:2rem; font-size:0.9rem; }
  .user-avatar { width:28px; height:28px; border-radius:50%; background:var(--primary); color:#fff; display:flex; align-items:center; justify-content:center; font-size:0.8rem; }
  .menu-dots { cursor:pointer; padding:0.5rem; border-radius:50%; }
  .menu-dots:hover { background:var(--gray-100); }

  /* FOOTER */
  .main-footer { background:var(--white); border-top:1px solid var(--gray-200); padding:1rem; text-align:center; font-size:0.8rem; color:var(--gray-600); margin-top:auto; display:flex; align-items:center; justify-content:center; gap:0.5rem; }
  .main-footer img { height:24px; opacity:0.8; }

  /* MODULES */
  .container { flex:1; padding:1.5rem; overflow-y:auto; }
  .module-section { display:none; animation:fadeIn 0.3s ease; }
  .module-section.active { display:block; }
  
  /* HOME CARDS */
  .home-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:1.5rem; margin-top:1.5rem; }
  .home-card { background:var(--card-bg); border-radius:1rem; padding:1.5rem; text-align:center; cursor:pointer; transition:var(--transition); border:2px solid transparent; box-shadow:var(--shadow); }
  .home-card:hover { transform:translateY(-4px); border-color:var(--primary); }
  .home-card-icon { font-size:2rem; margin-bottom:0.75rem; display:block; }
  .home-card-title { font-size:1rem; font-weight:700; color:var(--gray-800); }
  .home-card-desc { font-size:0.8rem; color:var(--gray-600); margin-top:0.25rem; }

  /* CARGA SIC */
  .upload-zone { border:2px dashed var(--gray-300); border-radius:1rem; padding:2.5rem; text-align:center; background:var(--gray-50); transition:var(--transition); margin-bottom:1.5rem; cursor:pointer; }
  .upload-zone:hover, .upload-zone.dragover { border-color:var(--primary); background:#e0f2fe; }
  .summary-box { background:var(--card-bg); border-radius:0.75rem; padding:1.25rem; margin:1.5rem 0; box-shadow:var(--shadow); display:none; border-left:5px solid var(--success); }
  .summary-box.show { display:block; animation:slideInUp 0.4s ease; }
  .btn-volver { background:linear-gradient(135deg, #6366f1, #8b5cf6); color:#fff; padding:0.75rem 1.5rem; border-radius:0.75rem; border:none; cursor:pointer; font-weight:600; margin-top:1.5rem; display:inline-flex; align-items:center; gap:0.5rem; transition:var(--transition); }
  .btn-volver:hover { opacity:0.9; transform:translateY(-2px); }

  /* OTs LAYOUT */
  .ots-layout { display:grid; grid-template-columns:80% 20%; gap:1rem; height:calc(100vh - 180px); }
  .ots-left { display:grid; grid-template-rows:auto 1fr; gap:1rem; }
  .ots-right { background:var(--card-bg); border-radius:1rem; padding:1rem; overflow-y:auto; border:1px solid var(--gray-200); display:flex; flex-direction:column; }
  
  /* SEARCH & FILTERS */
  .search-container { position:relative; margin-bottom:0.75rem; }
  .search-input { width:100%; padding:0.75rem 1rem; border:2px solid var(--gray-200); border-radius:0.75rem; font-size:0.95rem; }
  .search-input:focus { border-color:var(--primary); outline:none; }
  .search-dropdown { position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid var(--gray-200); border-radius:0.5rem; max-height:220px; overflow-y:auto; z-index:50; box-shadow:var(--shadow-lg); margin-top:4px; display:none; }
  .search-dropdown.show { display:block; }
  .search-item { padding:0.75rem 1rem; cursor:pointer; border-bottom:1px solid var(--gray-100); background:#fff; color:var(--primary-dark); font-weight:500; font-size:0.9rem; }
  .search-item:hover { background:var(--gray-50); }
  .filters-row { display:grid; grid-template-columns:repeat(5, 1fr); gap:0.5rem; }
  .filters-row select { padding:0.5rem; border:1px solid var(--gray-200); border-radius:0.5rem; font-size:0.85rem; background:var(--gray-50); }

  /* TABLE */
  .table-scroll-wrapper { overflow:auto; border:1px solid var(--gray-200); border-radius:0.75rem; background:var(--card-bg); }
  .ots-table { width:100%; min-width:1100px; border-collapse:collapse; font-size:0.8rem; }
  .ots-table th { background:var(--gray-100); padding:0.75rem; text-align:left; position:sticky; top:0; font-weight:600; border-bottom:2px solid var(--gray-200); }
  .ots-table td { padding:0.75rem; border-bottom:1px solid var(--gray-100); white-space:nowrap; }
  .ots-table tr:hover { background:#f8fafc; cursor:pointer; }
  .ots-table tr.selected { background:#dbeafe; border-left:3px solid var(--primary); }
  .badge { padding:2px 6px; border-radius:10px; font-size:0.7rem; color:#fff; font-weight:600; }
  .b-pen{background:var(--warning)} .b-asi{background:var(--secondary)} .b-pro{background:var(--success)} .b-cer{background:var(--gray-600)}

  /* DETAIL PANEL */
  .detail-form label { display:block; font-size:0.75rem; color:var(--gray-600); margin:0.5rem 0 0.25rem; }
  .detail-form input, .detail-form select { width:100%; padding:0.5rem; border:1px solid var(--gray-200); border-radius:0.5rem; font-size:0.85rem; }
  .detail-actions { display:flex; flex-direction:column; gap:0.5rem; margin-top:auto; padding-top:1rem; border-top:1px solid var(--gray-200); }
  .btn-save { background:var(--success); color:#fff; padding:0.6rem; border-radius:0.5rem; border:none; cursor:pointer; }
  .btn-cancel { background:var(--gray-500); color:#fff; padding:0.6rem; border-radius:0.5rem; border:none; cursor:pointer; }
  .hidden { display:none!important; }

  /* KPIs */
  .kpi-grid { display:grid; grid-template-columns:repeat(4, 1fr); gap:1rem; margin-bottom:1.5rem; }
  .kpi-card { background:var(--card-bg); padding:1rem; border-radius:0.75rem; text-align:center; box-shadow:var(--shadow); }
  .kpi-val { font-size:1.5rem; font-weight:700; margin:0.5rem 0; }
  .pills-container { display:flex; gap:0.5rem; flex-wrap:wrap; margin-bottom:1rem; }
  .pill-btn { padding:0.5rem 1rem; border-radius:2rem; border:1px solid var(--gray-300); background:var(--card-bg); cursor:pointer; font-size:0.85rem; transition:var(--transition); }
  .pill-btn.active { background:var(--primary); color:#fff; border-color:var(--primary); }
  .progress-container { height:12px; background:var(--gray-200); border-radius:6px; overflow:hidden; margin-bottom:1rem; }
  .progress-bar { height:100%; background:linear-gradient(90deg, #10B981 0%, #FBBF24 50%, #EF4444 100%); width:0%; transition:width 0.6s ease; }
  .top-list { background:var(--gray-50); padding:1rem; border-radius:0.75rem; }
  .top-item { display:flex; justify-content:space-between; padding:0.5rem 0; border-bottom:1px solid var(--gray-200); font-size:0.85rem; }
  .top-item:last-child { border-bottom:none; }

  /* TRACKING */
  .tracking-layout { display:grid; grid-template-columns:1fr 1fr; gap:1.5rem; }
  .timeline { border-left:2px solid var(--gray-200); padding-left:1rem; margin:1rem 0; }
  .timeline-item { position:relative; margin-bottom:1rem; }
  .timeline-item::before { content:''; position:absolute; left:-1.35rem; top:0.2rem; width:10px; height:10px; border-radius:50%; background:var(--primary); }
  .timeline-date { font-size:0.75rem; color:var(--gray-600); }
  .attachments-grid { display:grid; grid-template-columns:repeat(3, 1fr); gap:0.5rem; margin:1rem 0; }
  .att-box { background:var(--gray-100); border-radius:0.5rem; height:60px; display:flex; align-items:center; justify-content:center; font-size:0.8rem; color:var(--gray-600); border:1px dashed var(--gray-300); }
  .inc-table { width:100%; border-collapse:collapse; font-size:0.8rem; margin-top:0.5rem; }
  .inc-table th, .inc-table td { padding:0.5rem; border:1px solid var(--gray-200); text-align:left; }

  /* CONTRATISTAS */
  .crud-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; }
  .btn-add { background:var(--primary); color:#fff; padding:0.6rem 1rem; border-radius:0.5rem; border:none; cursor:pointer; display:flex; align-items:center; gap:0.5rem; }
  .modal-overlay { position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.4); display:none; justify-content:center; align-items:center; z-index:200; }
  .modal-overlay.show { display:flex; }
  .modal-box { background:#fff; padding:1.5rem; border-radius:1rem; width:90%; max-width:500px; box-shadow:var(--shadow-lg); }
  .modal-actions { display:flex; gap:0.5rem; margin-top:1rem; justify-content:flex-end; }

  @keyframes slideInUp { from{opacity:0; transform:translateY(15px)} to{opacity:1; transform:translateY(0)} }
  @keyframes fadeIn { from{opacity:0} to{opacity:1} }
</style>
</head>
<body>
<div class="app-background"></div>

<header class="main-header">
  <div class="header-left">
    <img src="/img/logohospitalantofagasta.jpeg" class="header-logo">
    <div><div class="header-module-title">Hospital de Antofagasta</div><div class="header-role">Admin Hospital</div></div>
    <button class="home-icon-btn" onclick="showModule('home')" title="Volver al Home">
      <svg width="22" height="22" fill="none" stroke="var(--primary)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
    </button>
  </div>
  <div class="header-right">
    <div class="header-datetime"><div id="clock">--:--</div><div style="font-size:0.7rem;">MedicalOT v1.0</div></div>
    <div class="header-user"><div class="user-avatar">AH</div><span>Admin Hospital</span></div>
    <div class="menu-dots"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path></svg></div>
  </div>
</header>

<main class="container">
  <!-- HOME -->
  <section id="home" class="module-section active">
    <div style="text-align:center; margin-bottom:1.5rem;"><h2 style="color:var(--primary-dark);">Bienvenido al Panel de Control</h2><p style="color:var(--gray-600);">Gestión integral de mantenimiento hospitalario</p></div>
    <div class="home-grid">
      <div class="home-card" onclick="showModule('carga-sic')"><span class="home-card-icon"></span><div class="home-card-title">Carga SIC</div><div class="home-card-desc">Importar planificaciones</div></div>
      <div class="home-card" onclick="showModule('ots')"><span class="home-card-icon"></span><div class="home-card-title">OTs</div><div class="home-card-desc">Gestión y seguimiento</div></div>
      <div class="home-card" onclick="showModule('tracking')"><span class="home-card-icon">📡</span><div class="home-card-title">Tracking</div><div class="home-card-desc">Avance en terreno</div></div>
      <div class="home-card" onclick="showModule('kpis')"><span class="home-card-icon">📊</span><div class="home-card-title">KPIs</div><div class="home-card-desc">Indicadores y métricas</div></div>
      <div class="home-card" onclick="showModule('contratistas')"><span class="home-card-icon">🤝</span><div class="home-card-title">Contratistas</div><div class="home-card-desc">Mantenedor de proveedores</div></div>
    </div>
  </section>

  <!-- CARGA SIC -->
  <section id="carga-sic" class="module-section">
    <div style="max-width:800px; margin:0 auto;">
      <h3 style="margin-bottom:1rem;">📥 Carga y Validación SIC</h3>
      <div class="upload-zone" id="dropZone">
        <input type="file" id="sicFile" accept=".csv,.xls,.xlsx" style="display:none">
        <p style="font-weight:600;">Arrastra tu archivo SIC o haz clic aquí</p>
        <p style="font-size:0.8rem; color:var(--gray-600);">Solo .csv, .xls, .xlsx</p>
      </div>
      <div id="sicSummary" class="summary-box">
        <h4>📋 Resumen de Validación</h4>
        <div id="sicLog" style="font-size:0.9rem; margin:0.5rem 0;"></div>
        <button class="btn-volver" style="background:var(--primary); margin-top:0.5rem;" onclick="confirmLoad()">✅ Confirmar Carga</button>
      </div>
      <table style="width:100%; margin-top:1.5rem; border-collapse:collapse; background:#fff; border-radius:0.75rem; overflow:hidden;">
        <thead><tr style="background:var(--gray-100);"><th style="padding:0.75rem; text-align:left;">Fecha</th><th>Hora</th><th>Nuevos</th><th>Omitidos</th></tr></thead>
        <tbody id="loadHistory"></tbody>
      </table>
      <button class="btn-volver" onclick="showModule('home')">🏠 Volver a Home</button>
    </div>
  </section>

  <!-- OTs -->
  <section id="ots" class="module-section">
    <div class="ots-layout">
      <div class="ots-left">
        <div style="background:#fff; padding:1rem; border-radius:0.75rem; border:1px solid var(--gray-200);">
          <div class="search-container">
            <input type="text" class="search-input" id="otSearch" placeholder=" Buscar OT, protocolo, equipo, área..." oninput="handleSearch()">
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
        <div id="otDetailContent"><p style="color:var(--gray-500); text-align:center; margin-top:2rem;">Selecciona una OT</p></div>
        <div class="detail-actions" id="otActions" style="display:none;">
          <button class="btn-save" onclick="alert('Guardado correctamente')">💾 Guardar</button>
          <button class="btn-cancel" onclick="clearDetail()">❌ Cancelar</button>
          <button class="btn-volver" style="background:var(--gray-600); margin-top:0;" onclick="showModule('home')"> Volver a Home</button>
          <!-- <button class="btn-add" style="margin-top:0.5rem; background:var(--warning);" onclick="alert('Formulario manual')"> Insertar OT Manual</button> -->
        </div>
      </div>
    </div>
  </section>

  <!-- TRACKING -->
  <section id="tracking" class="module-section">
    <h3 style="margin-bottom:1rem;">📡 Tracking en Terreno</h3>
    <div style="display:flex; gap:1rem; margin-bottom:1.5rem;">
      <select id="trackStatus" style="padding:0.5rem; border-radius:0.5rem; border:1px solid var(--gray-200); flex:1;" onchange="updateTracking()">
        <option>Filtrar por Estado</option><option>en_proceso</option><option>pausada</option><option>cerrada</option>
      </select>
      <select id="trackOT" style="padding:0.5rem; border-radius:0.5rem; border:1px solid var(--gray-200); flex:2;">
        <option>Seleccionar OT</option><option>OT-2026-003 - Estanque Criogénico</option><option>OT-2026-007 - Torres Enfriamiento</option>
      </select>
    </div>
    <div class="tracking-layout">
      <div style="background:#fff; padding:1.5rem; border-radius:0.75rem; border:1px solid var(--gray-200);">
        <h4>Progreso & Timeline</h4>
        <div class="progress-container" style="margin:1rem 0;"><div class="progress-bar" style="width:65%;"></div></div>
        <p style="font-size:0.85rem; margin-bottom:0.5rem;"><strong>65%</strong> completado - 18.2 HH / 28.0 HH</p>
        <div class="timeline">
          <div class="timeline-item"><div class="timeline-date">10/05/2026 08:30</div><div>Carga desde SIC - Admin Hospital</div></div>
          <div class="timeline-item"><div class="timeline-date">11/05/2026 09:15</div><div>Asignado a Pool ClimA - Sup. Pérez</div></div>
          <div class="timeline-item"><div class="timeline-date">12/05/2026 10:00</div><div>Inicio trabajo - Tec. Juan López</div></div>
          <div class="timeline-item"><div class="timeline-date">--/--/----</div><div>Pendiente término</div></div>
        </div>
      </div>
      <div style="background:#fff; padding:1.5rem; border-radius:0.75rem; border:1px solid var(--gray-200);">
        <h4>Evidencias & Incidencias</h4>
        <div class="attachments-grid">
          <div class="att-box">📷 Foto 1</div><div class="att-box">📄 PDF</div><div class="att-box"> Agregar</div>
        </div>
        <button class="btn-add" style="margin:1rem 0; width:100%; justify-content:center;" onclick="alert('Formulario de incidencia abierto')">⚠️ Reportar Incidencia</button>
        <table class="inc-table">
          <thead><tr><th>Fecha</th><th>Tipo</th><th>Descripción</th></tr></thead>
          <tbody><tr><td>12/05 14:20</td><td style="color:var(--warning);">Material</td><td>Falta filtro HEPA repuesto</td></tr></tbody>
        </table>
      </div>
    </div>
  </section>

  <!-- KPIs -->
  <section id="kpis" class="module-section">
    <h3 style="margin-bottom:1rem;"> Indicadores de Gestión</h3>
    <div class="kpi-grid">
      <div class="kpi-card"><div style="font-size:0.8rem; color:var(--gray-600);">SLA Cumplimiento</div><div class="kpi-val" style="color:var(--success);">94%</div></div>
      <div class="kpi-card"><div style="font-size:0.8rem; color:var(--gray-600);">HH Presup/Real</div><div class="kpi-val" style="color:var(--warning);">102%</div></div>
      <div class="kpi-card"><div style="font-size:0.8rem; color:var(--gray-600);">OTs Cerradas/Mes</div><div class="kpi-val">47</div></div>
      <div class="kpi-card"><div style="font-size:0.8rem; color:var(--gray-600);">En Proceso</div><div class="kpi-val" style="color:var(--primary);">12</div></div>
    </div>
    <h4 style="margin-bottom:0.5rem;">Distribución de HH por Categoría</h4>
    <div class="pills-container">
      <button class="pill-btn active" onclick="updateKPIs(this, 'Especialidad')">Especialidad</button>
      <button class="pill-btn" onclick="updateKPIs(this, 'Área')">Área</button>
      <button class="pill-btn" onclick="updateKPIs(this, 'Equipo')">Equipo</button>
    </div>
    <div class="progress-container"><div class="progress-bar" id="kpiBar" style="width:65%;"></div></div>
    <div class="top-list" id="topList">
      <!-- Dynamic Top 5 -->
    </div>
  </section>

  <!-- CONTRATISTAS -->
  <section id="contratistas" class="module-section">
    <div class="crud-header">
      <h3> Mantenedor de Contratistas</h3>
      <button class="btn-add" onclick="openModal()">➕ Nuevo Contratista</button>
    </div>
    <table style="width:100%; background:#fff; border-radius:0.75rem; overflow:hidden; border-collapse:collapse;">
      <thead><tr style="background:var(--gray-100);"><th style="padding:0.75rem; text-align:left;">RUT</th><th>Razón Social</th><th>Especialidad</th><th>Contacto</th><th>Acciones</th></tr></thead>
      <tbody id="contratistasBody">
        <tr><td style="padding:0.75rem;">76.543.210-K</td><td>Servicios ClimaSpa</td><td>M-CLIMATIZACION</td><td>contacto@clima.cl</td><td><button style="padding:0.25rem 0.5rem; cursor:pointer;" onclick="openModal('edit')">✏️</button> <button style="padding:0.25rem 0.5rem; cursor:pointer; color:var(--error);">️</button></td></tr>
        <tr><td style="padding:0.75rem;">96.876.540-1</td><td>Mantenciones Valdivia</td><td>M-ELECTROMECANICA</td><td>admin@valdivia.cl</td><td><button style="padding:0.25rem 0.5rem; cursor:pointer;" onclick="openModal('edit')">✏️</button> <button style="padding:0.25rem 0.5rem; cursor:pointer; color:var(--error);">🗑️</button></td></tr>
      </tbody>
    </table>
  </section>
</main>

<footer class="main-footer">
  <img src="/img/logo.png" alt="MedicalOT">
  <span>© 2026 Hospital de Antofagasta - Sistema MedicalOT</span>
</footer>

<!-- MODAL CONTRATISTAS -->
<div class="modal-overlay" id="contratistaModal">
  <div class="modal-box">
    <h3 id="modalTitle">Nuevo Contratista</h3>
    <div style="display:grid; gap:0.75rem; margin-top:1rem;">
      <input placeholder="RUT" style="padding:0.5rem; border:1px solid var(--gray-200); border-radius:0.5rem;">
      <input placeholder="Razón Social" style="padding:0.5rem; border:1px solid var(--gray-200); border-radius:0.5rem;">
      <select style="padding:0.5rem; border:1px solid var(--gray-200); border-radius:0.5rem;"><option>Seleccionar Especialidad</option><option>M-CLIMATIZACION</option><option>M-GASFITERIA</option></select>
      <input placeholder="Email Contacto" style="padding:0.5rem; border:1px solid var(--gray-200); border-radius:0.5rem;">
    </div>
    <div class="modal-actions">
      <button class="btn-cancel" onclick="closeModal()">Cancelar</button>
      <button class="btn-save" onclick="closeModal(); alert('Contratista guardado')">Guardar</button>
    </div>
  </div>
</div>

<script>
// Mock Data
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

// Navigation
function showModule(id) {
  document.querySelectorAll('.module-section').forEach(s => s.classList.remove('active'));
  document.getElementById(id).classList.add('active');
  if(id === 'ots') renderOTs();
  if(id === 'kpis') updateKPIs(document.querySelector('.pill-btn.active'), 'Especialidad');
}

// Clock (No seconds)
setInterval(() => {
  document.getElementById('clock').textContent = new Date().toLocaleString('es-CL', {
    weekday:'short', year:'numeric', month:'short', day:'numeric', hour:'2-digit', minute:'2-digit'
  });
}, 1000);

// Carga SIC
const dropZone = document.getElementById('dropZone');
dropZone.addEventListener('click', () => document.getElementById('sicFile').click());
dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('dragover'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
dropZone.addEventListener('drop', e => { e.preventDefault(); dropZone.classList.remove('dragover'); handleFile(e.dataTransfer.files[0]); });
document.getElementById('sicFile').addEventListener('change', e => handleFile(e.target.files[0]));

function handleFile(file) {
  if(!file) return;
  const ext = file.name.split('.').pop().toLowerCase();
  if(!['csv','xls','xlsx'].includes(ext)) { alert('Formato inválido. Solo .csv, .xls, .xlsx'); return; }
  document.getElementById('sicLog').innerHTML = `<p>✅ Archivo: ${file.name}</p><p>🔍 Validando columnas SIC...</p>`;
  document.getElementById('sicSummary').classList.add('show');
  setTimeout(() => { document.getElementById('sicLog').innerHTML += `<p style="color:var(--success)">✅ 4 registros nuevos. ⚠️ 2 duplicados omitidos.</p>`; }, 600);
}
function confirmLoad() {
  const tbody = document.getElementById('loadHistory');
  const now = new Date();
  tbody.insertAdjacentHTML('afterbegin', `<tr><td style="padding:0.75rem;">${now.toLocaleDateString()}</td><td>${now.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'})}</td><td style="color:var(--success); font-weight:600;">4</td><td style="color:var(--warning);">2</td></tr>`);
  document.getElementById('sicSummary').classList.remove('show');
}

// OTs Logic
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
  document.getElementById('otDetailContent').innerHTML = '<p style="color:var(--gray-500); text-align:center; margin-top:2rem;">Selecciona una OT</p>';
  document.getElementById('otActions').style.display = 'none';
  document.querySelectorAll('.ots-table tr').forEach(r => r.classList.remove('selected'));
}

// KPIs
function updateKPIs(btn, cat) {
  document.querySelectorAll('.pill-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  const width = cat === 'Especialidad' ? 65 : cat === 'Área' ? 42 : 28;
  document.getElementById('kpiBar').style.width = width + '%';
  
  const list = document.getElementById('topList');
  list.innerHTML = `<h5 style="margin-bottom:0.5rem; color:var(--gray-700);">Top 5 ${cat}</h5>` + 
    top5Data[cat].map(item => `<div class="top-item"><span>${item[0]}</span><strong>${item[1]}</strong></div>`).join('');
}

// Contratistas Modal
function openModal(mode) {
  document.getElementById('contratistaModal').classList.add('show');
  document.getElementById('modalTitle').textContent = mode === 'edit' ? 'Editar Contratista' : 'Nuevo Contratista';
}
function closeModal() { document.getElementById('contratistaModal').classList.remove('show'); }

// Init
renderOTs();
updateKPIs(document.querySelector('.pill-btn.active'), 'Especialidad');
</script>
</body>
</html>