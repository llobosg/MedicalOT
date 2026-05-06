<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MedicalOT | Hospital de Antofagasta</title>
<link rel="stylesheet" href="/css/medicalot.css">
<style>
  /* ========================================
     LAYOUTS ESPECÍFICOS POR MÓDULO
     ======================================== */
  .module-section { display: none; height: 100%; animation: fadeIn 0.3s ease; }
  .module-section.active { display: block; }
  
  /* Home Grid */
  .home-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.5rem; margin-top: 2rem; }
  .home-card { background: var(--card); border-radius: 1rem; padding: 2rem; text-align: center; cursor: pointer; transition: var(--transition); border: 2px solid transparent; }
  .home-card:hover { transform: translateY(-5px); border-color: var(--primary); box-shadow: var(--shadow-lg); }
  .home-card-icon { font-size: 2.5rem; margin-bottom: 1rem; display: block; }
  .home-card-title { font-size: 1.1rem; font-weight: 700; color: var(--gray-800); }
  .home-card-desc { font-size: 0.85rem; color: var(--gray-600); margin-top: 0.5rem; }

  /* Carga SIC */
  .upload-zone { border: 2px dashed var(--gray-300); border-radius: 1rem; padding: 3rem; text-align: center; background: var(--gray-50); transition: var(--transition); margin-bottom: 2rem; }
  .upload-zone.dragover { border-color: var(--primary); background: #e0f2fe; }
  .upload-zone input { display: none; }
  .summary-box { background: var(--card); border-radius: 0.75rem; padding: 1.5rem; margin: 1.5rem 0; box-shadow: var(--shadow); display: none; border-left: 5px solid var(--success); }
  .summary-box.error { border-left-color: var(--warning); }
  .summary-box.show { display: block; animation: slideInUp 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
  .btn-volver-home { background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; padding: 0.75rem 1.5rem; border-radius: 0.75rem; border: none; cursor: pointer; font-weight: 600; margin-top: 2rem; display: inline-flex; align-items: center; gap: 0.5rem; transition: var(--transition); }
  .btn-volver-home:hover { opacity: 0.9; transform: translateY(-2px); }

  /* OTs Layout 80/20 */
  .ots-layout { display: grid; grid-template-columns: 80% 20%; gap: 1rem; height: calc(100vh - 140px); }
  .ots-left { display: grid; grid-template-rows: 20% 80%; gap: 1rem; }
  .ots-right { background: var(--card); border-radius: 1rem; padding: 1rem; overflow-y: auto; border: 1px solid var(--gray-200); }
  
  /* Smart Search */
  .search-container { position: relative; }
  .search-input { width: 100%; padding: 0.75rem 1rem; border: 2px solid var(--gray-200); border-radius: 0.75rem; font-size: 1rem; }
  .search-input:focus { border-color: var(--primary); outline: none; }
  .search-dropdown { position: absolute; top: 100%; left: 0; right: 0; background: var(--card); border: 1px solid var(--gray-200); border-radius: 0.5rem; max-height: 200px; overflow-y: auto; display: none; z-index: 10; box-shadow: var(--shadow-lg); margin-top: 4px; }
  .search-dropdown.show { display: block; }
  .search-item { padding: 0.75rem; cursor: pointer; border-bottom: 1px solid var(--gray-100); font-size: 0.9rem; }
  .search-item:hover { background: var(--gray-50); color: var(--primary); }
  .filters-row { display: grid; grid-template-columns: repeat(5, 1fr); gap: 0.5rem; margin-top: 0.75rem; }
  .filters-row select { padding: 0.5rem; border: 1px solid var(--gray-200); border-radius: 0.5rem; font-size: 0.85rem; }

  /* Table Scrollable */
  .table-scroll-wrapper { overflow: auto; height: 100%; border: 1px solid var(--gray-200); border-radius: 0.75rem; background: var(--card); }
  .ots-table { width: 100%; min-width: 1200px; border-collapse: collapse; font-size: 0.85rem; }
  .ots-table th { background: var(--gray-100); padding: 0.75rem; text-align: left; position: sticky; top: 0; font-weight: 600; border-bottom: 2px solid var(--gray-200); }
  .ots-table td { padding: 0.75rem; border-bottom: 1px solid var(--gray-100); white-space: nowrap; }
  .ots-table tr:hover { background: #f8fafc; cursor: pointer; }
  .ots-table tr.selected { background: #dbeafe; border-left: 3px solid var(--primary); }

  /* Detail Panel */
  .detail-form label { display: block; font-size: 0.8rem; color: var(--gray-600); margin: 0.5rem 0 0.25rem; }
  .detail-form input, .detail-form select { width: 100%; padding: 0.5rem; border: 1px solid var(--gray-200); border-radius: 0.5rem; font-size: 0.9rem; }
  .detail-actions { display: flex; flex-direction: column; gap: 0.5rem; margin-top: 1rem; }
  .btn-save { background: var(--success); color: white; padding: 0.6rem; border-radius: 0.5rem; border: none; cursor: pointer; }
  .btn-cancel { background: var(--gray-500); color: white; padding: 0.6rem; border-radius: 0.5rem; border: none; cursor: pointer; }
  .btn-insert { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; padding: 0.6rem; border-radius: 0.5rem; border: none; cursor: pointer; margin-top: 1rem; }

  /* KPIs Pills */
  .kpi-pills { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-top: 1rem; }
  .pill-btn { padding: 0.5rem 1rem; border-radius: 2rem; border: 1px solid var(--gray-300); background: var(--card); cursor: pointer; font-size: 0.85rem; transition: var(--transition); }
  .pill-btn.active { background: var(--primary); color: white; border-color: var(--primary); }
  .progress-bar-container { height: 10px; background: var(--gray-200); border-radius: 5px; margin-top: 0.5rem; overflow: hidden; }
  .progress-bar { height: 100%; background: var(--primary); width: 0%; transition: width 0.5s ease; }

  @keyframes slideInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
  @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
</style>
</head>
<body>
<div class="app-background"></div>

<!-- HEADER -->
<header class="main-header">
  <div class="header-left">
    <img src="/img/logohospitalantofagasta.jpeg" alt="Hospital" class="header-logo">
    <div class="header-module">
      <div class="header-module-title">Hospital de Antofagasta</div>
      <div class="header-role">Admin Hospital</div>
    </div>
    <button class="home-icon-btn" onclick="showModule('home')" title="Volver al Home" style="background:none;border:none;cursor:pointer;padding:0.5rem;margin-left:1rem;">
      <svg width="24" height="24" fill="none" stroke="var(--primary)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
    </button>
  </div>
  <div class="header-right">
    <div class="header-datetime"><div id="clock">--:--</div><div style="font-size:0.75rem;">Sistema MedicalOT</div></div>
    <div class="header-user"><div class="user-avatar">AH</div><span>Admin Hospital</span></div>
    <div class="menu-dots"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path></svg></div>
  </div>
</header>

<main class="container">
  <!-- HOME -->
  <section id="home" class="module-section active">
    <div class="card" style="text-align:center; padding:2rem; margin-bottom:1rem;">
      <h2 style="color:var(--primary-dark);">Bienvenido, Administrador</h2>
      <p style="color:var(--gray-600);">Gestión integral de Órdenes de Trabajo Hospitalarias</p>
      <div class="pulse-line" style="max-width:300px; margin:1.5rem auto;"></div>
    </div>
    <div class="home-grid">
      <div class="home-card" onclick="showModule('carga-sic')"><span class="home-card-icon"></span><div class="home-card-title">Carga SIC</div><div class="home-card-desc">Importar y validar planificaciones</div></div>
      <div class="home-card" onclick="showModule('ots')"><span class="home-card-icon">📋</span><div class="home-card-title">OTs</div><div class="home-card-desc">Gestión y seguimiento de órdenes</div></div>
      <div class="home-card" onclick="showModule('tracking')"><span class="home-card-icon">📡</span><div class="home-card-title">Tracking</div><div class="home-card-desc">Avance en terreno y evidencias</div></div>
      <div class="home-card" onclick="showModule('kpis')"><span class="home-card-icon">📊</span><div class="home-card-title">KPIs</div><div class="home-card-desc">Indicadores de gestión y SLA</div></div>
      <div class="home-card" onclick="showModule('contratistas')"><span class="home-card-icon">🤝</span><div class="home-card-title">Contratistas</div><div class="home-card-desc">Mantenedor de proveedores</div></div>
    </div>
  </section>

  <!-- CARGA SIC -->
  <section id="carga-sic" class="module-section">
    <div class="card" style="max-width:900px; margin:0 auto; padding:2rem;">
      <h3 style="margin-bottom:1rem;"> Carga y Validación SIC</h3>
      <div class="upload-zone" id="dropZone">
        <input type="file" id="sicFile" accept=".csv,.xls,.xlsx">
        <p style="font-size:1.2rem; font-weight:600;">Arrastra aquí tu archivo SIC o haz clic para seleccionar</p>
        <p style="color:var(--gray-600); margin-top:0.5rem;">Formatos aceptados: .csv, .xls, .xlsx</p>
      </div>
      <div id="sicSummary" class="summary-box">
        <h4 style="margin-bottom:0.5rem;">📋 Resumen de Validación</h4>
        <div id="sicLog"></div>
        <button class="btn-primary" onclick="confirmLoad()" style="margin-top:1rem; width:auto;">✅ Confirmar Carga</button>
      </div>
      <table style="width:100%; margin-top:2rem; border-collapse:collapse;">
        <thead><tr><th style="background:var(--gray-100); padding:0.75rem; text-align:left;">Fecha</th><th>Hora</th><th>Registros Nuevos</th><th>Omitidos</th></tr></thead>
        <tbody id="loadHistory"></tbody>
      </table>
      <button class="btn-volver-home" onclick="showModule('home')">🏠 Volver a Home</button>
    </div>
  </section>

  <!-- OTs -->
  <section id="ots" class="module-section">
    <div class="ots-layout">
      <div class="ots-left">
        <div class="card" style="padding:1rem;">
          <div class="search-container">
            <input type="text" class="search-input" id="otSearch" placeholder="🔍 Buscar OT, protocolo, equipo, área..." oninput="handleSearch()">
            <div class="search-dropdown" id="searchDropdown"></div>
          </div>
          <div class="filters-row">
            <select id="fEsp"><option>Todas las Especialidades</option><option>M-CLIMATIZACION</option><option>M-ELECTROMECANICA</option></select>
            <select id="fEstado"><option>Todos los Estados</option><option>pendiente</option><option>asignada</option><option>en_proceso</option><option>cerrada</option></select>
            <select id="fMes"><option>Todos los Meses</option><option>Enero</option><option>Febrero</option><option>Marzo</option></select>
            <select id="fGrupo"><option>Todos los Grupos</option><option>Pool ClimA</option><option>Pool ElecB</option></select>
            <select id="fTipo"><option>Todos los Tipos</option><option>Preventiva</option><option>Correctiva</option></select>
          </div>
        </div>
        <div class="table-scroll-wrapper">
          <table class="ots-table">
            <thead>
              <tr><th>OT</th><th>Fecha</th><th>ID SIC</th><th>Protocolo</th><th>Familia</th><th>Periodicidad</th><th>Nombre</th><th>Área</th><th>Equipo</th><th>Serie</th><th>Ubicación</th><th>Especialidad</th><th>Proveedor</th><th>HH</th><th>Esp.</th><th>Grupo</th><th>Estado</th><th>Acción</th></tr>
            </thead>
            <tbody id="otsTableBody"></tbody>
          </table>
        </div>
      </div>
      <div class="ots-right detail-form" id="otDetailPanel">
        <h4 style="margin-bottom:1rem;">📝 Detalle / Edición OT</h4>
        <div id="otDetailContent"><p style="color:var(--gray-500); text-align:center; margin-top:2rem;">Selecciona una OT para ver detalles</p></div>
        <div class="detail-actions" id="otActions" style="display:none;">
          <button class="btn-save" onclick="alert('OT guardada correctamente')">💾 Guardar</button>
          <button class="btn-cancel" onclick="clearDetail()">❌ Cancelar</button>
          <button class="btn-volver-home" style="margin-top:0.5rem; background:var(--gray-600);" onclick="showModule('home')"> Volver</button>
          <button class="btn-insert" onclick="alert('Formulario manual abierto')">➕ Insertar OT Manual</button>
        </div>
      </div>
    </div>
  </section>

  <!-- TRACKING (Estructura base) -->
  <section id="tracking" class="module-section">
    <div class="card" style="padding:2rem;"><h3>📡 Tracking en Terreno</h3><p style="margin:1rem 0; color:var(--gray-600);">Módulo en construcción. Se implementará: filtros por estado, barra de avance, timeline de responsables, adjuntos e incidencias.</p></div>
  </section>

  <!-- KPIs (Estructura base) -->
  <section id="kpis" class="module-section">
    <div class="card" style="padding:2rem;">
      <h3>📊 KPIs & Distribución HH</h3>
      <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:1rem; margin:2rem 0;">
        <div style="text-align:center; padding:1rem; background:var(--gray-50); border-radius:0.75rem;"><div style="font-size:2rem; font-weight:700; color:var(--success);">94%</div><div style="font-size:0.85rem; color:var(--gray-600);">SLA Cumplimiento</div></div>
        <div style="text-align:center; padding:1rem; background:var(--gray-50); border-radius:0.75rem;"><div style="font-size:2rem; font-weight:700; color:var(--primary);">102%</div><div style="font-size:0.85rem; color:var(--gray-600);">HH Presup/Real</div></div>
        <div style="text-align:center; padding:1rem; background:var(--gray-50); border-radius:0.75rem;"><div style="font-size:2rem; font-weight:700; color:var(--gray-800);">47</div><div style="font-size:0.85rem; color:var(--gray-600);">OTs Cerradas/Mes</div></div>
        <div style="text-align:center; padding:1rem; background:var(--gray-50); border-radius:0.75rem;"><div style="font-size:2rem; font-weight:700; color:var(--warning);">12</div><div style="font-size:0.85rem; color:var(--gray-600);">OTs en Proceso</div></div>
      </div>
      <h4 style="margin-top:2rem;">Distribución de HH por Categoría</h4>
      <div class="kpi-pills">
        <button class="pill-btn active" onclick="updateHHBar(this, 65)">Especialidad</button>
        <button class="pill-btn" onclick="updateHHBar(this, 42)">Área</button>
        <button class="pill-btn" onclick="updateHHBar(this, 28)">Equipo</button>
      </div>
      <div class="progress-bar-container"><div class="progress-bar" id="hhBar" style="width:65%;"></div></div>
      <p style="font-size:0.8rem; color:var(--gray-500); margin-top:0.5rem;">Total HH asignadas vs ejecutadas por categoría seleccionada</p>
    </div>
  </section>

  <!-- CONTRATISTAS (Placeholder) -->
  <section id="contratistas" class="module-section">
    <div class="card" style="padding:2rem;"><h3>🤝 Mantenedor de Contratistas</h3><p style="margin:1rem 0; color:var(--gray-600);">Módulo listo para desarrollo. Incluirá CRUD de proveedores, contratos vigentes y métricas de desempeño.</p></div>
  </section>
</main>

<script>
// Mock Data OTs
const otsData = [
  {id:'OT-2026-001', fecha:'11/05/2026', idSic:'396562', proto:'IA11', familia:'Neumática', periodo:'Semestral', nombre:'Linear Coupler', area:'Correo Central', equipo:'Transferencia', serie:'TR-8842', ubi:'Piso 1', esp:'M-POLIVALENTE', prov:'000012', hh:1.33, espCod:'57', grupo:'Pool PoliA', estado:'pendiente'},
  {id:'OT-2026-002', fecha:'12/05/2026', idSic:'397888', proto:'I713', familia:'Criogénica', periodo:'Mensual', nombre:'Estanque Criogénico', area:'Lab. Inmunohematología', equipo:'Tanque N2', serie:'INT-3026B', ubi:'Sótano 2', esp:'M-ELECTROMECANICA', prov:'000007', hh:2.0, espCod:'55', grupo:'Pool ElecB', estado:'asignada'},
  {id:'OT-2026-003', fecha:'14/05/2026', idSic:'402710', proto:'I106', familia:'Construcción', periodo:'Quinquenal', nombre:'Terminaciones', area:'Pabellones', equipo:'Revestimientos', serie:'N/A', ubi:'Nivel 3', esp:'M-POLIVALENTE', prov:'000012', hh:2.0, espCod:'57', grupo:'Pool PoliA', estado:'en_proceso'},
  {id:'OT-2026-004', fecha:'15/05/2026', idSic:'402711', proto:'I106', familia:'Construcción', periodo:'Quinquenal', nombre:'Terminaciones', area:'Pabellones', equipo:'Revestimientos', serie:'N/A', ubi:'Nivel 3', esp:'M-POLIVALENTE', prov:'000012', hh:2.0, espCod:'57', grupo:'Pool PoliA', estado:'cerrada'}
];

let selectedOT = null;

// Navigation
function showModule(id) {
  document.querySelectorAll('.module-section').forEach(s => s.classList.remove('active'));
  document.getElementById(id).classList.add('active');
  if(id === 'ots') renderOTsTable();
}

// Carga SIC Logic
const dropZone = document.getElementById('dropZone');
dropZone.addEventListener('click', () => document.getElementById('sicFile').click());
dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('dragover'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
dropZone.addEventListener('drop', e => { e.preventDefault(); dropZone.classList.remove('dragover'); handleFile(e.dataTransfer.files[0]); });
document.getElementById('sicFile').addEventListener('change', e => handleFile(e.target.files[0]));

function handleFile(file) {
  if(!file) return;
  const ext = file.name.split('.').pop().toLowerCase();
  if(!['csv','xls','xlsx'].includes(ext)) { alert('Formato no válido. Solo .csv, .xls, .xlsx'); return; }
  
  // Simulate validation
  const summary = document.getElementById('sicSummary');
  const log = document.getElementById('sicLog');
  log.innerHTML = `<p>✅ Archivo recibido: ${file.name}</p><p>🔍 Validando integridad y columnas SIC...</p><p>🆔 Revisando duplicados por hash...</p>`;
  summary.classList.add('show');
  
  setTimeout(() => {
    log.innerHTML += `<p style="color:var(--success)">✅ 4 registros nuevos importados.</p><p style="color:var(--warning)">⚠️ 2 registros omitidos (duplicados detectados por ID PREVISION SIC).</p>`;
  }, 800);
}

function confirmLoad() {
  const tbody = document.getElementById('loadHistory');
  const now = new Date();
  const row = `<tr><td>${now.toLocaleDateString()}</td><td>${now.toLocaleTimeString()}</td><td style="color:var(--success)">4</td><td style="color:var(--warning)">2</td></tr>`;
  tbody.innerHTML = row + tbody.innerHTML;
  document.getElementById('sicSummary').classList.remove('show');
  document.getElementById('sicFile').value = '';
  alert('Carga confirmada y registrada en historial.');
}

// OTs Logic
function renderOTsTable() {
  const tbody = document.getElementById('otsTableBody');
  tbody.innerHTML = otsData.map(o => `
    <tr onclick="selectOT('${o.id}')">
      <td>${o.id}</td><td>${o.fecha}</td><td>${o.idSic}</td><td>${o.proto}</td><td>${o.familia}</td><td>${o.periodo}</td><td>${o.nombre}</td><td>${o.area}</td><td>${o.equipo}</td><td>${o.serie}</td><td>${o.ubi}</td><td>${o.esp}</td><td>${o.prov}</td><td>${o.hh}</td><td>${o.espCod}</td><td>${o.grupo}</td><td><span class="badge b-${o.estado.replace('en_proceso','pro').replace('asignada','asi').replace('pendiente','pen').replace('cerrada','cer')}">${o.estado}</span></td><td><button style="padding:0.25rem 0.5rem; font-size:0.8rem; cursor:pointer;">✏️</button></td>
    </tr>
  `).join('');
}

function handleSearch() {
  const val = document.getElementById('otSearch').value.toLowerCase();
  const dropdown = document.getElementById('searchDropdown');
  if(!val) { dropdown.classList.remove('show'); return; }
  
  const matches = otsData.filter(o => Object.values(o).join(' ').toLowerCase().includes(val));
  if(matches.length === 0) { dropdown.classList.remove('show'); return; }
  
  dropdown.innerHTML = matches.map(o => `<div class="search-item" onclick="selectOT('${o.id}'); document.getElementById('searchDropdown').classList.remove('show');">${o.id} - ${o.nombre} (${o.esp})</div>`).join('');
  dropdown.classList.add('show');
}

function selectOT(id) {
  selectedOT = otsData.find(o => o.id === id);
  if(!selectedOT) return;
  
  document.querySelectorAll('.ots-table tr').forEach(r => r.classList.remove('selected'));
  event.target.closest('tr').classList.add('selected');
  
  const panel = document.getElementById('otDetailContent');
  panel.innerHTML = `
    <label>ID SIC</label><input value="${selectedOT.idSic}" readonly>
    <label>Protocolo</label><input value="${selectedOT.proto} - ${selectedOT.nombre}">
    <label>Fecha Programada</label><input type="date" value="2026-05-11">
    <label>HH Asignadas</label><input type="number" value="${selectedOT.hh}">
    <label>Estado</label><select><option>pendiente</option><option>asignada</option><option>en_proceso</option><option>cerrada</option></select>
    <label>Grupo Asignado</label><input value="${selectedOT.grupo}">
  `;
  document.getElementById('otActions').style.display = 'flex';
}

function clearDetail() {
  document.getElementById('otDetailContent').innerHTML = '<p style="color:var(--gray-500); text-align:center; margin-top:2rem;">Selecciona una OT para ver detalles</p>';
  document.getElementById('otActions').style.display = 'none';
  document.querySelectorAll('.ots-table tr').forEach(r => r.classList.remove('selected'));
}

// KPIs Pill Interaction
function updateHHBar(btn, width) {
  document.querySelectorAll('.pill-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById('hhBar').style.width = width + '%';
}

// Clock
setInterval(() => { document.getElementById('clock').textContent = new Date().toLocaleString('es-CL'); }, 1000);

// Init
renderOTsTable();
</script>
</body>
</html>