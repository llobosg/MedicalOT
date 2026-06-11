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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        
        /* ✅ CORRECCIÓN LAYOUT OTs (Filtros Fijos + Scroll Independiente) */
        
        /* 1. El Layout Principal debe tener altura fija relativa a la ventana */
        .ots-layout { 
            display: grid; 
            grid-template-columns: 78% 22%; 
            gap: 1rem; 
            height: calc(100vh - 160px); /* Altura fija menos header/footer */
            min-height: 0;
            overflow: hidden; /* Evita scroll global */
        }

        /* 2. La columna izquierda usa Flexbox vertical para separar Filtros (fijos) de Tabla (scroll) */
        .ots-left { 
            display: flex; 
            flex-direction: column; 
            height: 100%; /* Ocupa toda la altura disponible */
            min-height: 0;
            overflow: hidden; /* Contiene el desbordamiento */
        }
        
        /* 3. El Panel de Filtros NO se encoge y queda fijo arriba */
        .filters-panel { 
            background: #fff; 
            padding: 1rem; 
            border-radius: 0.75rem; 
            border: 1px solid #e2e8f0; 
            flex-shrink: 0; /* CRÍTICO: Impide que se aplaste o mueva */
            margin-bottom: 0.75rem;
            z-index: 10;
        }

        /* 4. El Wrapper de la Tabla ocupa el resto del espacio y hace el scroll */
        .table-scroll-wrapper { 
            flex: 1; /* Toma todo el espacio restante */
            overflow-y: auto; /* Scroll VERTICAL solo aquí */
            overflow-x: auto; /* Scroll HORIZONTAL si es necesario */
            border: 1px solid #e2e8f0; 
            border-radius: 0.5rem; 
            background: #fff; 
            min-height: 0; /* CRÍTICO: Permite que flex:1 funcione con overflow */
        }

        .ots-table { width: 100%; min-width: 1300px; border-collapse: collapse; font-size: 0.8rem; }
        .ots-table th { background: #f1f5f9; padding: 0.7rem; text-align: left; position: sticky; top: 0; font-weight: 600; border-bottom: 2px solid #e2e8f0; z-index: 5; }
        .ots-table td { padding: 0.7rem; border-bottom: 1px solid #f1f5f9; white-space: nowrap; }
        .ots-table tr:hover { background: #f8fafc; cursor: pointer; }
        .ots-table tr.selected { background: #dbeafe; border-left: 3px solid var(--primary); }
        
        /* 5. Panel Derecho (Detalle) también necesita ser flexible y anclado */
        .ots-right { 
            background: #fff; 
            border-radius: 0.75rem; 
            border: 1px solid #e2e8f0; 
            display: flex; 
            flex-direction: column; 
            height: 100%; /* Altura completa */
            overflow: hidden; /* Contiene contenido interno */
            padding: 0;
        }
        .ots-right .detail-form { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
        /* Contenido del Detalle (Scrollable internamente si es muy largo) */
        #otDetailContent { 
            flex: 1; /* Ocupa el espacio disponible entre título y botones */
            overflow-y: auto; /* Si hay muchas observaciones, hace scroll solo aquí */
            padding: 0.5rem 1rem;
        }
        /* Título del panel derecho */
        .ots-right h4 {
            padding: 1rem 1rem 0 1rem;
            margin: 0;
            flex-shrink: 0; /* No se mueve */
        }
        /* Botones del panel derecho (Anclados al fondo) */
        .detail-actions { 
            flex-shrink: 0; /* No se encoge */
            padding: 1rem; 
            border-top: 1px solid #e2e8f0; 
            display: flex; 
            flex-direction: column; 
            gap: 0.5rem; 
            background: #f8fafc; 
            border-radius: 0 0 0.75rem 0.75rem;
        }
        
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
        /* ✅ BARRA DE PROGRESO DINÁMICA */
        .progress-container {
            height: 20px; /* Un poco más gruesa para mejor visibilidad */
            background: #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 1rem;
            width: 100%;
            max-width: 400px;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);
        }

        .progress-bar {
            height: 100%;
            /* Gradiente inicial */
            background: linear-gradient(90deg, #3b82f6 0%, #60a5fa 100%);
            width: 0%;
            transition: width 0.3s ease-out, background 0.5s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.75rem;
            font-weight: bold;
            text-shadow: 0 1px 2px rgba(0,0,0,0.2);
        }

        /* Clases para cambiar color según avance */
        .progress-low { background: linear-gradient(90deg, #ef4444 0%, #f87171 100%) !important; } /* Rojo < 20% */
        .progress-med { background: linear-gradient(90deg, #f59e0b 0%, #fbbf24 100%) !important; } /* Amarillo 20-70% */
        .progress-high { background: linear-gradient(90deg, #10b981 0%, #34d399 100%) !important; } /* Verde > 70% */

        /* ✅ ESTILOS TRACKING */
        .timeline-item {
            position: relative;
            margin-bottom: 1.5rem;
            padding-left: 1rem;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -1.65rem; /* Ajuste según border-left del contenedor */
            top: 0.25rem;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #fff;
            border: 3px solid var(--primary);
            z-index: 1;
        }
        .timeline-item.completed::before { background: var(--success); border-color: var(--success); }
        .timeline-item.warning::before { background: #f59e0b; border-color: #f59e0b; }
        .timeline-item.error::before { background: #ef4444; border-color: #ef4444; }

        .timeline-date { font-size: 0.75rem; color: #64748b; font-weight: 600; margin-bottom: 0.2rem; }
        .timeline-title { font-size: 0.9rem; font-weight: 600; color: #1e293b; }
        .timeline-desc { font-size: 0.8rem; color: #475569; margin-top: 0.1rem; }

        .inc-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-left: 4px solid #ef4444;
            border-radius: 0.5rem;
            padding: 0.75rem;
            margin-bottom: 0.75rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .inc-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.25rem; }
        .inc-type { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #ef4444; }
        .inc-date { font-size: 0.7rem; color: #94a3b8; }
        .inc-body { font-size: 0.85rem; color: #334155; line-height: 1.4; }
        .inc-evidence { margin-top: 0.5rem; font-size: 0.75rem; color: #3b82f6; cursor: pointer; display: flex; align-items: center; gap: 0.25rem; }
        /* Estilos para Pestañas de Recursos */
        .resource-tab {
            padding: 0.75rem 1.5rem;
            border: none;
            background: transparent;
            color: #64748b;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
            margin-bottom: -2px; /* Para superponer la línea inferior */
        }

        .resource-tab:hover {
            color: #3b82f6;
            background: #f8fafc;
        }

        .resource-tab.active {
            color: #3b82f6;
            border-bottom-color: #3b82f6;
            background: #eff6ff;
        }

        /* Botón Primario Genérico */
        .btn-primary {
            background: #3b82f6; /* Azul primario */
            color: white;
            padding: 0.6rem 1.2rem;
            border-radius: 0.5rem;
            border: none;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
            box-shadow: 0 2px 4px rgba(59, 130, 246, 0.2);
        }

        .btn-primary:hover {
            background: #2563eb;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(59, 130, 246, 0.3);
        }

        /* Animación Modal */
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Fix para Selects en algunos navegadores/temas oscuros */
        select option {
            background-color: #ffffff !important;
            color: #1e293b !important;
            padding: 10px;
        }
        /* FIX CRÍTICO: Forzar colores claros en selects del modal */
        #modalRecursos select,
        #modalRecursos select option {
            background-color: #ffffff !important;
            color: #000000 !important;
        }

        /* Asegurar que el placeholder del select sea visible */
        #modalRecursos select option[value=""] {
            color: #64748b !important;
        }
        select, option {
        background-color: #ffffff !important;
        color: #1e293b !important;
        }

        select:focus {
            background-color: #ffffff !important;
            color: #1e293b !important;
        }

        /* Ajuste para 4 columnas en pantallas grandes */
        @media (min-width: 1400px) {
            .ci-container { grid-template-columns: repeat(4, 1fr) !important; }
        }
        /* En pantallas medianas, 2x2 */
        @media (max-width: 1399px) and (min-width: 900px) {
            .ci-container { grid-template-columns: repeat(2, 1fr) !important; }
        }
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
            <button class="home-icon-btn" onclick="showModule('home')" title="Volver al Home">
                <svg width="22" height="22" fill="none" stroke="var(--primary)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
            </button>
        </div>
        <div class="header-right">
            <div class="header-datetime"><div id="clock">--:--</div><div style="font-size:0.7rem;">MedicalOT v1.0</div></div>
            <button class="logout-btn" onclick="logout()" title="Cerrar Sesión"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg></button>
            <div class="menu-dots"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path></svg></div>
            <!-- MENÚ DE CATÁLOGOS (Solo Admin) -->
            <?php 
                $isAdminHeader = (isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], ['admin', 'admin_hospital', 'admin_hosp']));
            ?>
            <!--
            <?php if ($isAdminHeader): ?>
            <div class="dropdown" style="position:relative;">
                <button onclick="toggleCatalogMenu()" style="background:none; border:none; font-size:1.2rem; cursor:pointer; color:#64748b; padding:0.5rem;" title="Catálogos">
                    ⚙️
                </button>
                <div id="catalogDropdown" style="display:none; position:absolute; right:0; top:100%; background:#fff; border:1px solid #e2e8f0; border-radius:0.5rem; box-shadow:0 4px 6px rgba(0,0,0,0.1); width:200px; z-index:1000; overflow:hidden;">
                    <button onclick="openCatalogModal('especialidad')" style="width:100%; text-align:left; padding:0.75rem 1rem; border:none; background:none; cursor:pointer; hover:bg-gray-50; display:flex; align-items:center; gap:0.5rem;">
                        🛠️ Especialidades
                    </button>
                    <button onclick="openCatalogModal('turno')" style="width:100%; text-align:left; padding:0.75rem 1rem; border:none; background:none; cursor:pointer; hover:bg-gray-50; display:flex; align-items:center; gap:0.5rem;">
                        ⏱️ Tipos de Turno
                    </button>
                </div>
            </div>
            <?php endif; ?>
            -->
            <div class="header-user"><div class="user-avatar"><?php echo strtoupper(substr($user['name'], 0, 2)); ?></div><span><?php echo htmlspecialchars($user['name']); ?></span></div>
        </div>
    </header>

    <main class="container">
        <section id="home" class="module-section active">
             <!-- Bienvenida -->
            <div class="card" style="margin-bottom: 2rem;">
                <div class="card-body" style="text-align: center; padding: 3rem;">
                    <img src="/img/logo_siglo_21.png" alt="Hospital" style="width: 120px; height: 120px; object-fit: contain; margin-bottom: 1rem; opacity: 0.8;">
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
                <div class="home-card" onclick="showModule('carga-sic')">
                    <div class="icon-3d-container" style="background:transparent; box-shadow:none; border:none;">
                        <img src="/img/icons/carga_sic.png" alt="Carga SIC" style="width:50px; height:50px; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.1));">
                    </div>
                    <div class="home-card-title">Carga SIC</div>
                    <div class="home-card-desc">Importar y validar planificaciones</div>
                </div>
                <?php endif; ?>
                <div class="home-card" onclick="showModule('ots')">
                    <div class="icon-3d-container" style="background:transparent; box-shadow:none; border:none;">
                        <img src="/img/icons/ots.png" alt="Carga SIC" style="width:50px; height:50px; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.1));">
                    </div>
                    <div class="home-card-title">OTs</div>
                    <div class="home-card-desc">Gestión y seguimiento</div>
                </div>
                <div class="home-card" onclick="showModule('tracking')">
                    <div class="icon-3d-container" style="background:transparent; box-shadow:none; border:none;">
                        <img src="/img/icons/tracking.png" alt="Carga SIC" style="width:50px; height:50px; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.1));">
                    </div>
                    <div class="home-card-title">Tracking</div>
                    <div class="home-card-desc">Avance en terreno</div>
                </div>
                <div class="home-card" onclick="showModule('kpis')">
                    <div class="icon-3d-container" style="background:transparent; box-shadow:none; border:none;">
                        <img src="/img/icons/kpis.png" alt="Carga SIC" style="width:50px; height:50px; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.1));">
                    </div>
                    <div class="home-card-title">KPIs</div>
                    <div class="home-card-desc">Indicadores y métricas</div>
                </div>
                <!-- FICHA RECURSOS -->
                <div class="home-card" onclick="showModule('recursos')">
                    <div class="icon-3d-container" style="background:transparent; box-shadow:none; border:none;">
                        <!-- Icono de Usuarios/Recursos -->
                        <svg width="50" height="50" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="filter: drop-shadow(0 4px 6px rgba(0,0,0,0.1));">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                        </svg>
                    </div>
                    <div class="home-card-title">Recursos</div>
                    <div class="home-card-desc">Técnicos, Grupos y Turnos</div>
                </div>
                <!-- Solo Admin y Admin Cont pueden ver esta sección
                <?php if($isAdmin || $user['role'] === 'admin_cont'): ?>
                   <div class="home-card" onclick="showModule('presentacion')">
                        <div class="icon-3d-container" style="background:transparent; box-shadow:none; border:none;">
                            <img src="/img/icons/presentacion.png" alt="Carga SIC" style="width:50px; height:50px; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.1));">
                        </div>
                        <div class="home-card-title">Presentación</div>
                        <div class="home-card-desc">KPIs de carga y disponibilidad</div>
                    </div>   
                <?php endif; ?>
                 -->
            </div>
        </section>

        <?php if($isAdmin): ?>
            <section id="carga-sic" class="module-section">
            <style>
                /* === CENTRO DE IMPORTACIONES - LAYOUT 4 COLUMNAS PERFECTAS === */
                .ci-container { 
                    display: grid; 
                    grid-template-columns: repeat(4, 1fr); /* 4 Columnas Iguales Forzadas */
                    gap: 1rem; 
                    max-width: 1600px; 
                    margin: 0 auto; 
                    padding: 0 1rem; 
                    align-items: stretch; 
                }
                
                .ci-card { 
                    background: #fff; 
                    border-radius: 1rem; 
                    padding: 1.25rem; 
                    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.07); 
                    border-top: 5px solid #ccc; 
                    display: flex; 
                    flex-direction: column; 
                    height: 100%; 
                    min-width: 0; /* CRÍTICO: Permite que el texto largo haga wrap en vez de estirar la columna */
                    transition: transform 0.2s, box-shadow 0.2s; 
                    position: relative;
                    overflow: hidden; /* Contiene cualquier desbordamiento */
                }
                
                .ci-card:hover { transform: translateY(-3px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }

                /* Colores por tarjeta */
                .ci-card.ejecucion { border-top-color: #ef4444; } 
                .ci-card.hh { border-top-color: #8b5cf6; }       
                .ci-card.mant { border-top-color: #f59e0b; }     
                .ci-card.sic { border-top-color: #3b82f6; }      

                .ci-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; flex-shrink: 0; }
                .ci-title { font-size: 1rem; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 0.5rem; margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
                .ci-help-btn { width: 24px; height: 24px; border-radius: 50%; border: 2px solid #e2e8f0; background: #fff; color: #64748b; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; transition: all 0.2s; flex-shrink: 0; }
                .ci-help-btn:hover { border-color: currentColor; background: #f8fafc; }

                .ci-desc { font-size: 0.75rem; color: #64748b; margin-bottom: 1rem; line-height: 1.4; flex-shrink: 0; }
                
                /* Dropzone Uniforme */
                .ci-dropzone { 
                    border: 2px dashed #cbd5e1; 
                    border-radius: 0.75rem; 
                    padding: 1.5rem 1rem; 
                    text-align: center; 
                    cursor: pointer; 
                    transition: all 0.3s; 
                    background: #fafbfc; 
                    min-height: 140px; 
                    display: flex; 
                    flex-direction: column; 
                    align-items: center; 
                    justify-content: center; 
                    flex: 0 0 auto; 
                    margin-bottom: 1rem;
                }
                
                .ci-dropzone:hover, .ci-dropzone.dragover { border-color: currentColor; background: #f0f9ff; transform: scale(1.02); }
                
                .ci-card.ejecucion .ci-dropzone:hover, .ci-card.ejecucion .ci-dropzone.dragover { border-color: #ef4444; background: #fef2f2; }
                .ci-card.hh .ci-dropzone:hover, .ci-card.hh .ci-dropzone.dragover { border-color: #8b5cf6; background: #f5f3ff; }
                .ci-card.mant .ci-dropzone:hover, .ci-card.mant .ci-dropzone.dragover { border-color: #f59e0b; background: #fffbeb; }
                .ci-card.sic .ci-dropzone:hover, .ci-card.sic .ci-dropzone.dragover { border-color: #3b82f6; background: #eff6ff; }

                .ci-dropzone i { font-size: 2rem; margin-bottom: 0.5rem; opacity: 0.8; }
                .ci-card.ejecucion .ci-dropzone i { color: #ef4444; }
                .ci-card.hh .ci-dropzone i { color: #8b5cf6; }
                .ci-card.mant .ci-dropzone i { color: #f59e0b; }
                .ci-card.sic .ci-dropzone i { color: #3b82f6; }

                .ci-dropzone p { margin: 0; font-size: 0.85rem; color: #475569; font-weight: 600; }
                .ci-dropzone small { font-size: 0.7rem; color: #94a3b8; margin-top: 0.3rem; display: block; }

                /* Progreso y Resultados */
                .ci-progress { display: none; margin-top: 0.5rem; animation: fadeIn 0.3s ease; flex-shrink: 0; }
                .ci-bar-bg { height: 6px; border-radius: 6px; overflow: hidden; background: #e9ecef; }
                .ci-bar-fill { height: 100%; border-radius: 6px; transition: width 0.5s ease; }
                
                .ci-card.ejecucion .ci-bar-fill { background: linear-gradient(90deg, #ef4444 0%, #b91c1c 100%); }
                .ci-card.hh .ci-bar-fill { background: linear-gradient(90deg, #8b5cf6 0%, #6366f1 100%); }
                .ci-card.mant .ci-bar-fill { background: linear-gradient(90deg, #f59e0b 0%, #d97706 100%); }
                .ci-card.sic .ci-bar-fill { background: linear-gradient(90deg, #3b82f6 0%, #2563eb 100%); }

                .ci-result { display: none; margin-top: 0.75rem; padding: 0.75rem; border-radius: 0.5rem; font-size: 0.75rem; line-height: 1.5; animation: fadeIn 0.3s ease; flex-shrink: 0; word-break: break-word; }
                .ci-result.success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }
                .ci-result.error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
                
                .ci-close-btn { margin-top: 0.5rem; width: 100%; padding: 0.4rem; border: 1px solid #e2e8f0; border-radius: 0.5rem; background: #fff; color: #475569; font-size: 0.75rem; cursor: pointer; transition: all 0.2s; font-weight: 500; }
                .ci-close-btn:hover { background: #f8fafc; border-color: #cbd5e1; }

                /* Bitácora con Scroll Interno */
                .ci-bitacora { margin-top: auto; padding-top: 0.75rem; border-top: 1px solid #f1f5f9; flex: 1; display: flex; flex-direction: column; min-height: 0; overflow: hidden; }
                .ci-bitacora-title { font-size: 0.7rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.4rem; flex-shrink: 0; }
                .ci-log-container { flex: 1; overflow-y: auto; max-height: 150px; padding-right: 4px; }
                .ci-log-item { display: flex; justify-content: space-between; align-items: center; padding: 0.3rem 0; border-bottom: 1px solid #f8fafc; font-size: 0.7rem; }
                .ci-log-item:last-child { border-bottom: none; }
                .ci-log-name { color: #334155; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 60%; }
                .ci-log-meta { color: #94a3b8; text-align: right; white-space: nowrap; display: flex; align-items: center; gap: 0.3rem; }
                .ci-log-badge { display: inline-block; padding: 1px 5px; border-radius: 4px; font-size: 0.65rem; font-weight: 700; }
                .ci-log-badge.ok { background: #dcfce7; color: #166534; }
                .ci-log-badge.err { background: #fee2e2; color: #991b1b; }

                /* Modal Ayuda */
                .ci-modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; animation: fadeIn 0.2s ease; }
                .ci-modal-overlay.active { display: flex; }
                .ci-modal { background: #fff; border-radius: 1rem; max-width: 550px; width: 90%; max-height: 80vh; overflow-y: auto; padding: 2rem; box-shadow: 0 20px 60px rgba(0,0,0,0.2); position: relative; }
                .ci-modal-close { position: absolute; top: 1rem; right: 1rem; width: 32px; height: 32px; border-radius: 50%; border: none; background: #f1f5f9; color: #64748b; font-size: 1.2rem; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: background 0.2s; }
                .ci-modal-close:hover { background: #e2e8f0; }
                .ci-modal h3 { margin: 0 0 0.5rem; font-size: 1.15rem; }
                .ci-modal p, .ci-modal li { font-size: 0.85rem; color: #475569; line-height: 1.6; }
                .ci-modal ul { padding-left: 1.2rem; margin: 0.5rem 0; }
                
                @media (max-width: 1400px) { .ci-container { grid-template-columns: repeat(2, 1fr); } }
                @media (max-width: 900px) { .ci-container { grid-template-columns: 1fr; } }
            </style>

            <!-- Header -->
            <div style="max-width: 1600px; margin: 0 auto; padding: 0 1rem 1rem;">
                <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b; margin-bottom: 0.25rem;">📦 Centro de Importaciones</h2>
                <p style="font-size: 0.85rem; color: #64748b; margin: 0;">Gestiona las cargas de datos del sistema. Cada archivo alimenta una capa diferente del análisis.</p>
            </div>

            <!-- Grid 4 Columnas -->
            <div class="ci-container">

                <!-- ====== COLUMNA 1: ESTADO FINAL (Cierre de Ciclo) ====== -->
                <div class="ci-card ejecucion">
                    <div class="ci-header">
                        <h3 class="ci-title"><i class="bi bi-check-circle-fill"></i> Estado Final</h3>
                        <button class="ci-help-btn" onclick="openCIHelp('ejecucion')" title="Ayuda" style="color:#ef4444; border-color:#fecaca;">?</button>
                    </div>
                    <div class="ci-desc">
                        Cierra el ciclo de la OT. Importa HHs reales, tiempos de ejecución y estado final. Actualiza KPIs de eficiencia.
                    </div>
                    
                    <div class="ci-dropzone" id="dropZoneEjecucion" onclick="document.getElementById('ejecucionFile').click()">
                        <input type="file" id="ejecucionFile" accept=".xlsx,.xls" style="display:none">
                        <i class="bi bi-file-earmark-check"></i>
                        <p>Arrastra archivo de Cierre aquí</p>
                        <small>.xlsx | Hoja "Estado Final"</small>
                    </div>
                    
                    <div class="ci-progress" id="ejecucionProgressContainer">
                        <div class="ci-bar-bg"><div class="ci-bar-fill" id="ejecucionProgressBar" style="width:0%;"></div></div>
                        <p id="ejecucionProgressText" style="font-size:0.72rem; color:#64748b; margin-top:0.3rem; text-align:center;"></p>
                    </div>
                    
                    <div class="ci-result" id="ejecucionResult"></div>
                    
                    <div class="ci-bitacora">
                        <div class="ci-bitacora-title">📋 Últimos cierres</div>
                        <div class="ci-log-container" id="bitacoraEjecucion"><p style="font-size:0.72rem; color:#cbd5e1; text-align:center; margin:0;">Sin cargas recientes</p></div>
                    </div>
                </div>

                <!-- ====== COLUMNA 2: PLANIFICACIÓN HH (Violeta) ====== -->
                <div class="ci-card hh">
                    <div class="ci-header">
                        <h3 class="ci-title"><i class="bi bi-calendar-week"></i> Planificación HH</h3>
                        <button class="ci-help-btn" onclick="openCIHelp('hh')" title="Ayuda" style="color:#8b5cf6; border-color:#ddd6fe;">?</button>
                    </div>
                    <div class="ci-desc">
                        Dotación mensual de técnicos con turnos y horas planificadas. Se carga <strong>mensual o semanalmente</strong>.
                    </div>
                    
                    <div class="ci-dropzone" id="dropZoneHH" onclick="document.getElementById('hhFile').click()">
                        <input type="file" id="hhFile" accept=".xlsx,.xls" style="display:none">
                        <i class="bi bi-file-earmark-spreadsheet"></i>
                        <p>Arrastra archivo Excel aquí</p>
                        <small>.xlsx / .xls — Hoja "Dotación (2)"</small>
                    </div>
                    
                    <div class="ci-progress" id="hhProgressContainer">
                        <div class="ci-bar-bg"><div class="ci-bar-fill" id="hhProgressBar" style="width:0%;"></div></div>
                        <p id="hhProgressText" style="font-size:0.72rem; color:#64748b; margin-top:0.3rem; text-align:center;"></p>
                    </div>
                    
                    <div class="ci-result" id="hhResult"></div>
                    
                    <div class="ci-bitacora">
                        <div class="ci-bitacora-title">📋 Últimas cargas</div>
                        <div class="ci-log-container" id="bitacoraHH"><p style="font-size:0.72rem; color:#cbd5e1; text-align:center; margin:0;">Sin cargas recientes</p></div>
                    </div>
                </div>

                <!-- ====== COLUMNA 3: MANTENCIÓN NEW BD (Ámbar) ====== -->
                <div class="ci-card mant">
                    <div class="ci-header">
                        <h3 class="ci-title"><i class="bi bi-tools"></i> Mantención (NEW BD)</h3>
                        <button class="ci-help-btn" onclick="openCIHelp('mant')" title="Ayuda" style="color:#f59e0b; border-color:#fde68a;">?</button>
                    </div>
                    <div class="ci-desc">
                        Planificaciones y ejecuciones del sistema de mantenimiento. Actualiza HHs, especialidades y estados de OTs.
                    </div>
                    
                    <div class="ci-dropzone" id="dropZoneMantencion" onclick="document.getElementById('mantencionFile').click()">
                        <input type="file" id="mantencionFile" accept=".xlsx,.xls,.csv" style="display:none">
                        <i class="bi bi-gear-wide-connected"></i>
                        <p>Arrastra archivo aquí</p>
                        <small>.xlsx / .csv — Hoja "NEW BD"</small>
                    </div>
                    
                    <div class="ci-progress" id="mantProgressContainer">
                        <div class="ci-bar-bg"><div class="ci-bar-fill" id="mantProgressBar" style="width:0%;"></div></div>
                        <p id="mantProgressText" style="font-size:0.72rem; color:#64748b; margin-top:0.3rem; text-align:center;"></p>
                    </div>
                    
                    <div class="ci-result" id="mantResult"></div>
                    
                    <div class="ci-bitacora">
                        <div class="ci-bitacora-title">📋 Últimas cargas</div>
                        <div class="ci-log-container" id="bitacoraMant"><p style="font-size:0.72rem; color:#cbd5e1; text-align:center; margin:0;">Sin cargas recientes</p></div>
                    </div>
                </div>

                <!-- ====== COLUMNA 4: CARGA SIC (Azul) ====== -->
                <div class="ci-card sic">
                    <div class="ci-header">
                        <h3 class="ci-title"><i class="bi bi-database"></i> Carga SIC</h3>
                        <button class="ci-help-btn" onclick="openCIHelp('sic')" title="Ayuda" style="color:#3b82f6; border-color:#bfdbfe;">?</button>
                    </div>
                    <div class="ci-desc">
                        Órdenes de trabajo y planificación anual del sistema SIC. Se carga <strong>una vez al año</strong> como base inicial.
                    </div>
                    
                    <div class="ci-dropzone" id="dropZoneSIC" onclick="document.getElementById('sicFile').click()">
                        <input type="file" id="sicFile" accept=".csv" style="display:none">
                        <i class="bi bi-server"></i>
                        <p>Arrastra archivo CSV aquí</p>
                        <small>.csv — Exportación SIC | Máx 50MB</small>
                    </div>
                    
                    <div class="ci-progress" id="sicProgressContainer">
                        <div class="ci-bar-bg"><div class="ci-bar-fill" id="sicProgressBar" style="width:0%;"></div></div>
                        <p id="sicProgressText" style="font-size:0.72rem; color:#64748b; margin-top:0.3rem; text-align:center;"></p>
                    </div>
                    
                    <div class="ci-result" id="sicResult"></div>
                    
                    <div class="ci-bitacora">
                        <div class="ci-bitacora-title">📋 Últimas cargas</div>
                        <div class="ci-log-container" id="bitacoraSIC"><p style="font-size:0.72rem; color:#cbd5e1; text-align:center; margin:0;">Sin cargas recientes</p></div>
                    </div>
                </div>
            </div>

            <!-- Botón Volver -->
            <div style="text-align:center; margin-top:1.5rem;">
                <button class="btn-volver" onclick="showModule('home')">🏠 Volver a Home</button>
            </div>

            <!-- ====== MODALES DE AYUDA ====== -->
            <div class="ci-modal-overlay" id="ciModalOverlay" onclick="if(event.target===this)closeCIHelp()">
                <div class="ci-modal" id="ciModalContent">
                    <button class="ci-modal-close" onclick="closeCIHelp()">✕</button>
                    <div id="ciModalBody"></div>
                </div>
            </div>

            <script>
            // ═══════════════════════════════════════════════════════
            // CENTRO DE IMPORTACIONES - LÓGICA UNIFICADA
            // ═══════════════════════════════════════════════════════

            // === CONTENIDO DE MODALES DE AYUDA ===
            const ciHelpContent = {
                ejecucion: {
                    cls: 'ejecucion',
                    title: '🛑 Estado Final (Cierre de OT)',
                    body: `
                        <p>Este archivo cierra el ciclo de vida de la Orden de Trabajo. Registra lo que <strong>realmente</strong> ocurrió en terreno.</p>
                        <h4>🔗 Vinculación</h4>
                        <ul>
                            <li><strong>Llave:</strong> Columna B (<code>id_prevision</code>) vincula con la planificación SIC.</li>
                            <li><strong>Técnico:</strong> Columna AV busca vincular con la tabla <code>tecnicos</code> para KPIs de ocupación.</li>
                            <li><strong>HH Reales:</strong> Columna BN (<code>Horas</code>) se compara contra las HH Planificadas para calcular desviaciones.</li>
                        </ul>
                        <h4>📊 Impacto en KPIs</h4>
                        <p>Al importar este archivo, se actualizan automáticamente:</p>
                        <ul>
                            <li>% Cumplimiento de Tiempos (Real vs Programado).</li>
                            <li>Eficiencia de Técnicos (HH Consumidas).</li>
                            <li>Estado real de Equipos (Operativo/Falla).</li>
                        </ul>
                    `
                },
                hh: {
                    cls: 'hh',
                    title: '📅 Planificación de Horas-Hombre',
                    body: `
                        <p>Este archivo contiene la <strong>dotación mensual de técnicos</strong> con sus turnos asignados día a día.</p>
                        <h4>📁 Formato del archivo</h4>
                        <ul>
                            <li><strong>Tipo:</strong> Excel (.xlsx / .xls)</li>
                            <li><strong>Hoja requerida:</strong> "Dotación (2)" o "Dotación"</li>
                            <li><strong>Frecuencia:</strong> Mensual o semanal</li>
                        </ul>
                        <h4>📊 Columnas requeridas</h4>
                        <table>
                            <tr><th>Columna</th><th>Descripción</th><th>Ejemplo</th></tr>
                            <tr><td>#</td><td>Número de fila</td><td>1, 2, 3...</td></tr>
                            <tr><td>RUT</td><td>RUT del técnico</td><td>12345678-9</td></tr>
                            <tr><td>Apellidos y Nombres</td><td>Nombre completo</td><td>Juan Pérez</td></tr>
                            <tr><td>HH</td><td>Aporta horas hombre</td><td>SI / NO</td></tr>
                            <tr><td>Estatus</td><td>Estado del técnico</td><td>Activo / Vacante</td></tr>
                            <tr><td>4/1/26 ... 4/30/26</td><td>Código de turno por día</td><td>7, 9, -1, 6</td></tr>
                        </table>
                    `
                },
                mant: {
                    cls: 'mant',
                    title: '🔧 Mantención (NEW BD)',
                    body: `
                        <p>Archivo exportado del sistema de mantenimiento con las <strong>planificaciones y ejecuciones</strong> de órdenes de trabajo.</p>
                        <h4>📁 Formato del archivo</h4>
                        <ul>
                            <li><strong>Tipo:</strong> Excel (.xlsx) o CSV (.csv)</li>
                            <li><strong>Hoja requerida:</strong> "NEW BD"</li>
                            <li><strong>Frecuencia:</strong> Semanal o quincenal</li>
                        </ul>
                        <h4>📊 Contenido principal</h4>
                        <table>
                            <tr><th>Campo</th><th>Descripción</th></tr>
                            <tr><td>Código OT</td><td>Identificador único de la orden</td></tr>
                            <tr><td>Equipo</td><td>Equipo asociado a la OT</td></tr>
                            <tr><td>Especialidad</td><td>Área técnica responsable</td></tr>
                            <tr><td>HH Planificadas</td><td>Horas hombre estimadas</td></tr>
                            <tr><td>Fecha Programada</td><td>Fecha de ejecución planificada</td></tr>
                            <tr><td>Estado</td><td>Pendiente, En Ejecución, Completada</td></tr>
                        </table>
                    `
                },
                sic: {
                    cls: 'sic',
                    title: '🗄️ Carga y Validación SIC',
                    body: `
                        <p>Exportación del <strong>sistema SIC</strong> con todas las órdenes de trabajo del año. Es la <strong>base fundacional</strong> del sistema.</p>
                        <h4>📁 Formato del archivo</h4>
                        <ul>
                            <li><strong>Tipo:</strong> CSV (.csv)</li>
                            <li><strong>Origen:</strong> Exportación directa del sistema SIC</li>
                            <li><strong>Frecuencia:</strong> Una vez al año (al inicio)</li>
                        </ul>
                        <h4>📊 Contenido principal</h4>
                        <table>
                            <tr><th>Campo</th><th>Descripción</th></tr>
                            <tr><td>Código OT</td><td>Identificador único SIC</td></tr>
                            <tr><td>Equipo</td><td>Nombre del equipo</td></tr>
                            <tr><td>Especialidad</td><td>Disciplina técnica</td></tr>
                            <tr><td>HH Planificadas</td><td>Horas hombre anuales</td></tr>
                            <tr><td>Periodicidad</td><td>Mensual, Semestral, Anual</td></tr>
                            <tr><td>Estado</td><td>Estado actual de la OT</td></tr>
                        </table>
                    `
                }
            };

            // === FUNCIONES DE MODAL ===
            function openCIHelp(type) {
                const data = ciHelpContent[type];
                const modal = document.getElementById('ciModalContent');
                modal.className = 'ci-modal ' + data.cls;
                document.getElementById('ciModalBody').innerHTML = '<h3>' + data.title + '</h3>' + data.body;
                document.getElementById('ciModalOverlay').classList.add('active');
            }
            function closeCIHelp() {
                document.getElementById('ciModalOverlay').classList.remove('active');
            }

            // === BITÁCORA PERSISTENTE (localStorage) ===
            function addToBitacora(type, fileName, stats, success) {
                const key = 'ci_bitacora_' + type;
                const history = JSON.parse(localStorage.getItem(key) || '[]');
                const now = new Date();
                const timeStr = now.toLocaleTimeString('es-CL', {hour:'2-digit', minute:'2-digit'});
                const dateStr = now.toLocaleDateString('es-CL', {day:'2-digit', month:'2-digit'});
                
                let count = 0;
                if (type === 'hh') count = stats.total_tecnicos || stats.planificaciones_creadas || 0;
                else if (type === 'mant') count = stats.updated || stats.processed || 0;
                else if (type === 'sic') count = stats.inserted || stats.total || 0;
                else if (type === 'ejecucion') count = stats.insertados || stats.actualizados || 0;
                
                history.unshift({ name: fileName, time: timeStr, date: dateStr, count: count, ok: success });
                localStorage.setItem(key, JSON.stringify(history.slice(0, 10)));
                renderBitacora(type);
            }

            function renderBitacora(type) {
                const key = 'ci_bitacora_' + type;
                let containerId = '';
                if (type === 'hh') containerId = 'bitacoraHH';
                else if (type === 'mant') containerId = 'bitacoraMant';
                else if (type === 'sic') containerId = 'bitacoraSIC';
                else if (type === 'ejecucion') containerId = 'bitacoraEjecucion';
                
                const container = document.getElementById(containerId);
                if (!container) return;
                
                const history = JSON.parse(localStorage.getItem(key) || '[]');
                if (history.length === 0) {
                    container.innerHTML = '<p style="font-size:0.72rem; color:#cbd5e1; text-align:center; margin:0;">Sin cargas recientes</p>';
                    return;
                }
                
                container.innerHTML = history.map(h => `
                    <div class="ci-log-item">
                        <div class="ci-log-name" title="${h.name}">${h.name}</div>
                        <div class="ci-log-meta">
                            <span class="ci-log-badge ${h.ok ? 'ok' : 'err'}">${h.ok ? 'OK' : 'ERR'}</span>
                            ${h.date} ${h.time} · ${h.count} reg.
                        </div>
                    </div>
                `).join('');
            }

            // === MOSTRAR RESULTADO SIMPLIFICADO ===
            function showCIResult(type, success, stats, periodo) {
                let resultId = '';
                if (type === 'hh') resultId = 'hhResult';
                else if (type === 'mant') resultId = 'mantResult';
                else if (type === 'sic') resultId = 'sicResult';
                else if (type === 'ejecucion') resultId = 'ejecucionResult';
                
                const el = document.getElementById(resultId);
                if (!el) return;
                
                el.style.display = 'block';
                el.className = 'ci-result ' + (success ? 'success' : 'error');
                
                if (!success) {
                    el.innerHTML = `<div class="ci-result-line">❌ <strong>Error:</strong> ${stats.error || 'Desconocido'}</div>
                        <button class="ci-close-btn" onclick="this.parentElement.style.display='none'">Cerrar</button>`;
                    return;
                }
                
                let lines = '';
                if (type === 'hh') {
                    const p = periodo || {};
                    lines = `<div class="ci-result-line">✅ <strong>Carga Exitosa</strong></div>
                        <div class="ci-result-line">📊 Técnicos procesados: ${stats.total_tecnicos || 0}</div>
                        <div class="ci-result-line">🆕 Técnicos creados: ${stats.tecnicos_creados || 0}</div>
                        <div class="ci-result-line">📅 Planificaciones: ${stats.planificaciones_creadas || 0}</div>
                        <div class="ci-result-line">🗓️ Período: ${p.mes_nombre || '-'} ${p.año || ''}</div>`;
                } else if (type === 'mant') {
                    lines = `<div class="ci-result-line">✅ <strong>Carga Exitosa</strong></div>
                        <div class="ci-result-line">📊 Filas procesadas: ${stats.processed || 0}</div>
                        <div class="ci-result-line">✅ Registros actualizados: ${stats.updated || 0}</div>
                        <div class="ci-result-line">❌ Errores: ${stats.errors || 0}</div>`;
                } else if (type === 'sic') {
                    lines = `<div class="ci-result-line">✅ <strong>Carga Exitosa</strong></div>
                        <div class="ci-result-line">📊 Registros leídos: ${stats.total || 0}</div>
                        <div class="ci-result-line">✅ OTs nuevas importadas: ${stats.inserted || 0}</div>
                        <div class="ci-result-line">⚠️ OTs duplicadas omitidas: ${stats.skipped || 0}</div>`;
                } else if (type === 'ejecucion') {
                    lines = `<div class="ci-result-line">✅ <strong>Cierre Exitoso</strong></div>
                        <div class="ci-result-line">📝 OTs cerradas: ${stats.insertados || 0}</div>
                        <div class="ci-result-line">🔄 OTs actualizadas: ${stats.actualizados || 0}</div>
                        <div class="ci-result-line">❌ Errores: ${stats.errores || 0}</div>`;
                }
                
                lines += `<button class="ci-close-btn" onclick="this.parentElement.style.display='none'">Cerrar</button>`;
                el.innerHTML = lines;
            }

            // === DRAG & DROP GENÉRICO ===
            function setupCIDropZone(dropId, fileInputId, barId, textId, progressId, type) {
                const drop = document.getElementById(dropId);
                const input = document.getElementById(fileInputId);
                if (!drop || !input) return;
                
                // Click to upload
                drop.addEventListener('click', (e) => {
                    // Evitar doble trigger si se hace click en el input invisible
                    if(e.target !== input) input.click();
                });

                // Drag events
                drop.addEventListener('dragover', (e) => { 
                    e.preventDefault(); 
                    drop.classList.add('dragover'); 
                });
                
                drop.addEventListener('dragleave', () => { 
                    drop.classList.remove('dragover'); 
                });
                
                drop.addEventListener('drop', (e) => {
                    e.preventDefault();
                    drop.classList.remove('dragover');
                    if (e.dataTransfer.files.length > 0) {
                        input.files = e.dataTransfer.files;
                        handleCIUpload(e.dataTransfer.files[0], barId, textId, progressId, type);
                    }
                });
                
                // File input change
                input.addEventListener('change', () => {
                    if (input.files.length > 0) handleCIUpload(input.files[0], barId, textId, progressId, type);
                });
            }

            // === UPLOAD UNIFICADO CON FEEDBACK VISUAL ===
            async function handleCIUpload(file, barId, textId, progressId, type) {
                const bar = document.getElementById(barId);
                const text = document.getElementById(textId);
                const progress = document.getElementById(progressId);
                
                // Ocultar resultado anterior
                let resultId = '';
                if (type === 'hh') resultId = 'hhResult';
                else if (type === 'mant') resultId = 'mantResult';
                else if (type === 'sic') resultId = 'sicResult';
                else if (type === 'ejecucion') resultId = 'ejecucionResult';
                
                const resultEl = document.getElementById(resultId);
                if (resultEl) resultEl.style.display = 'none';
                
                // Mostrar barra de progreso
                progress.style.display = 'block';
                bar.style.width = '10%';
                bar.style.background = ''; 
                
                // Mensaje inicial
                text.textContent = ' Subiendo archivo...';
                
                const formData = new FormData();
                
                // Endpoint y campo según tipo
                let endpoint, fieldName;
                if (type === 'hh') {
                    endpoint = 'api/importar_planificacion.php';
                    fieldName = 'archivo';
                } else if (type === 'mant') {
                    endpoint = 'api/carga_mantencion.php';
                    fieldName = 'mantencion_file';
                } else if (type === 'sic') {
                    endpoint = 'api/import_sic.php';
                    fieldName = 'sicFile';
                } else if (type === 'ejecucion') {
                    endpoint = 'api/importar_ejecucion.php';
                    fieldName = 'archivo';
                }
                
                formData.append(fieldName, file);
                
                try {
                    // Simular avance visual mientras sube/procesa
                    let fakeProgress = 10;
                    const interval = setInterval(() => {
                        if (fakeProgress < 90) {
                            fakeProgress += Math.random() * 5;
                            bar.style.width = fakeProgress + '%';
                            
                            // Mensajes dinámicos según el tipo
                            if (type === 'ejecucion') {
                                if (fakeProgress < 30) text.textContent = '📂 Leyendo estructura CSV...';
                                else if (fakeProgress < 60) text.textContent = '⚙️ Vinculando OTs y Técnicos...';
                                else text.textContent = '💾 Guardando registros reales...';
                            } else {
                                text.textContent = '⚙️ Procesando datos... (' + Math.floor(fakeProgress) + '%)';
                            }
                        }
                    }, 500);

                    const response = await fetch(endpoint, { method: 'POST', body: formData, credentials: 'include' });
                    
                    clearInterval(interval); // Detener simulación
                    
                    const rawText = await response.text();
                    
                    let result;
                    try { 
                        result = JSON.parse(rawText); 
                    } catch { 
                        throw new Error('El servidor devolvió una respuesta no válida (no JSON). Revisa los logs del servidor.'); 
                    }
                    
                    bar.style.width = '100%';
                    text.textContent = '✅ Proceso finalizado';
                    
                    setTimeout(() => {
                        progress.style.display = 'none';
                        
                        const success = result.success !== false;
                        const stats = result.stats || result.data || result;
                        const periodo = result.periodo || null;
                        
                        showCIResult(type, success, success ? stats : { error: result.error || rawText.substring(0, 200) }, periodo);
                        addToBitacora(type, file.name, stats, success);
                        
                        // Limpiar input
                        const inputId = type === 'hh' ? 'hhFile' : type === 'mant' ? 'mantencionFile' : type === 'sic' ? 'sicFile' : 'ejecucionFile';
                        const inputEl = document.getElementById(inputId);
                        if (inputEl) inputEl.value = '';
                    }, 500);
                    
                } catch (error) {
                    bar.style.width = '100%';
                    bar.style.background = '#ef4444';
                    text.textContent = '❌ Error: ' + error.message;
                    
                    setTimeout(() => {
                        progress.style.display = 'none';
                        showCIResult(type, false, { error: error.message });
                        addToBitacora(type, file.name, { error: error.message }, false);
                    }, 500);
                }
            }

            // === INICIALIZAR ZONAS DE CARGA ===
            (function initCI() {
                setupCIDropZone('dropZoneEjecucion', 'ejecucionFile', 'ejecucionProgressBar', 'ejecucionProgressText', 'ejecucionProgressContainer', 'ejecucion');
                setupCIDropZone('dropZoneHH', 'hhFile', 'hhProgressBar', 'hhProgressText', 'hhProgressContainer', 'hh');
                setupCIDropZone('dropZoneMantencion', 'mantencionFile', 'mantProgressBar', 'mantProgressText', 'mantProgressContainer', 'mant');
                setupCIDropZone('dropZoneSIC', 'sicFile', 'sicProgressBar', 'sicProgressText', 'sicProgressContainer', 'sic');
                
                // Renderizar bitácoras al cargar
                renderBitacora('ejecucion');
                renderBitacora('hh');
                renderBitacora('mant');
                renderBitacora('sic');
            })();
            </script>
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
                    <h4 style="margin-bottom:1rem;">Detalle / Edición OT</h4>
                    <div id="otDetailContent"><p style="color:#94a3b8; text-align:center; margin-top:2rem;">Selecciona una OT para ver detalles</p></div>
                    <div class="detail-actions" id="otActions" style="display:none;"><button class="btn-save" onclick="saveOT()">💾 Guardar</button><button class="btn-cancel" onclick="clearDetail()">❌ Cancelar</button><button class="btn-volver" style="background:#64748b; margin-top:0;" onclick="showModule('home')">🏠 Volver a Home</button></div>
                </div>
            </div>
        </section>

        <section id="tracking" class="module-section">
            <div style="max-width:1000px; margin:0 auto; height:100%; display:flex; flex-direction:column;">
                <!-- Header & Buscador -->
                <div style="margin-bottom:1rem;">
                    <h3 style="margin-bottom:0.5rem;">📡 Tracking en Terreno</h3>
                    <p style="font-size:0.9rem; color:#64748b; margin-top:0;">Busca una OT para ver su historial, evidencias e incidencias.</p>
                </div>

                <!-- Barra de Búsqueda Inteligente -->
                <div class="search-container" style="position:relative; margin-bottom:1.5rem;">
                    <input type="text" class="search-input" id="trackingSearch" placeholder="🔍 Buscar OT por código, equipo o área..." oninput="handleTrackingSearch()">
                    <div class="search-dropdown" id="trackingDropdown"></div>
                </div>

                <!-- Contenedor Principal (Oculto hasta seleccionar OT) -->
                <div id="trackingContent" style="display:none; flex:1; overflow-y:auto;">
                    
                    <!-- Info Cabecera OT -->
                    <div class="card" style="margin-bottom:1rem; padding:1rem; border-left:4px solid var(--primary);">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <div>
                                <h4 style="margin:0; font-size:1.1rem;" id="trackOtCode">OT-XXXX</h4>
                                <span style="font-size:0.85rem; color:#64748b;" id="trackOtDesc">Descripción...</span>
                            </div>
                            <span id="trackOtStatus" class="badge b-pen">Pendiente</span>
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.5rem;">
                        
                        <!-- COLUMNA IZQUIERDA: Timeline & Progreso -->
                        <div class="card" style="padding:1.5rem;">
                            <h4 style="margin-top:0;">📊 Progreso & Hitos</h4>
                            
                            <!-- Barra de Progreso HH -->
                            <div style="margin-bottom:1.5rem;">
                                <div style="display:flex; justify-content:space-between; font-size:0.85rem; margin-bottom:0.5rem;">
                                    <span>HH Reales / HH Programadas</span>
                                    <strong id="trackHhRatio">0 / 0</strong>
                                </div>
                                <div class="progress-container">
                                    <div class="progress-bar" id="trackProgressBar" style="width:0%;"></div>
                                </div>
                            </div>

                            <!-- Timeline Vertical -->
                            <div style="border-left:2px solid #e2e8f0; padding-left:1.5rem; margin-top:1rem;">
                                <div id="timelineContainer">
                                    <!-- Se llena dinámicamente -->
                                    <div style="text-align:center; color:#94a3b8; padding:1rem;">Selecciona una OT para ver hitos</div>
                                </div>
                            </div>
                        </div>

                        <!-- COLUMNA DERECHA: Evidencias & Incidencias -->
                        <div class="card" style="padding:1.5rem;">
                            <h4 style="margin-top:0;">⚠️ Incidencias & Evidencias</h4>
                            
                            <!-- Formulario Rápido de Incidencia -->
                            <div style="background:#f8fafc; padding:1rem; border-radius:0.5rem; margin-bottom:1rem; border:1px solid #e2e8f0;">
                                <label style="font-size:0.8rem; font-weight:600; color:#475569;">Reportar Nueva Incidencia</label>
                                <select id="incType" style="width:100%; padding:0.5rem; margin:0.5rem 0; border:1px solid #cbd5e1; border-radius:0.5rem;">
                                    <option value="acceso">🚫 Acceso Denegado</option>
                                    <option value="material">📦 Falta Material/Repuesto</option>
                                    <option value="seguridad">⚠️ Riesgo Seguridad</option>
                                    <option value="otro">📝 Otro</option>
                                </select>
                                <textarea id="incDesc" rows="2" placeholder="Detalle la incidencia (ej: Encargado no permite entrada...)" style="width:100%; padding:0.5rem; border:1px solid #cbd5e1; border-radius:0.5rem; resize:none;"></textarea>
                                
                                <div style="display:flex; gap:0.5rem; margin-top:0.5rem;">
                                    <button onclick="addIncidence()" style="flex:1; background:#ef4444; color:white; border:none; padding:0.5rem; border-radius:0.5rem; cursor:pointer; font-size:0.85rem;">⚠️ Registrar</button>
                                    <button onclick="document.getElementById('fileInput').click()" style="flex:1; background:#fff; border:1px solid #cbd5e1; padding:0.5rem; border-radius:0.5rem; cursor:pointer; font-size:0.85rem;">📷 Foto/PDF</button>
                                    <input type="file" id="fileInput" accept="image/*,.pdf" style="display:none" onchange="uploadEvidence(this)">
                                </div>
                            </div>

                            <!-- Lista de Incidencias Recientes -->
                            <h5 style="font-size:0.9rem; color:#475569; margin-bottom:0.5rem;">Historial de Incidencias</h5>
                            <div id="incidencesList" style="max-height:300px; overflow-y:auto;">
                                <!-- Se llena dinámicamente -->
                                <div style="text-align:center; color:#94a3b8; padding:1rem; font-size:0.85rem;">Sin incidencias registradas</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- MÓDULO 4: DASHBOARD KPIs (EMILY VERSION) -->
        <!-- MÓDULO 4: DASHBOARD KPIs -->
        <section id="kpis" class="module-section" style="padding: 2rem; max-width: 1400px; margin: 0 auto; overflow-y: auto;">
            
            <!-- Header con título y botón actualizar -->
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
                <div>
                    <h2 style="margin:0; color:#1e293b; font-size:1.5rem; font-weight:700;">📊 Panel de Control Operativo</h2>
                    <p style="color:#64748b; margin:0.25rem 0 0 0; font-size:0.9rem;">Análisis en tiempo real de mantenimientos programados</p>
                </div>
                
                <div style="display:flex; gap:0.75rem; align-items:center; flex-wrap:wrap;">
                    <select id="filterYear" onchange="applyFilters()" style="padding:0.5rem; border-radius:0.5rem; border:1px solid #cbd5e1; background:#fff; font-size:0.85rem;">
                        <option value="2026" <?= date('Y') == 2026 ? 'selected' : '' ?>>2026</option>
                        <option value="2025" <?= date('Y') == 2025 ? 'selected' : '' ?>>2025</option>
                    </select>
                    <select id="filterMonth" onchange="applyFilters()" style="padding:0.5rem; border-radius:0.5rem; border:1px solid #cbd5e1; background:#fff; font-size:0.85rem;">
                        <option value="">Todo el año</option>
                        <!-- Las opciones se generan dinámicamente o estáticamente -->
                        <option value="enero" <?= date('n') == 1 ? 'selected' : '' ?>>Enero</option>
                        <option value="febrero" <?= date('n') == 2 ? 'selected' : '' ?>>Febrero</option>
                        <option value="marzo" <?= date('n') == 3 ? 'selected' : '' ?>>Marzo</option>
                        <option value="abril" <?= date('n') == 4 ? 'selected' : '' ?>>Abril</option>
                        <option value="mayo" <?= date('n') == 5 ? 'selected' : '' ?>>Mayo</option>
                        <option value="junio" <?= date('n') == 6 ? 'selected' : '' ?>>Junio</option>
                        <option value="julio" <?= date('n') == 7 ? 'selected' : '' ?>>Julio</option>
                        <option value="agosto" <?= date('n') == 8 ? 'selected' : '' ?>>Agosto</option>
                        <option value="septiembre" <?= date('n') == 9 ? 'selected' : '' ?>>Septiembre</option>
                        <option value="octubre" <?= date('n') == 10 ? 'selected' : '' ?>>Octubre</option>
                        <option value="noviembre" <?= date('n') == 11 ? 'selected' : '' ?>>Noviembre</option>
                        <option value="diciembre" <?= date('n') == 12 ? 'selected' : '' ?>>Diciembre</option>
                    </select>
                    <select id="filterWeek" onchange="applyFilters()" style="padding:0.5rem; border-radius:0.5rem; border:1px solid #cbd5e1; background:#fff; font-size:0.85rem;">
                        <option value="">Todo el mes</option>
                    </select>
                    <button onclick="loadKpis()" style="background:#10b981; color:white; border:none; padding:0.5rem 1rem; border-radius:0.5rem; cursor:pointer; font-weight:600; font-size:0.85rem;">
                        🔄 Actualizar
                    </button>
                    <!-- 🆕 AGREGAR AQUÍ: Switch IA -->
                    <div class="ai-toggle-container" id="aiToggleContainer">
                        <span class="ai-toggle-label">🤖 IA</span>
                        <div class="ai-toggle-switch" id="aiToggleSwitch" onclick="toggleAI()"></div>
                    </div>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════════════════════ -->
            <!-- FILA 1: FICHAS KPI (6 columnas)                            -->
            <!-- ═══════════════════════════════════════════════════════════ -->
            <div style="display:grid; grid-template-columns: repeat(6, 1fr); gap:1rem; margin-bottom:1rem;">
                
                <!-- 🥇 Ficha 1: TOTAL HHs PLANIFICADAS (Hero - Destacada) -->
                <div style="background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%); padding:1.5rem; border-radius:1rem; box-shadow:0 4px 12px rgba(59,130,246,0.3); color:white; position:relative; overflow:hidden;">
                    <div style="position:absolute; top:-15px; right:-15px; font-size:5rem; opacity:0.12;">⚡</div>
                    <div style="font-size:0.75rem; font-weight:700; text-transform:uppercase; opacity:0.9; letter-spacing:0.5px;">
                        Total HHs Planificadas
                    </div>
                    <div style="display:flex; align-items:baseline; gap:0.4rem; margin-top:0.5rem;">
                        <span id="kpi-total-hh" style="font-size:2rem; font-weight:800; font-family:monospace; letter-spacing:-1px;">--</span>
                        <span style="font-size:0.9rem; opacity:0.85; font-weight:500;">HH</span>
                    </div>
                    <div style="font-size:0.7rem; opacity:0.85; margin-top:0.25rem; display:flex; align-items:center; gap:0.25rem;">
                        <span>📊</span> Carga acumulada del periodo
                    </div>
                </div>

                <!-- 🆕 Ficha 2: HH DISPONIBLES vs DEMANDA -->
                <div style="background:white; padding:1.5rem; border-radius:1rem; box-shadow:0 4px 6px -1px rgba(0,0,0,0.1); border-left:4px solid #f59e0b; position:relative; overflow:hidden;">
                    <div style="position:absolute; top:-10px; right:-10px; font-size:4rem; opacity:0.08;">👷</div>
                    <div style="font-size:0.8rem; color:#64748b; font-weight:600; text-transform:uppercase;">HH Disponibles (Plan)</div>
                    <div style="display:flex; align-items:baseline; gap:0.3rem; margin-top:0.5rem;">
                        <span id="kpi-hh-disponibles" style="font-size:2rem; font-weight:700; color:#1e293b;">--</span>
                        <span style="font-size:0.85rem; color:#64748b;">HH</span>
                    </div>
                    <div style="margin-top:0.5rem; display:flex; align-items:center; gap:0.5rem;">
                        <span id="kpi-hh-cobertura" style="font-size:0.8rem; font-weight:700; padding:2px 8px; border-radius:10px; background:#dcfce7; color:#166534;">--%</span>
                        <span style="font-size:0.7rem; color:#64748b;">Cobertura</span>
                    </div>
                    <div style="font-size:0.7rem; color:#94a3b8; margin-top:0.3rem;">
                        👷 <span id="kpi-tecnicos-plan">--</span> técnicos planificados
                    </div>
                </div>
                <!-- Ficha 3: SLA Cumplido -->
                <div style="background:white; padding:1.5rem; border-radius:1rem; box-shadow:0 4px 6px -1px rgba(0,0,0,0.1); border-left:4px solid #10b981;">
                    <div style="font-size:0.8rem; color:#64748b; font-weight:600; text-transform:uppercase;">SLA Cumplido</div>
                    <div style="font-size:2rem; font-weight:700; color:#1e293b; margin-top:0.5rem;">
                        <span id="kpi-sla">--</span>
                    </div>
                    <div style="font-size:0.75rem; color:#64748b; margin-top:0.25rem;">
                        ✅ De OTs completadas a tiempo
                    </div>
                </div>
                <!-- 🆕 Ficha 4: DISTRIBUCIÓN DE TURNOS -->
                <div style="background:white; padding:1.5rem; border-radius:1rem; box-shadow:0 4px 6px -1px rgba(0,0,0,0.1); border-left:4px solid #8b5cf6; position:relative; overflow:hidden;">
                    <div style="position:absolute; top:-10px; right:-10px; font-size:4rem; opacity:0.08;">🌙</div>
                    <div style="font-size:0.8rem; color:#64748b; font-weight:600; text-transform:uppercase;">Distribución Turnos</div>
                    
                    <div style="margin-top:0.75rem; display:flex; flex-direction:column; gap:0.5rem;">
                        <!-- Barra Día -->
                        <div style="display:flex; align-items:center; justify-content:space-between; font-size:0.8rem;">
                            <span style="color:#64748b;">☀️ Día</span>
                            <strong id="kpi-hh-dia" style="color:#1e293b;">-- HH</strong>
                        </div>
                        <div style="height:6px; background:#e2e8f0; border-radius:3px; overflow:hidden;">
                            <div id="bar-hh-dia" style="height:100%; width:0%; background:#f59e0b; transition:width 0.5s ease;"></div>
                        </div>

                        <!-- Barra Noche -->
                        <div style="display:flex; align-items:center; justify-content:space-between; font-size:0.8rem;">
                            <span style="color:#64748b;">🌙 Noche</span>
                            <strong id="kpi-hh-noche" style="color:#1e293b;">-- HH</strong>
                        </div>
                        <div style="height:6px; background:#e2e8f0; border-radius:3px; overflow:hidden;">
                            <div id="bar-hh-noche" style="height:100%; width:0%; background:#6366f1; transition:width 0.5s ease;"></div>
                        </div>
                    </div>
                </div>
                <!-- Ficha 5: OTs Cerradas -->
                <div style="background:white; padding:1.5rem; border-radius:1rem; box-shadow:0 4px 6px -1px rgba(0,0,0,0.1); border-left:4px solid #8b5cf6;">
                    <div style="font-size:0.8rem; color:#64748b; font-weight:600; text-transform:uppercase;">OTs Cerradas</div>
                    <div style="font-size:2rem; font-weight:700; color:#1e293b; margin-top:0.5rem;">
                        <span id="kpi-ots-closed">--</span>
                    </div>
                    <div style="font-size:0.75rem; color:#64748b; margin-top:0.25rem;">
                        📦 Completadas en el periodo
                    </div>
                </div>

                <!-- Ficha 6: OTs en Riesgo (CLICKEABLE) -->
                <div id="kpi-risk-card" 
                    onclick="toggleRiskMode()" 
                    style="background:white; padding:1.5rem; border-radius:1rem; box-shadow:0 4px 6px -1px rgba(0,0,0,0.1); border-left:4px solid #ef4444; cursor:pointer; position:relative; transition:all 0.3s;">
                    
                    <!-- Botón X para limpiar filtro (solo visible en modo riesgo) -->
                    <button id="kpi-risk-close" 
                            onclick="event.stopPropagation(); clearRiskMode();" 
                            style="position:absolute; top:0.5rem; right:0.5rem; background:#fee2e2; color:#ef4444; border:none; width:24px; height:24px; border-radius:50%; cursor:pointer; font-weight:bold; display:none; align-items:center; justify-content:center; font-size:0.85rem;"
                            title="Limpiar filtro">
                        ✕
                    </button>
                    
                    <div style="font-size:0.8rem; color:#64748b; font-weight:600; text-transform:uppercase;">OTs en Riesgo</div>
                    <div style="font-size:2rem; font-weight:700; color:#1e293b; margin-top:0.5rem;">
                        <span id="kpi-ots-risk">--</span>
                    </div>
                    <div id="kpi-risk-hint" style="font-size:0.75rem; color:#ef4444; margin-top:0.25rem;">
                        ⚠️ Click para ver detalle
                    </div>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════════════════════ -->
            <!-- FILA 2: GRÁFICOS (70% Ranking + 30% Torta)                 -->
            <!-- ═══════════════════════════════════════════════════════════ -->
            <div style="display:grid; grid-template-columns: 7fr 3fr; gap:1.5rem; margin-bottom:2rem;">
                
                <!-- 🏆 RANKING DE ESPECIALIDADES (70% del espacio) -->
                <div style="background:white; padding:1.5rem; border-radius:1rem; box-shadow:0 4px 6px -1px rgba(0,0,0,0.1); border-top:4px solid #3b82f6;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem; padding-bottom:1rem; border-bottom:2px solid #f1f5f9;">
                        <div>
                            <h3 style="margin:0; font-size:1.15rem; color:#1e293b; font-weight:700;">
                                🏆 Ranking de Especialidades
                            </h3>
                            <p style="margin:0.25rem 0 0 0; font-size:0.78rem; color:#64748b;">
                                Distribución de Horas Hombre Planificadas
                            </p>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-size:0.7rem; color:#64748b; text-transform:uppercase; font-weight:600;">Total</div>
                            <div id="kpi-total-hh-esp" style="font-size:1.3rem; font-weight:800; color:#3b82f6; font-family:monospace;">--</div>
                            <div style="font-size:0.7rem; color:#94a3b8;">HH</div>
                        </div>
                    </div>
                    <div id="containerEspecialidades" style="display:flex; flex-direction:column; gap:0.6rem; max-height:400px; overflow-y:auto; padding-right:0.5rem;">
                        <!-- Se llena con JS -->
                    </div>
                </div>

                <!-- 🥧 DISTRIBUCIÓN POR ESTADO (30% del espacio) -->
                <div style="background:white; padding:1.5rem; border-radius:1rem; box-shadow:0 4px 6px -1px rgba(0,0,0,0.1);">
                    <h3 style="margin:0 0 1rem 0; font-size:1rem; color:#1e293b; font-weight:700;">
                        🥧 Distribución por Estado
                    </h3>
                    <div style="position:relative; height:280px;">
                        <canvas id="chartEstados"></canvas>
                    </div>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════════════════════ -->
            <!-- FILA 3: TABLA DE OTs REPROGRAMADAS                         -->
            <!-- ═══════════════════════════════════════════════════════════ -->
            <!-- Tabla de OTs Reprogramadas (Comodín) -->
            <div style="background:white; padding:1.5rem; border-radius:1rem; box-shadow:0 4px 6px -1px rgba(0,0,0,0.1); margin-top:1.5rem;">
                <h3 id="tablaComodinTitle" style="margin-top:0; font-size:1.1rem; color:#1e293b;">🔄 OTs Reprogramadas (Mayor Impacto)</h3>
                <div style="overflow-x:auto;">
                    <table style="width:100%; border-collapse:collapse; margin-top:1rem;">
                        <!-- ✅ THEAD ESTÁTICO (fuera del JS) -->
                        <thead>
                            <tr style="background:#f8fafc; border-bottom:2px solid #e2e8f0;">
                                <th style="padding:0.75rem; text-align:left; font-size:0.85rem; color:#64748b;">ID Previsión</th>
                                <th style="padding:0.75rem; text-align:left; font-size:0.85rem; color:#64748b;">Equipo</th>
                                <th style="padding:0.75rem; text-align:center; font-size:0.85rem; color:#64748b;">Estado</th>
                                <th id="thCol4" style="padding:0.75rem; text-align:center; font-size:0.85rem; color:#64748b;">Veces Reprog.</th>
                                <th style="padding:0.75rem; text-align:center; font-size:0.85rem; color:#64748b;">Fecha Programada</th>
                                <th id="thCol6" style="padding:0.75rem; text-align:center; font-size:0.85rem; color:#64748b;">Retraso</th>
                            </tr>
                        </thead>
                        <!-- ✅ TBODY DINÁMICO (se llena con JS) -->
                        <tbody id="tablaReprogramadas">
                            <!-- Las filas se generan aquí -->
                        </tbody>
                    </table>
                </div>
            </div>

        </section>

        <!-- MÓDULO 7: VERTICALES 
        <?php 
            // 1. Leer el rol desde la clave CORRECTA de la sesión (recinto_rol)
            $rolActual = $_SESSION['recinto_rol'] ?? '';
            
            // 2. Definir si es Admin Hospital (o admin general) ANTES de usarlo en HTML
            $esAdmin = ($rolActual === 'admin_hospital' || $rolActual === 'admin');
        ?>
        -->
        <section id="verticales" class="module-section">
            <div style="max-width:900px; margin:0 auto; height:100%; display:flex; flex-direction:column;">
                
                <!-- Header del Módulo -->
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                    <div>
                        <h3 style="margin:0;">🏢 Mantenedor de Verticales</h3>
                        <p style="font-size:0.9rem; color:#64748b; margin-top:0.25rem;">Gestión de áreas técnicas y responsables</p>
                    </div>
                    
                    <!-- Botón Nueva Vertical: Visible SOLO si $esAdmin es TRUE -->
                    <?php 
                        $rolUsuario = $_SESSION['user_role'] ?? $_SESSION['role_name'] ?? '';
                        $esAdmin = in_array($rolUsuario, ['admin', 'admin_hospital', 'admin_hosp']);
                    ?>

                    <!-- Botón Nueva Vertical -->
                    <?php if ($esAdmin): ?>
                    <button onclick="abrirModalVerticales()" style="...">➕ Nueva Vertical</button>
                    <?php endif; ?>
                </div>
                
                <!-- Tabla de Verticales -->
                <div class="card" style="flex:1; overflow:auto; padding:0;">
                    <table style="width:100%; border-collapse:collapse; min-width:600px;">
                        <thead>
                            <tr style="background:#f1f5f9; position:sticky; top:0;">
                                <th style="padding:1rem; text-align:left; border-bottom:2px solid #e2e8f0; font-weight:600; color:#475569;">Nombre Vertical</th>
                                <th style="padding:1rem; text-align:left; border-bottom:2px solid #e2e8f0; font-weight:600; color:#475569;">Responsable</th>
                                <th style="padding:1rem; text-align:left; border-bottom:2px solid #e2e8f0; font-weight:600; color:#475569;">Especialidad Principal</th>
                                <th style="padding:1rem; text-align:left; border-bottom:2px solid #e2e8f0; font-weight:600; color:#475569;">Contacto Email</th>
                                <th style="padding:1rem; text-align:center; border-bottom:2px solid #e2e8f0; font-weight:600; color:#475569;">Estado</th>
                                <!-- Columna Acción SIEMPRE visible para editar (si hay datos) -->
                                <th style="padding:1rem; text-align:center; border-bottom:2px solid #e2e8f0; font-weight:600; color:#475569;">Acción</th>
                            </tr>
                        </thead>
                        <tbody id="tablaVerticalesBody">
                            <tr><td colspan="6" style="text-align:center; padding:2rem; color:#64748b;">Cargando...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- MODAL NUEVA/EDITAR VERTICAL -->
        <div id="modalVerticales" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); backdrop-filter:blur(4px); z-index:2000; justify-content:center; align-items:center; padding:1rem;">
            <div style="background:#fff; padding:0; border-radius:1rem; width:90%; max-width:500px; box-shadow:0 20px 60px rgba(0,0,0,0.3); overflow:hidden;">
                
                <!-- Header Modal -->
                <div style="padding:1.25rem 1.5rem; border-bottom:1px solid #e2e8f0; background:#f8fafc; display:flex; justify-content:space-between; align-items:center;">
                    <h3 id="tituloModalVerticales" style="margin:0; font-size:1.1rem; font-weight:600; color:#2D3748;">Nueva Vertical</h3>
                    <button onclick="cerrarModalVerticales()" style="background:none; border:none; font-size:1.5rem; color:#94a3b8; cursor:pointer; line-height:1;">&times;</button>
                </div>

                <!-- Formulario -->
                <form id="formVerticales" onsubmit="guardarVertical(event)" style="padding:1.5rem;">
                    <input type="hidden" id="v_id" name="id_vertical">
                    
                    <div style="margin-bottom:1rem;">
                        <label style="display:block; font-size:0.85rem; font-weight:600; color:#475569; margin-bottom:0.5rem;">Nombre de la Vertical *</label>
                        <input type="text" id="v_nombre" name="nombre_vertical" required placeholder="Ej: Climatización, Electricidad..." style="width:100%; padding:0.75rem; border:1px solid #cbd5e1; border-radius:0.5rem; font-size:0.95rem;">
                    </div>

                    <div style="margin-bottom:1rem;">
                        <label style="display:block; font-size:0.85rem; font-weight:600; color:#475569; margin-bottom:0.5rem;">Nombre del Responsable *</label>
                        <input type="text" id="v_responsable" name="nombre_responsable" required placeholder="Nombre completo del Jefe/Supervisor" style="width:100%; padding:0.75rem; border:1px solid #cbd5e1; border-radius:0.5rem; font-size:0.95rem;">
                    </div>

                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem; margin-bottom:1rem;">
                        <div>
                            <label style="display:block; font-size:0.85rem; font-weight:600; color:#475569; margin-bottom:0.5rem;">Especialidad Principal</label>
                            <select id="v_especialidad" name="cod_especialidad_principal" style="width:100%; padding:0.75rem; border:1px solid #cbd5e1; border-radius:0.5rem; font-size:0.95rem; background:#fff;">
                                <option value="">Seleccionar...</option>
                                <option value="M-CLIMATIZACION">M-CLIMATIZACION</option>
                                <option value="M-ELECTRICIDAD">M-ELECTRICIDAD</option>
                                <option value="M-ELECTRONICA">M-ELECTRONICA</option>
                                <option value="M-GASFITERIA">M-GASFITERIA</option>
                                <option value="M-POLIVALENTE">M-POLIVALENTE</option>
                                <option value="M-JARDINERIA">M-JARDINERIA</option>
                                <option value="M-INFRAESTRUCTURA">M-INFRAESTRUCTURA</option>
                            </select>
                        </div>
                        <div>
                            <label style="display:block; font-size:0.85rem; font-weight:600; color:#475569; margin-bottom:0.5rem;">Email Contacto</label>
                            <input type="email" id="v_contacto" name="contacto_email" placeholder="correo@hospital.cl" style="width:100%; padding:0.75rem; border:1px solid #cbd5e1; border-radius:0.5rem; font-size:0.95rem;">
                        </div>
                    </div>

                    <div style="display:flex; gap:0.75rem; justify-content:flex-end; margin-top:1.5rem;">
                        <button type="button" onclick="cerrarModalVerticales()" style="padding:0.6rem 1.2rem; border:1px solid #e2e8f0; border-radius:0.5rem; background:#fff; cursor:pointer; font-weight:500; color:#475569;">Cancelar</button>
                        <button type="submit" style="padding:0.6rem 1.2rem; border:none; border-radius:0.5rem; background:var(--primary); color:#fff; cursor:pointer; font-weight:600;">💾 Guardar</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════ -->
        <!-- MÓDULO 8: RECURSOS (Rediseñado - Estilo Centro Cargas) -->
        <!-- ═══════════════════════════════════════════════════════ -->
        <section id="recursos" class="module-section">
        <div style="max-width:1200px; margin:0 auto; padding:1rem;">
            <h3 style="margin-bottom:1.5rem; color:#1e293b;">👷 Mantenedor de Recursos</h3>
            
            <!-- Pestañas Modernas -->
            <div style="display:flex; gap:0.5rem; margin-bottom:1.5rem; border-bottom:2px solid #e2e8f0; padding-bottom:0;">
                <button onclick="showResourceTab('tecnicos')" id="tab-tecnicos" class="resource-tab active">👨‍🔧 Técnicos</button>
                <button onclick="showResourceTab('grupos')" id="tab-grupos" class="resource-tab">👥 Grupos</button>
                <button onclick="showResourceTab('turnos')" id="tab-turnos" class="resource-tab">⏱️ Turnos Activos</button>
                <button onclick="showResourceTab('asistencia')" id="tab-asistencia" class="resource-tab">📅 Asistencia Hoy</button>
            </div>

            <!-- BUSCADOR INTELIGENTE -->
            <div style="margin-bottom:1rem; position:relative;">
                <input type="text" id="searchRecursos" placeholder="🔍 Buscar por nombre, grupo o turno..."
                    onkeyup="filterResources()"
                    style="width:100%; padding:0.75rem 1rem 0.75rem 2.5rem; border:1px solid #cbd5e1; border-radius:0.5rem; font-size:0.95rem; box-sizing:border-box;"
                    onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#cbd5e1'">
                <span style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#94a3b8;">🔍</span>
            </div>

            <!-- CONTENEDOR TÉCNICOS -->
            <div id="view-tecnicos">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                    <p style="color:#64748b; font-size:0.9rem;">Gestión de técnicos, especialidades y turnos.</p>
                    <button onclick="openModal('tecnico')" class="btn-primary">➕ Nuevo Técnico</button>
                </div>
                <div class="card" style="padding:0; overflow-x:auto;">
                    <table style="width:100%; border-collapse:separate; border-spacing:0 0.5rem;">
                        <thead>
                            <tr style="background:#f8fafc; color:#64748b; font-size:0.85rem; text-transform:uppercase;">
                                <th style="padding:1rem; text-align:left;">RUT</th>
                                <th style="padding:1rem; text-align:left;">Nombre Completo</th>
                                <th style="padding:1rem; text-align:left;">Especialidad</th>
                                <th style="padding:1rem; text-align:left;">Vertical</th>
                                <th style="padding:1rem; text-align:left;">Turno</th>
                                <th style="padding:1rem; text-align:center;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tablaTecnicosBody">
                            <tr><td colspan="6" style="text-align:center; padding:2rem; color:#94a3b8;">Cargando...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- CONTENEDOR GRUPOS -->
            <div id="view-grupos" style="display:none;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                    <p style="color:#64748b; font-size:0.9rem;">Gestión de grupos de trabajo.</p>
                    <button onclick="openModal('grupo')" class="btn-primary">➕ Nuevo Grupo</button>
                </div>
                <div class="card" style="padding:0; overflow-x:auto;">
                    <table style="width:100%; border-collapse:separate; border-spacing:0 0.5rem;">
                        <thead>
                            <tr style="background:#f8fafc; color:#64748b; font-size:0.85rem; text-transform:uppercase;">
                                <th style="padding:1rem; text-align:left;">Nombre Grupo</th>
                                <th style="padding:1rem; text-align:left;">Vertical Asociada</th>
                                <th style="padding:1rem; text-align:left;">Turno Asignado</th>
                                <th style="padding:1rem; text-align:center;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tablaGruposBody">
                            <tr><td colspan="4" style="text-align:center; padding:2rem; color:#94a3b8;">Cargando...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- CONTENEDOR TURNOS ACTIVOS -->
            <div id="view-turnos" style="display:none;">
                <div style="margin-bottom:1rem;">
                    <p style="color:#64748b; font-size:0.9rem;">Lista unificada de recursos con turno activo.</p>
                </div>
                <div class="card" style="padding:0; overflow-x:auto;">
                    <table style="width:100%; border-collapse:separate; border-spacing:0 0.5rem;">
                        <thead>
                            <tr style="background:#f8fafc; color:#64748b; font-size:0.85rem; text-transform:uppercase;">
                                <th style="padding:1rem; text-align:left;">Tipo</th>
                                <th style="padding:1rem; text-align:left;">Nombre</th>
                                <th style="padding:1rem; text-align:left;">Turno</th>
                                <th style="padding:1rem; text-align:left;">Vertical / Especialidad</th>
                            </tr>
                        </thead>
                        <tbody id="tablaTurnosBody">
                            <tr><td colspan="4" style="text-align:center; padding:2rem; color:#94a3b8;">Cargando...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Contenedor Asistencia -->
            <div id="view-asistencia" style="display:none;">
                <div style="margin-bottom:1rem; display:flex; justify-content:space-between; align-items:center;">
                    <h4 style="margin:0;">Registro Diario: <span id="fechaHoyAsistencia"></span></h4>
                    <button onclick="guardarAsistenciaMasiva()" class="btn-primary">💾 Guardar Cambios</button>
                </div>
                
                <div class="card" style="padding:0; overflow-x:auto;">
                    <table style="width:100%; border-collapse:separate; border-spacing:0 0.5rem;">
                        <thead>
                            <tr style="background:#f8fafc; color:#64748b; font-size:0.85rem; text-transform:uppercase;">
                                <th style="padding:1rem; text-align:left;">Técnico</th>
                                <th style="padding:1rem; text-align:left;">Turno Planif.</th>
                                <th style="padding:1rem; text-align:left;">Estado Real</th>
                                <th style="padding:1rem; text-align:left;">Observaciones</th>
                            </tr>
                        </thead>
                        <tbody id="tablaAsistenciaBody">
                            <tr><td colspan="4" style="text-align:center; padding:2rem;">Cargando lista...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        </section>

        <!-- MODAL GENÉRICO PARA TÉCNICO / GRUPO -->
        <div id="modalRecursos" style="display:none; position:fixed; inset:0; background:rgba(15, 23, 42, 0.6); backdrop-filter:blur(4px); z-index:2000; justify-content:center; align-items:center; padding:1rem;">
            <div style="background:#fff; padding:0; border-radius:1rem; width:90%; max-width:550px; box-shadow:0 25px 50px -12px rgba(0, 0, 0, 0.25); overflow:hidden; animation: slideIn 0.3s ease-out;">
                <div style="padding:1.25rem 1.5rem; border-bottom:1px solid #e2e8f0; background:#f8fafc; display:flex; justify-content:space-between; align-items:center;">
                    <h3 id="tituloModalRecursos" style="margin:0; font-size:1.1rem; font-weight:600; color:#1e293b;">Nuevo Registro</h3>
                    <button onclick="closeModal()" style="background:none; border:none; font-size:1.5rem; color:#94a3b8; cursor:pointer;">&times;</button>
                </div>
                <form id="formRecursos" onsubmit="saveResource(event)" autocomplete="off" style="padding:1.5rem;">
                    <input type="hidden" id="res_type" value="">
                    <input type="hidden" id="res_id" value="">
                    
                    <!-- CAMPOS TÉCNICO -->
                    <div id="fields-tecnico" style="display:none;">
                        <div style="margin-bottom:1rem;">
                            <label style="display:block; font-size:0.85rem; font-weight:600; color:#475569; margin-bottom:0.5rem;">RUT *</label>
                            <input type="text" id="res_rut" placeholder="12.345.678-9" style="width:100%; padding:0.75rem; border:1px solid #cbd5e1; border-radius:0.5rem;">
                        </div>
                        <div style="margin-bottom:1rem;">
                            <label style="display:block; font-size:0.85rem; font-weight:600; color:#475569; margin-bottom:0.5rem;">Nombre Completo *</label>
                            <input type="text" id="res_nombre" placeholder="Nombre Apellido" style="width:100%; padding:0.75rem; border:1px solid #cbd5e1; border-radius:0.5rem;">
                        </div>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1rem;">
                            <div>
                                <label style="display:block; font-size:0.85rem; font-weight:600; color:#475569; margin-bottom:0.5rem;">Vertical</label>
                                <select id="res_vertical_tecnico" style="width:100%; padding:0.75rem; border:1px solid #cbd5e1; border-radius:0.5rem; background:#fff;"></select>
                            </div>
                            <div>
                                <label style="display:block; font-size:0.85rem; font-weight:600; color:#475569; margin-bottom:0.5rem;">Especialidad</label>
                                <select id="res_especialidad" style="width:100%; padding:0.75rem; border:1px solid #cbd5e1; border-radius:0.5rem; background:#fff;"></select>
                            </div>
                        </div>
                    </div>

                    <!-- CAMPOS GRUPO -->
                    <div id="fields-grupo" style="display:none;">
                        <div style="margin-bottom:1rem;">
                            <label style="display:block; font-size:0.85rem; font-weight:600; color:#475569; margin-bottom:0.5rem;">Nombre del Grupo *</label>
                            <input type="text" id="res_nombre_grupo" placeholder="Ej: Equipo A Climatización" style="width:100%; padding:0.75rem; border:1px solid #cbd5e1; border-radius:0.5rem;">
                        </div>
                        <div style="margin-bottom:1rem;">
                            <label style="display:block; font-size:0.85rem; font-weight:600; color:#475569; margin-bottom:0.5rem;">Vertical Asociada</label>
                            <select id="res_vertical_grupo" style="width:100%; padding:0.75rem; border:1px solid #cbd5e1; border-radius:0.5rem; background:#fff;"></select>
                        </div>
                    </div>

                    <!-- CAMPO COMÚN: TURNO -->
                    <div style="margin-bottom:1.5rem;">
                        <label style="display:block; font-size:0.85rem; font-weight:600; color:#475569; margin-bottom:0.5rem;">Tipo de Turno</label>
                        <select id="res_turno" style="width:100%; padding:0.75rem; border:1px solid #cbd5e1; border-radius:0.5rem; background:#fff;">
                            <option value="">Sin Turno Asignado</option>
                        </select>
                    </div>

                    <div style="display:flex; gap:0.75rem; justify-content:flex-end; padding-top:1rem; border-top:1px solid #f1f5f9;">
                        <button type="button" onclick="closeModal()" style="padding:0.6rem 1.2rem; border:1px solid #e2e8f0; border-radius:0.5rem; background:#fff; cursor:pointer;">Cancelar</button>
                        <button type="submit" style="padding:0.6rem 1.2rem; border:none; border-radius:0.5rem; background:var(--primary); color:#fff; cursor:pointer;">💾 Guardar</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
        // === LÓGICA DE RECURSOS ===
        let currentResourceType = 'tecnico';

        function showResourceTab(tab) {
            document.getElementById('view-tecnicos').style.display = tab === 'tecnicos' ? 'block' : 'none';
            document.getElementById('view-grupos').style.display = tab === 'grupos' ? 'block' : 'none';
            document.getElementById('view-turnos').style.display = tab === 'turnos' ? 'block' : 'none';
            
            document.getElementById('tab-tecnicos').classList.toggle('active', tab === 'tecnicos');
            document.getElementById('tab-grupos').classList.toggle('active', tab === 'grupos');
            document.getElementById('tab-turnos').classList.toggle('active', tab === 'turnos');
            
            const searchInput = document.getElementById('searchRecursos');
            if(searchInput) searchInput.value = '';
            
            if (tab === 'tecnicos') cargarTecnicos();
            if (tab === 'grupos') cargarGrupos();
            if (tab === 'turnos') cargarTurnosActivos();
            if (tab === 'asistencia') cargarAsistenciaHoy();
        }

        async function cargarTecnicos() {
            const tbody = document.getElementById('tablaTecnicosBody');
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:2rem; color:#94a3b8;">⏳ Cargando técnicos...</td></tr>';
            try {
                const res = await fetch('/api/recursos.php?action=list_tecnicos');
                const data = await res.json();
                if (!data.success) throw new Error(data.error);
                
                if (data.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:2rem; color:#94a3b8;">No hay técnicos registrados.</td></tr>';
                    return;
                }
                
                tbody.innerHTML = data.data.map(t => `
                    <tr style="background:#fff; transition:background 0.2s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='#fff'">
                        <td style="padding:1rem; border-bottom:1px solid #f1f5f9; font-family:monospace; color:#64748b;">${t.rut || '-'}</td>
                        <td style="padding:1rem; border-bottom:1px solid #f1f5f9; font-weight:600; color:#1e293b;">${t.nombre}</td>
                        
                        <!-- ESPECIALIDAD -->
                        <td style="padding:1rem; border-bottom:1px solid #f1f5f9;">
                            ${t.especialidad_nombre ? `<span class="badge b-pen">${t.especialidad_nombre}</span>` : '<span style="color:#cbd5e1;">-</span>'}
                        </td>
                        
                        <!-- VERTICAL -->
                        <td style="padding:1rem; border-bottom:1px solid #f1f5f9; color:#475569;">${t.nombre_vertical || '-'}</td>
                        
                        <!-- TURNO ACTUAL -->
                        <td style="padding:1rem; border-bottom:1px solid #f1f5f9;">
                            ${t.turno_actual ? `<span class="badge b-blue">${t.turno_actual}</span>` : '<span style="color:#94a3b8; font-style:italic;">Sin turno</span>'}
                        </td>
                        
                        <!-- CONTACTO -->
                        <td style="padding:1rem; border-bottom:1px solid #f1f5f9; font-size:0.9rem; color:#64748b;">
                            ${t.correo ? `<div>📧 ${t.correo}</div>` : ''}
                            ${t.telefono ? `<div>📱 ${t.telefono}</div>` : ''}
                            ${(!t.correo && !t.telefono) ? '-' : ''}
                        </td>
                        
                        <!-- ACCIONES (Uno al lado del otro) -->
                        <td style="padding:1rem; border-bottom:1px solid #f1f5f9; text-align:center; white-space:nowrap;">
                            <button onclick="editResource('tecnico', ${JSON.stringify(t).replace(/"/g, '&quot;')})" 
                                    title="Editar Ficha" 
                                    style="cursor:pointer; margin-right:8px; background:none; border:none; font-size:1.1rem;">✏️</button>
                            
                            <button onclick="deleteResource('tecnico', ${t.id}, '${t.nombre}')" 
                                    title="Desactivar" 
                                    style="cursor:pointer; background:none; border:none; font-size:1.1rem; color:#ef4444;">🗑️</button>
                        </td>
                    </tr>
                `).join('');
            } catch (err) {
                tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; color:#ef4444; padding:2rem;">❌ Error: ${err.message}</td></tr>`;
            }
        }

        async function cargarGrupos() {
            const tbody = document.getElementById('tablaGruposBody');
            tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:2rem; color:#94a3b8;">⏳ Cargando...</td></tr>';
            try {
                const res = await fetch('/api/recursos.php?action=list_grupos');
                const data = await res.json();
                if (!data.success) throw new Error(data.error);
                
                if (data.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:2rem; color:#94a3b8;">No hay grupos registrados.</td></tr>';
                    return;
                }
                
                tbody.innerHTML = data.data.map(g => `
                    <tr style="background:#fff; transition:background 0.2s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='#fff'">
                        <td style="padding:1rem; border-bottom:1px solid #f1f5f9; font-weight:600; color:#1e293b;">${g.nombre_grupo}</td>
                        <td style="padding:1rem; border-bottom:1px solid #f1f5f9; color:#475569;">${g.nombre_vertical || '-'}</td>
                        <td style="padding:1rem; border-bottom:1px solid #f1f5f9;">${g.turno_actual || '<span style="color:#94a3b8; font-style:italic;">Sin turno</span>'}</td>
                        <td style="padding:1rem; border-bottom:1px solid #f1f5f9; text-align:center;">
                            <button onclick="editResource('grupo', ${JSON.stringify(g).replace(/"/g, '&quot;')})" title="Editar" style="cursor:pointer; margin-right:5px;">✏️</button>
                            <button onclick="deleteResource('grupo', ${g.id}, '${g.nombre_grupo}')" title="Eliminar" style="cursor:pointer; color:#ef4444;">🗑️</button>
                        </td>
                    </tr>
                `).join('');
            } catch (err) {
                tbody.innerHTML = `<tr><td colspan="4" style="text-align:center; color:#ef4444; padding:2rem;">❌ Error: ${err.message}</td></tr>`;
            }
        }

        async function cargarTurnosActivos() {
            const tbody = document.getElementById('tablaTurnosBody');
            tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:2rem; color:#94a3b8;">⏳ Cargando...</td></tr>';
            try {
                const res = await fetch('/api/recursos.php?action=list_con_turno');
                const data = await res.json();
                if (!data.success) throw new Error(data.error);
                
                if (data.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:2rem; color:#94a3b8;">No hay recursos con turno activo.</td></tr>';
                    return;
                }
                
                tbody.innerHTML = data.data.map(r => `
                    <tr style="background:#fff; transition:background 0.2s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='#fff'">
                        <td style="padding:1rem; border-bottom:1px solid #f1f5f9;">
                            <span style="background:${r.tipo_recurso === 'tecnico' ? '#dbeafe' : '#fce7f3'}; color:${r.tipo_recurso === 'tecnico' ? '#1e40af' : '#9d174d'}; padding:0.25rem 0.5rem; border-radius:4px; font-size:0.75rem; font-weight:600;">
                                ${r.tipo_recurso === 'tecnico' ? '👨‍🔧 Técnico' : '👥 Grupo'}
                            </span>
                        </td>
                        <td style="padding:1rem; border-bottom:1px solid #f1f5f9; font-weight:600; color:#1e293b;">${r.nombre_display}</td>
                        <td style="padding:1rem; border-bottom:1px solid #f1f5f9;">
                            ${r.turno_nombre ? `<span class="badge b-blue">${r.turno_nombre}</span>` : '<span style="color:#cbd5e1;">-</span>'}
                        </td>
                        <td style="padding:1rem; border-bottom:1px solid #f1f5f9; color:#64748b;">
                            ${r.vertical_nombre ? `<div>🏢 ${r.vertical_nombre}</div>` : ''}
                            ${r.especialidad_nombre ? `<div>🛠️ ${r.especialidad_nombre}</div>` : ''}
                        </td>
                    </tr>
                `).join('');
            } catch (err) {
                tbody.innerHTML = `<tr><td colspan="4" style="text-align:center; color:#ef4444; padding:2rem;">❌ Error: ${err.message}</td></tr>`;
            }
        }

        function filterResources() {
            const query = document.getElementById('searchRecursos').value.toLowerCase().trim();
            let tbodyId = '';
            if (document.getElementById('view-tecnicos').style.display !== 'none') tbodyId = 'tablaTecnicosBody';
            else if (document.getElementById('view-grupos').style.display !== 'none') tbodyId = 'tablaGruposBody';
            else if (document.getElementById('view-turnos').style.display !== 'none') tbodyId = 'tablaTurnosBody';
            
            if (!tbodyId) return;
            
            const rows = document.querySelectorAll(`#${tbodyId} tr`);
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(query) ? '' : 'none';
            });
        }

        async function cargarAsistenciaHoy() {
            const tbody = document.getElementById('tablaAsistenciaBody');
            const fechaHoy = new Date().toISOString().split('T')[0];
            document.getElementById('fechaHoyAsistencia').textContent = new Date().toLocaleDateString('es-CL', {weekday:'long', year:'numeric', month:'long', day:'numeric'});
            
            tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:2rem;">⏳ Cargando...</td></tr>';
            
            try {
                // Endpoint hipotético: debe devolver técnicos activos y su turno de hoy si existe
                const res = await fetch(`/api/asistencia.php?action=get_daily_list&fecha=${fechaHoy}`);
                const data = await res.json();
                
                if (!data.success) throw new Error(data.error);
                
                if (data.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:2rem;">No hay técnicos programados para hoy.</td></tr>';
                    return;
                }
                
                tbody.innerHTML = data.data.map((t, index) => `
                    <tr>
                        <td style="font-weight:600;">${t.nombre}</td>
                        <td>${t.turno_planificado || 'Descanso'}</td>
                        <td>
                            <select class="status-select" id="status_${index}" data-id="${t.tecnico_id}" style="padding:0.4rem; border-radius:0.3rem; border:1px solid #cbd5e1;">
                                <option value="presente" ${t.estado_real === 'presente' ? 'selected' : ''}>✅ Presente</option>
                                <option value="ausente" ${t.estado_real === 'ausente' ? 'selected' : ''}>❌ Ausente</option>
                                <option value="licencia" ${t.estado_real === 'licencia' ? 'selected' : ''}>🏥 Licencia Médica</option>
                                <option value="vacaciones" ${t.estado_real === 'vacaciones' ? 'selected' : ''}>🏖️ Vacaciones</option>
                                <option value="permiso" ${t.estado_real === 'permiso' ? 'selected' : ''}>📝 Permiso Admin.</option>
                            </select>
                        </td>
                        <td>
                            <input type="text" id="obs_${index}" value="${t.observaciones || ''}" placeholder="Notas..." style="width:100%; padding:0.4rem; border:1px solid #cbd5e1; border-radius:0.3rem;">
                        </td>
                    </tr>
                `).join('');
                
                // Guardar referencia global para el guardado masivo
                window.asistenciaData = data.data;
                
            } catch (err) {
                tbody.innerHTML = `<tr><td colspan="4" style="text-align:center; color:#ef4444; padding:2rem;">❌ Error: ${err.message}</td></tr>`;
            }
        }

        async function guardarAsistenciaMasiva() {
            const registros = [];
            const fechaHoy = new Date().toISOString().split('T')[0];
            
            window.asistenciaData.forEach((t, index) => {
                const status = document.getElementById(`status_${index}`).value;
                const obs = document.getElementById(`obs_${index}`).value;
                registros.push({
                    tecnico_id: t.tecnico_id,
                    fecha: fechaHoy,
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
                    Toast.success('✅ Asistencia guardada correctamente');
                } else {
                    Toast.error('❌ Error: ' + data.error);
                }
            } catch (err) {
                Toast.error('❌ Error de conexión');
            }
        }

        async function openModal(type, item = null) {
            currentResourceType = type;
            const modal = document.getElementById('modalRecursos');
            const form = document.getElementById('formRecursos');
            
            // Resetear formulario
            form.reset();
            document.getElementById('res_id').value = '';
            document.getElementById('res_type').value = type;
            
            // Mostrar/Ocultar campos según tipo
            document.getElementById('fields-tecnico').style.display = type === 'tecnico' ? 'block' : 'none';
            document.getElementById('fields-grupo').style.display = type === 'grupo' ? 'block' : 'none';
            
            // Título dinámico
            document.getElementById('tituloModalRecursos').textContent = item ? `Editar ${type === 'tecnico' ? 'Técnico' : 'Grupo'}` : `Nuevo ${type === 'tecnico' ? 'Técnico' : 'Grupo'}`;
            
            // Cargar selects (Verticales, Especialidades, Turnos)
            await loadSelects(); 
            
            // Si hay item (es edición), llenar datos
            if (item) {
                if (type === 'tecnico') {
                    document.getElementById('res_rut').value = item.rut || '';
                    document.getElementById('res_nombre').value = item.nombre || '';
                    document.getElementById('res_correo').value = item.correo || '';
                    document.getElementById('res_telefono').value = item.telefono || '';
                    
                    // Seleccionar valores en los dropdowns
                    if (item.id_especialidad) document.getElementById('res_especialidad').value = item.id_especialidad;
                    if (item.id_vertical) document.getElementById('res_vertical_tecnico').value = item.id_vertical;
                    if (item.id_tipo_turno) document.getElementById('res_turno').value = item.id_tipo_turno;
                    
                    document.getElementById('res_id').value = item.id;
                } else {
                    // Lógica para grupos...
                    document.getElementById('res_nombre_grupo').value = item.nombre_grupo || '';
                    if (item.id_vertical) document.getElementById('res_vertical_grupo').value = item.id_vertical;
                    if (item.id_tipo_turno) document.getElementById('res_turno').value = item.id_tipo_turno;
                    document.getElementById('res_desc').value = item.descripcion || '';
                    document.getElementById('res_id').value = item.id;
                }
            }
            
            modal.style.display = 'flex';
        }

        function editResource(type, item) {
            openModal(type, item);
        }

        async function deleteResource(type, id, name) {
            if (!confirm(`¿Eliminar ${name}?`)) return;
            try {
                const res = await fetch(`/api/recursos.php?action=delete_${type}&id=${id}`, { method: 'DELETE' });
                const data = await res.json();
                if (data.success) {
                    Toast.success('Eliminado correctamente');
                    if (type === 'tecnico') cargarTecnicos();
                    else cargarGrupos();
                } else {
                    Toast.error(data.error);
                }
            } catch (err) {
                Toast.error('Error al eliminar');
            }
        }

        function closeModal() {
            document.getElementById('modalRecursos').style.display = 'none';
        }

        // Inicializar si estamos en la pestaña
        document.addEventListener('DOMContentLoaded', () => {
            if (document.getElementById('recursos').classList.contains('active')) {
                cargarTecnicos();
            }
        });
        </script>

        <!-- MÓDULO 9: PLANIFICACIÓN INTELIGENTE -->
        <section id="planificacion" class="module-section">
            <div style="height:100%; display:flex; flex-direction:column;">
                
                <!-- Header de Control -->
                <div style="padding:1rem; background:#f8fafc; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <h3 style="margin:0; color:#1e293b;">📅 Planificación Semanal</h3>
                        <p style="font-size:0.85rem; color:#64748b; margin-top:0.25rem;">Arrastra técnicos a las tareas o usa el modal para detalles.</p>
                    </div>
                    <div style="display:flex; gap:0.5rem; align-items:center;">
                        <button onclick="changeWeek(-1)" class="btn-secondary">⬅️ Anterior</button>
                        <span id="currentWeekLabel" style="font-weight:600; min-width:150px; text-align:center;">Cargando...</span>
                        <button onclick="changeWeek(1)" class="btn-secondary">Siguiente ➡️</button>
                        <button onclick="goToToday()" class="btn-primary" style="padding:0.4rem 0.8rem; font-size:0.8rem;">Hoy</button>
                    </div>
                </div>

                <!-- Contenedor Principal: Sidebar + Calendario -->
                <div style="flex:1; display:flex; overflow:hidden;">
                    
                    <!-- Sidebar de Recursos (Técnicos Disponibles) -->
                    <div id="sidebarRecursos" style="width:250px; background:#fff; border-right:1px solid #e2e8f0; padding:1rem; overflow-y:auto;">
                        <h4 style="font-size:0.9rem; color:#64748b; margin-bottom:1rem;">👷 Técnicos Disponibles</h4>
                        <div id="listaTecnicosDraggable" style="display:flex; flex-direction:column; gap:0.5rem;">
                            <!-- Se llena dinámicamente -->
                        </div>
                    </div>

                    <!-- Calendario Semanal Grid -->
                    <div style="flex:1; display:flex; flex-direction:column; background:#f1f5f9;">
                        <!-- Cabecera de Días -->
                        <div id="calendarHeader" style="display:grid; grid-template-columns:repeat(7, 1fr); background:#fff; border-bottom:2px solid #cbd5e1;">
                            <!-- Se llena dinámicamente -->
                        </div>

                        <!-- Cuerpo del Calendario -->
                        <div id="calendarBody" style="flex:1; overflow-y:auto; position:relative;">
                            <div id="calendarGrid" style="display:grid; grid-template-columns:repeat(7, 1fr); height:100%; min-height:500px;">
                                <!-- Celdas de días generadas por JS -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- MODAL DETALLE PLANIFICACIÓN -->
        <div id="modalPlanificacion" style="display:none; position:fixed; inset:0; background:rgba(15, 23, 42, 0.6); backdrop-filter:blur(4px); z-index:2000; justify-content:center; align-items:center;">
            <div style="background:#fff; padding:0; border-radius:1rem; width:90%; max-width:600px; box-shadow:0 25px 50px -12px rgba(0, 0, 0, 0.25); overflow:hidden;">
                <div style="padding:1.25rem 1.5rem; border-bottom:1px solid #e2e8f0; background:#f8fafc; display:flex; justify-content:space-between; align-items:center;">
                    <h3 style="margin:0; font-size:1.1rem; font-weight:600; color:#1e293b;">📋 Detalle de Planificación</h3>
                    <button onclick="closeModalPlanificacion()" style="background:none; border:none; font-size:1.5rem; color:#94a3b8; cursor:pointer;">&times;</button>
                </div>
                <div style="padding:1.5rem;">
                    <form id="formPlanificacion" onsubmit="savePlanificacionDetails(event)">
                        <input type="hidden" id="pf_id">
                        
                        <div style="margin-bottom:1rem;">
                            <label style="display:block; font-size:0.85rem; font-weight:600; color:#475569; margin-bottom:0.25rem;">OT / Equipo</label>
                            <input type="text" id="pf_ot" disabled style="width:100%; padding:0.6rem; border:1px solid #e2e8f0; border-radius:0.5rem; background:#f8fafc; color:#64748b;">
                        </div>

                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1rem;">
                            <div>
                                <label style="display:block; font-size:0.85rem; font-weight:600; color:#475569; margin-bottom:0.25rem;">Fecha Programada</label>
                                <input type="date" id="pf_fecha" required style="width:100%; padding:0.6rem; border:1px solid #cbd5e1; border-radius:0.5rem;">
                            </div>
                            <div>
                                <label style="display:block; font-size:0.85rem; font-weight:600; color:#475569; margin-bottom:0.25rem;">HH Requeridas</label>
                                <input type="number" step="0.5" id="pf_hh" required style="width:100%; padding:0.6rem; border:1px solid #cbd5e1; border-radius:0.5rem;">
                            </div>
                        </div>

                        <div style="margin-bottom:1rem;">
                            <label style="display:block; font-size:0.85rem; font-weight:600; color:#475569; margin-bottom:0.25rem;">Estado</label>
                            <select id="pf_estado" style="width:100%; padding:0.6rem; border:1px solid #cbd5e1; border-radius:0.5rem;">
                                <option value="pendiente_asignacion">Pendiente Asignación</option>
                                <option value="asignada">Asignada</option>
                                <option value="en_ejecucion">En Ejecución</option>
                                <option value="completada">Completada</option>
                                <option value="reprogramada">Reprogramada</option>
                            </select>
                        </div>

                        <div style="margin-bottom:1rem;">
                            <label style="display:block; font-size:0.85rem; font-weight:600; color:#475569; margin-bottom:0.25rem;">Asignaciones Actuales</label>
                            <div id="pf_asignaciones_list" style="max-height:100px; overflow-y:auto; border:1px solid #e2e8f0; border-radius:0.5rem; padding:0.5rem; font-size:0.9rem;">
                                <!-- Lista dinámica -->
                            </div>
                        </div>

                        <div style="display:flex; gap:0.5rem; justify-content:flex-end;">
                            <button type="button" onclick="closeModalPlanificacion()" style="padding:0.5rem 1rem; border:1px solid #e2e8f0; border-radius:0.5rem; background:#fff; cursor:pointer;">Cancelar</button>
                            <button type="submit" style="padding:0.5rem 1rem; border:none; border-radius:0.5rem; background:#3b82f6; color:#fff; cursor:pointer;">💾 Guardar Cambios</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- MÓDULO 3: PRESENTACIÓN (Dashboard Minimalista) -->
        <section id="presentacion" class="module-section">
            <div style="max-width:1000px; margin:0 auto;">
                <h3 style="margin-bottom:1rem;">Panel de Control de Carga</h3>
                
                <!-- KPIs Superiores -->
                <div style="display:grid; grid-template-columns:repeat(4, 1fr); gap:1rem; margin-bottom:2rem;">
                    <div class="kpi-card"><div style="font-size:0.8rem; color:#64748b;">HH Disponibles (Semana)</div><div class="kpi-val" style="color:var(--primary);">1,240</div></div>
                    <div class="kpi-card"><div style="font-size:0.8rem; color:#64748b;">HH Planificadas</div><div class="kpi-val" style="color:var(--success);">980</div></div>
                    <div class="kpi-card"><div style="font-size:0.8rem; color:#64748b;">% Ocupación</div><div class="kpi-val" style="color:var(--warning);">79%</div></div>
                    <div class="kpi-card"><div style="font-size:0.8rem; color:#64748b;">Técnicos Activos</div><div class="kpi-val">18/23</div></div>
                </div>

                <!-- Gráfico de Barras Simple (CSS Only) -->
                <h4 style="margin-bottom:1rem;">Carga por Grupo</h4>
                <div style="background:#fff; padding:1.5rem; border-radius:0.75rem; box-shadow:var(--shadow);">
                    <div style="margin-bottom:1rem;">
                        <div style="display:flex; justify-content:space-between; font-size:0.85rem; margin-bottom:0.5rem;">
                            <span>Pool ClimA</span>
                            <span>85% (340/400 HH)</span>
                        </div>
                        <div style="height:12px; background:#e2e8f0; border-radius:6px; overflow:hidden;">
                            <div style="width:85%; height:100%; background:linear-gradient(90deg, #10b981, #059669);"></div>
                        </div>
                    </div>
                    <div style="margin-bottom:1rem;">
                        <div style="display:flex; justify-content:space-between; font-size:0.85rem; margin-bottom:0.5rem;">
                            <span>Pool ElecB</span>
                            <span>60% (240/400 HH)</span>
                        </div>
                        <div style="height:12px; background:#e2e8f0; border-radius:6px; overflow:hidden;">
                            <div style="width:60%; height:100%; background:linear-gradient(90deg, #3b82f6, #2563eb);"></div>
                        </div>
                    </div>
                    <div>
                        <div style="display:flex; justify-content:space-between; font-size:0.85rem; margin-bottom:0.5rem;">
                            <span>Pool PoliC</span>
                            <span>45% (180/400 HH)</span>
                        </div>
                        <div style="height:12px; background:#e2e8f0; border-radius:6px; overflow:hidden;">
                            <div style="width:45%; height:100%; background:linear-gradient(90deg, #f59e0b, #d97706);"></div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- MODAL DE PLANIFICACIÓN -->
        <div id="planningModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:2000; justify-content:center; align-items:center;">
            <div style="background:#fff; padding:1.5rem; border-radius:1rem; width:90%; max-width:500px; box-shadow:0 20px 60px rgba(0,0,0,0.3);">
                <h3 style="margin-top:0;">Planificar OT</h3>
                <form onsubmit="event.preventDefault(); savePlanning();">
                    <label style="font-size:0.85rem; font-weight:600; color:#475569;">Seleccionar OT</label>
                    <select style="width:100%; padding:0.5rem; border:1px solid #cbd5e1; border-radius:0.5rem; margin-bottom:1rem;">
                        <option>OT-2026-001 - Mantenimiento UMA (40 HH)</option>
                        <option>OT-2026-002 - Revisión Chillers (20 HH)</option>
                    </select>
                    
                    <label style="font-size:0.85rem; font-weight:600; color:#475569;">Asignar a</label>
                    <select style="width:100%; padding:0.5rem; border:1px solid #cbd5e1; border-radius:0.5rem; margin-bottom:1rem;">
                        <option>Pool Clima</option>
                        <option>Técnico Juan Pérez</option>
                    </select>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1rem;">
                        <div>
                            <label style="font-size:0.85rem; font-weight:600; color:#475569;">Fecha Inicio</label>
                            <input type="date" value="<?php echo date('Y-m-d'); ?>" style="width:100%; padding:0.5rem; border:1px solid #cbd5e1; border-radius:0.5rem;">
                        </div>
                        <div>
                            <label style="font-size:0.85rem; font-weight:600; color:#475569;">Hora Inicio</label>
                            <input type="time" value="09:00" style="width:100%; padding:0.5rem; border:1px solid #cbd5e1; border-radius:0.5rem;">
                        </div>
                    </div>

                    <label style="font-size:0.85rem; font-weight:600; color:#475569;">HHs Totales</label>
                    <input type="number" value="40" style="width:100%; padding:0.5rem; border:1px solid #cbd5e1; border-radius:0.5rem; margin-bottom:1rem;">

                    <div style="display:flex; gap:0.5rem; justify-content:flex-end;">
                        <button type="button" onclick="document.getElementById('planningModal').style.display='none'" style="padding:0.5rem 1rem; border:1px solid #e2e8f0; border-radius:0.5rem; background:#fff; cursor:pointer;">Cancelar</button>
                        <button type="submit" style="padding:0.5rem 1rem; border:none; border-radius:0.5rem; background:var(--primary); color:#fff; cursor:pointer;">💾 Grabar Planificación</button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <div class="modal-overlay" id="contratistaModal">
        <div class="modal-box">
            <h3 id="modalTitle">Nueva Vertical</h3>
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
        // 🎯 ESTADO GLOBAL DE FILTROS EN CASCADA
        // Obtener fecha actual para filtros iniciales
        const now = new Date();
        const currentYear = now.getFullYear();
        const currentMonthIndex = now.getMonth(); // 0-11
        const mesesNombres = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
        const currentMonthName = mesesNombres[currentMonthIndex];

        let dashboardFilters = {
            year: String(currentYear),
            month: currentMonthName, // ✅ Iniciar con mes actual
            week: '',
            mode: 'standard',
            especialidad: null,
            especialidadLabel: ''
        };

        // ✅ ÚNICA DECLARACIÓN GLOBAL DE FILTROS
        let currentFilters = { 
            page: 1, search: '', esp: '', estado: '', mes: '', 
            year: '2026', month: '', week: '' 
        };
        let searchTimeout, selectedOTData = null, currentPage = 1, totalPages = 1;

        // === FUNCIÓN DE NAVEGACIÓN Y CARGA DE MÓDULOS ===
        function showModule(moduleId) {
            // 1. Ocultar todas las secciones
            document.querySelectorAll('.module-section').forEach(section => {
                section.classList.remove('active');
                section.style.display = 'none'; // Asegurar ocultado total
            });

            // 2. Mostrar la sección seleccionada
            const targetSection = document.getElementById(moduleId);
            if (targetSection) {
                targetSection.classList.add('active');
                targetSection.style.display = 'flex'; // O 'block' según tu CSS
                
                // 3. CARGAR DATOS ESPECÍFICOS SEGÚN EL MÓDULO
                console.log(`🔄 Activando módulo: ${moduleId}`);
                
                if (moduleId === 'verticales') {
                    console.log("📡 Llamando a cargarVerticales()...");
                    cargarVerticales(); // <--- ESTO ES LO QUE FALTABA DISPARAR
                }
                
                if (moduleId === 'ots') {
                    loadOTs();
                }
                
                if (moduleId === 'tracking') {
                    // Resetear tracking si es necesario
                    document.getElementById('trackingContent').style.display = 'none';
                }
            } else {
                console.error(`❌ Sección #${moduleId} no encontrada en el DOM`);
            }
        }

        // === FUNCION PARA CARGAR VERTICALES (Ya debería existir, pero asegurémonos) ===
        async function cargarVerticales() {
            const tbody = document.getElementById('tablaVerticalesBody');
            if (!tbody) {
                console.warn("⚠️ Elemento #tablaVerticalesBody no encontrado");
                return;
            }

            console.log("🔄 Iniciando carga de verticales desde API...");
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:2rem; color:#64748b;">⏳ Cargando...</td></tr>';

            try {
                // Ajusta la ruta si tu API está en otra carpeta relativa
                const response = await fetch('/api/verticales.php?action=list', {
                    credentials: 'same-origin' // Importante para enviar cookies de sesión
                });

                if (!response.ok) {
                    throw new Error(`HTTP Error: ${response.status}`);
                }

                const data = await response.json();
                console.log("✅ Datos recibidos:", data);

                if (!data.success) {
                    throw new Error(data.error || 'Error desconocido');
                }

                if (!Array.isArray(data.verticales)) {
                    throw new Error('Formato de respuesta inválido');
                }

                if (data.verticales.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:2rem; color:#94a3b8;">No hay verticales registradas.</td></tr>';
                    return;
                }

                // Determinar permisos (ajusta según cómo guardes el rol en sesión)
                const isAdmin = <?= json_encode(isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin_hospital') ?>;

                tbody.innerHTML = data.verticales.map(v => {
                    // Determinar si el usuario puede EDITAR (Solo Admins pueden crear/editar estructura base)
                    // Si quieres que los Jefes de Vertical puedan editar su propia info, ajusta esta condición.
                    // Por ahora, asumimos que solo Admin modifica la estructura de Verticales.
                    const isAdmin = <?= json_encode($esAdmin ?? false) ?>; 

                    return `
                        <tr style="border-bottom:1px solid #f1f5f9; transition:background 0.2s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='white'">
                            <td style="padding:1rem; font-weight:600; color:#1e293b;">${v.nombre_vertical}</td>
                            <td style="padding:1rem; color:#475569;">${v.nombre_responsable || '<span style="color:#94a3b8;">Sin asignar</span>'}</td>
                            <td style="padding:1rem;"><span class="badge b-pen">${v.cod_especialidad_principal || '-'}</span></td>
                            <td style="padding:1rem; color:#64748b; font-size:0.9rem;">${v.contacto_email || '-'}</td>
                            <td style="padding:1rem; text-align:center;">
                                <span style="background:${v.activo ? '#dcfce7' : '#fee2e2'}; color:${v.activo ? '#166534' : '#991b1b'}; padding:0.25rem 0.6rem; border-radius:20px; font-size:0.75rem; font-weight:600;">
                                    ${v.activo ? 'Activa' : 'Inactiva'}
                                </span>
                            </td>
                            <td style="padding:1rem; text-align:center;">
                                ${isAdmin ? `
                                    <button onclick="editarVertical(${JSON.stringify(v).replace(/"/g, '&quot;')})" 
                                            title="Editar Vertical"
                                            style="background:#eff6ff; color:#2563eb; border:none; width:32px; height:32px; border-radius:6px; cursor:pointer; font-size:1rem; display:inline-flex; align-items:center; justify-content:center; transition:background 0.2s;" 
                                            onmouseover="this.style.background='#dbeafe'" 
                                            onmouseout="this.style.background='#eff6ff'">
                                        ✏️
                                    </button>
                                ` : ''}
                            </td>
                        </tr>
                    `;
                }).join('');

            } catch (err) {
                console.error("❌ Error cargando verticales:", err);
                tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; color:#ef4444; padding:2rem;">❌ Error: ${err.message}<br><small>Revisa consola (F12)</small></td></tr>`;
            }
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
            pollingInterval = setInterval(updateProgress, 1000);
            const formData = new FormData(); formData.append('sicFile', file);
            try {
                const res = await fetch('/api/import_sic.php', { method: 'POST', body: formData, credentials: 'include' }); const rawText = await res.text();
                let data; try { data = JSON.parse(rawText); } catch { throw new Error('El servidor no devolvió JSON válido.'); }
                if(!res.ok || !data.success) throw new Error(data.error || `Error HTTP ${res.status}`);
                log.innerHTML = `<p style="color:#10b981; font-weight:600;">✅ Proceso finalizado</p><p>📥 Registros leídos: ${data.total}</p><p style="color:#10b981;">✅ ${data.inserted} OTs nuevas importadas</p>${data.skipped > 0 ? `<p style="color:#f59e0b;">⚠️ ${data.skipped} OTs duplicadas omitidas</p>` : ''}${data.errors?.length ? `<p style="color:#ef4444;">❌ ${data.errors.length} errores de validación</p>` : ''}`;
                addToHistory(data.inserted, data.skipped, data.total); Toast.success(`Carga completada: ${data.inserted} nuevos`); document.getElementById('sicFile').value = ''; progressBar.style.width = '100%'; progressText.textContent = '¡Proceso finalizado!';
            } catch (err) { log.innerHTML = `<p style="color:#ef4444;">❌ Error: ${err.message}</p>`; Toast.error(err.message, 'Carga Fallida'); progressBar.style.backgroundColor = '#ef4444'; }
            finally { clearInterval(pollingInterval); setTimeout(() => overlay.classList.remove('active'), 1500); }
        }

        function updateProgress() {
            fetch('/api/sic_progress.php')
                .then(r => r.json())
                .then(p => {
                    const progressBar = document.getElementById('progressBar');
                    const progressText = document.getElementById('progressText');
                    
                    if (!progressBar || !progressText) return;

                    // Calcular porcentaje basado en filas procesadas vs total estimado (si lo tuviéramos)
                    // Como no conocemos el total exacto hasta terminar, usamos un enfoque visual progresivo
                    // O mejor aún, mostramos el contador absoluto que es lo más honesto
                    
                    let percent = 0;
                    // Truco visual: Si ya procesó más de 100, empezamos a llenar la barra
                    // Idealmente, deberíamos hacer un conteo previo de líneas en el CSV, pero es costoso.
                    // Usaremos un crecimiento exponencial suave o simplemente mostraremos el número.
                    
                    // Para este caso, asumiremos que la barra representa el avance relativo al tiempo o filas
                    // Pero como p.total es 0 hasta el final en esta lógica simplificada, 
                    // vamos a confiar en que p.current sube.
                    
                    // Opción A: Barra infinita que llena según pasa el tiempo/filas (menos preciso)
                    // Opción B: Mostrar solo números (más preciso). 
                    // Vamos a implementar una barra que llene gradualmente mientras p.status sea 'processing'
                    
                    if (p.status === 'completed') {
                        percent = 100;
                    } else {
                        // Estimación simple basada en filas procesadas. 
                        // Nota: Sin conocer el total total de filas antes de empezar, 
                        // es difícil dar un % exacto. 
                        // Sin embargo, podemos mostrar el texto claro.
                        percent = Math.min(99, Math.floor((p.current / 5000) * 100)); // Ajusta divisor si tus archivos son muy grandes
                        // Mejor estrategia: No usar % falso, sino mostrar el texto claro y una barra animada indeterminada o basada en tiempo.
                        // Pero para cumplir tu requisito de %, haremos esto:
                        // Si p.current > 0, calculamos un % basado en un máximo teórico o simplemente llenamos poco a poco.
                        // Vamos a dejar el cálculo de % para el final o usar un valor arbitrario que crezca.
                        
                        // SOLUCIÓN MEJOR: Mostrar el texto detallado y una barra que crece linealmente con el tiempo o filas.
                        // Aquí forzamos que la barra muestre algo visual mientras procesa.
                        percent = Math.min(95, p.current > 0 ? Math.log(p.current) * 10 : 0); // Crecimiento logarítmico suave
                    }

                    // Actualizar ancho
                    progressBar.style.width = percent + '%';
                    
                    // Texto dentro de la barra
                    progressBar.textContent = p.status === 'completed' ? '100%' : (percent > 5 ? percent + '%' : '');

                    // ✅ TEXTO CLARO CON DATOS REALES
                    progressText.innerHTML = `
                        Procesando: <strong>${p.inserted.toLocaleString()}</strong> nuevas | 
                        <span style="color:#f59e0b;">${p.skipped.toLocaleString()}</span> omitidas (duplicados) | 
                        Total leído: ${p.current.toLocaleString()}
                    `;

                    // Cambiar color según avance (basado en el % visual)
                    progressBar.classList.remove('progress-low', 'progress-med', 'progress-high');
                    if (percent < 30) {
                        progressBar.classList.add('progress-low');
                    } else if (percent < 80) {
                        progressBar.classList.add('progress-med');
                    } else {
                        progressBar.classList.add('progress-high');
                    }

                    // Detener polling si terminó
                    if (p.status === 'completed' || p.status === 'error') {
                        clearInterval(window.sicPolling);
                        progressBar.style.width = '100%';
                        progressBar.textContent = '100%';
                        progressBar.classList.remove('progress-low', 'progress-med');
                        progressBar.classList.add('progress-high');
                        progressText.innerHTML = `✅ Finalizado: <strong>${p.inserted}</strong> nuevas, <span style="color:#f59e0b;">${p.skipped}</span> omitidas.`;
                    }
                })
                .catch(() => {});
        }

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
            const yearSelect = document.getElementById('filterYear');
            const monthSelect = document.getElementById('filterMonth');
            const weekSelect = document.getElementById('filterWeek');

            if (yearSelect) dashboardFilters.year = yearSelect.value;
            if (monthSelect) dashboardFilters.month = monthSelect.value;
            if (weekSelect) dashboardFilters.week = weekSelect.value;
            
            // Resetear drill-down al cambiar filtros globales
            dashboardFilters.especialidad = null;
            dashboardFilters.especialidadLabel = '';

            loadWeeks(); // Recargar semanas disponibles
            loadKpis();  // Recargar KPIs con nuevos filtros
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
            document.querySelectorAll('.ots-table tr').forEach(r => r.classList.remove('selected'));
            event.target.closest('tr').classList.add('selected');
            
            try {
                const res = await fetch(`/api/ots.php?search=${encodeURIComponent(codigoOt)}&limit=1&page=1`);
                const data = await res.json();
                
                if (!data.success || !data.data.length) return;
                selectedOTData = data.data[0];
                
                // ✅ INYECCIÓN SEGURA DE OBSERVACIONES
                const obsTexto = selectedOTData.observaciones ? selectedOTData.observaciones.replace(/</g, '&lt;').replace(/>/g, '&gt;') : '';

                document.getElementById('otDetailContent').innerHTML = `
                    <label>Código OT</label><input value="${selectedOTData.codigo_ot}" readonly>
                    <label>Fecha Programada</label><input type="date" value="${selectedOTData.fecha_programada || ''}">
                    <label>Turno</label><input value="${selectedOTData.turno || '-'}">
                    <label>Protocolo</label><input value="${selectedOTData.nombre_protocolo || '-'}">
                    <label>Equipo</label><input value="${selectedOTData.nombre_equipo || '-'}">
                    <label>Área</label><input value="${selectedOTData.nombre_area || '-'}">
                    <label>Especialidad</label><input value="${selectedOTData.nombre_especialidad || '-'}">
                    <label>HH Programadas</label><input type="number" value="${selectedOTData.hh_programadas || 0}" step="0.01">
                    <label>Estado</label>
                    <select id="otEstadoEdit">
                        <option value="pendiente" ${selectedOTData.estado==='pendiente'?'selected':''}>Pendiente</option>
                        <option value="asignada" ${selectedOTData.estado==='asignada'?'selected':''}>Asignada</option>
                        <option value="en_proceso" ${selectedOTData.estado==='en_proceso'?'selected':''}>En Proceso</option>
                        <option value="cerrada" ${selectedOTData.estado==='cerrada'?'selected':''}>Cerrada</option>
                    </select>
                    <label>Observaciones</label>
                    <textarea id="otObsEdit" rows="4" placeholder="Notas técnicas..." style="resize:vertical;">${obsTexto}</textarea>
                `;
                document.getElementById('otActions').style.display = 'flex';
            } catch (err) {
                console.error("Error al cargar detalle:", err);
            }
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


// FUNCIONES DEL MOCKUP PLANIFICACIÓN
function openPlanningModal(grupo, dia) {
    console.log(`Abriendo planificación para ${grupo} en ${dia}`);
    document.getElementById('planningModal').style.display = 'flex';
}

function savePlanning() {
    alert('✅ Planificación guardada exitosamente.\n\nEn producción, esto distribuirá las HHs automáticamente según la periodicidad de la OT.');
    document.getElementById('planningModal').style.display = 'none';
    // Aquí iría la lógica para pintar las celdas dinámicamente
}

function showEventDetails() {
    alert('📋 Detalle de OT:\n\nOT: 2026-001\nProtocolo: Mantenimiento UMA\nHHs Asignadas: 8\nEstado: Programado');
}

function changeWeek(delta) {
    alert('🔄 Cambiando semana... (Simulación)');
}

// AGREGAR NUEVAS FICHAS AL HOME (Dinámico)
//document.addEventListener('DOMContentLoaded', () => {
//    const homeGrid = document.querySelector('.home-grid');
//    if(homeGrid) {
//        // Insertar después de la primera tarjeta
//        const newCards = `
//            <div class="home-card" onclick="showModule('planificacion')">
//                <div class="icon-3d-container" style="background:transparent; box-shadow:none; border:none;">
//                    <img src="/img/icons/planificacion.png" alt="Carga SIC" style="width:50px; height:50px; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.1));">
//                </div>
//                <div class="home-card-title">Planificación</div>
//                <div class="home-card-desc">Calendario y asignación HH</div>
//            </div>
//            <div class="home-card" onclick="showModule('verticales')">
//                    <div class="icon-3d-container" style="background:transparent; box-shadow:none; border:none;">
//                        <img src="/img/icons/verticales.png" alt="Carga SIC" style="width:50px; height:50px; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.1));">
//                    </div>
//                    <div class="home-card-title">Verticales</div>
//                    <div class="home-card-desc">Mantenedor de Verticales</div>
//            </div>
//            <div class="home-card" onclick="showModule('recursos')">
//                <div class="icon-3d-container" style="background:transparent; box-shadow:none; border:none;">
//                    <img src="/img/icons/recursos.png" alt="Carga SIC" style="width:50px; height:50px; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.1));">
//                </div>
//                <div class="home-card-title">Recursos</div>
//                <div class="home-card-desc">Técnicos, Grupos y Turnos</div>
//            </div>
//      `;
//        // Insertar al final de la grilla
//        homeGrid.insertAdjacentHTML('beforeend', newCards);
//    }
//});
// === ESTADO GLOBAL TRACKING ===
let currentTrackingOT = null;

// === BUSCADOR INTELIGENTE TRACKING ===
function handleTrackingSearch() {
    const val = document.getElementById('trackingSearch').value.trim().toLowerCase();
    const dd = document.getElementById('trackingDropdown');
    
    if (!val) { 
        dd.classList.remove('show'); 
        return; 
    }

    // Reutilizamos la lógica de búsqueda de OTs
    fetch(`/api/ots.php?search=${encodeURIComponent(val)}&limit=10&page=1`)
        .then(r => r.json())
        .then(data => {
            if (!data.success || !data.data.length) { 
                dd.innerHTML = '<div class="search-item" style="color:#94a3b8;">No se encontraron OTs</div>';
                dd.classList.add('show');
                return; 
            }
            dd.innerHTML = data.data.map(o => 
                `<div class="search-item" onclick="loadTrackingDetails('${o.codigo_ot}', '${o.nombre_equipo || o.nombre_protocolo}')">
                    ${o.codigo_ot} - ${o.nombre_equipo || o.nombre_protocolo} (${o.estado})
                </div>`
            ).join('');
            dd.classList.add('show');
        })
        .catch(() => {});
}

// === CARGAR DETALLES DE LA OT SELECCIONADA ===
async function loadTrackingDetails(codigoOt, descripcion) {
    // Ocultar dropdown y mostrar contenido
    document.getElementById('trackingDropdown').classList.remove('show');
    document.getElementById('trackingContent').style.display = 'flex';
    
    // Actualizar cabecera
    document.getElementById('trackOtCode').textContent = codigoOt;
    document.getElementById('trackOtDesc').textContent = descripcion || 'Sin descripción';
    
    // Obtener datos completos de la OT
    try {
        const res = await fetch(`/api/ots.php?search=${encodeURIComponent(codigoOt)}&limit=1&page=1`);
        const data = await res.json();
        
        if (data.success && data.data.length > 0) {
            currentTrackingOT = data.data[0];
            
            // Actualizar Badge Estado
            const badgeMap = { 'pendiente': 'b-pen', 'asignada': 'b-asi', 'en_proceso': 'b-pro', 'cerrada': 'b-cer' };
            const badgeClass = badgeMap[currentTrackingOT.estado] || 'b-pen';
            const statusEl = document.getElementById('trackOtStatus');
            statusEl.className = `badge ${badgeClass}`;
            statusEl.textContent = currentTrackingOT.estado.replace('_', ' ').toUpperCase();

            // Actualizar HH
            const hhProg = parseFloat(currentTrackingOT.hh_programadas) || 0;
            const hhReal = parseFloat(currentTrackingOT.hh_reales) || 0;
            const percent = hhProg > 0 ? Math.min(100, (hhReal / hhProg) * 100) : 0;
            
            document.getElementById('trackHhRatio').textContent = `${hhReal} / ${hhProg} HH`;
            document.getElementById('trackProgressBar').style.width = `${percent}%`;
            
            // Generar Timeline Simulado (Basado en campos de BD + Logs simulados)
            generateTimeline(currentTrackingOT);
            
            // Cargar Incidencias (Simulado por ahora, luego conectaremos a DB)
            loadIncidences(codigoOt);
        }
    } catch (err) {
        console.error("Error cargando tracking:", err);
    }
}

// === GENERAR TIMELINE VISUAL ===
function generateTimeline(ot) {
    const container = document.getElementById('timelineContainer');
    let html = '';
    
    // Hito 1: Carga SIC (Fecha programada como referencia inicial si no hay log exacto)
    const fechaCarga = ot.fecha_programada ? new Date(ot.fecha_programada).toLocaleString('es-CL') : 'Fecha desconocida';
    html += createTimelineItem(fechaCarga, '📥 Carga desde SIC', 'Importada desde planilla CSV', 'completed');

    // Hito 2: Asignación (Simulado basado en estado)
    if (['asignada', 'en_proceso', 'cerrada'].includes(ot.estado)) {
        // En producción, esto vendría de una tabla 'asignaciones' o log de cambios de estado
        const fechaAsignacion = new Date(new Date().setDate(new Date().getDate() - 1)).toLocaleString('es-CL'); // Ejemplo: Ayer
        html += createTimelineItem(fechaAsignacion, '👤 Asignación', `Asignada a ${ot.nombre_especialidad || 'Grupo General'}`, 'completed');
    }

    // Hito 3: Inicio Trabajo
    if (['en_proceso', 'cerrada'].includes(ot.estado)) {
        const fechaInicio = new Date().toLocaleString('es-CL'); // Hoy
        html += createTimelineItem(fechaInicio, '🛠️ Inicio Trabajo', 'Técnico inició ejecución en terreno', 'warning');
    }

    // Hito 4: Cierre
    if (ot.estado === 'cerrada') {
        const fechaCierre = new Date().toLocaleString('es-CL');
        html += createTimelineItem(fechaCierre, '✅ OT Cerrada', 'Trabajo finalizado y validado', 'completed');
    }

    // Si no hay hitos avanzados, mostrar mensaje
    if (html === '') {
        html = createTimelineItem(fechaCarga, '📥 Carga desde SIC', 'Esperando asignación', 'completed');
    }

    container.innerHTML = html;
}

function createTimelineItem(date, title, desc, statusClass) {
    return `
        <div class="timeline-item ${statusClass}">
            <div class="timeline-date">${date}</div>
            <div class="timeline-title">${title}</div>
            <div class="timeline-desc">${desc}</div>
        </div>
    `;
}

// === GESTIÓN DE INCIDENCIAS ===
function getIncTypeName(type) {
    const types = {
        'acceso': '🚫 ACCESO DENEGADO',
        'material': '📦 FALTA MATERIAL',
        'seguridad': '⚠️ SEGURIDAD',
        'otro': '📝 OTRO'
    };
    return types[type] || 'INCIDENCIA';
}

// Variable temporal para manejar el archivo seleccionado antes de enviar
let currentEvidenceFile = null;

// 1. Cuando el usuario selecciona un archivo en el input <input type="file">
function uploadEvidence(input) {
    if (!currentTrackingOT) return alert('Selecciona una OT primero');
    
    if (input.files && input.files[0]) {
        const file = input.files[0];
        
        // Validar tamaño máximo (ej. 5MB)
        if (file.size > 5 * 1024 * 1024) {
            Toast.error('El archivo es muy grande. Máximo 5MB.', 'Error');
            input.value = ''; 
            currentEvidenceFile = null;
            return;
        }

        currentEvidenceFile = file; // Guardamos referencia al objeto File
        
        // Feedback visual
        Toast.success(`Archivo "${file.name}" listo para adjuntar`, 'Archivo cargado');
        console.log("📎 Evidencia cargada en memoria:", file.name);
    }
}
// 2. Función principal para GUARDAR la incidencia con persistencia
async function addIncidence() {
    if (!currentTrackingOT) return alert('Selecciona una OT primero');
    
    const type = document.getElementById('incType').value;
    const desc = document.getElementById('incDesc').value.trim();
    
    if (!desc) return Toast.error('Escribe un detalle para la incidencia', 'Campo requerido');

    // Preparamos FormData para enviar texto + archivo binario
    const formData = new FormData();
    formData.append('ot_code', currentTrackingOT.codigo_ot);
    formData.append('type', type);
    formData.append('description', desc);
    
    // Si hay archivo seleccionado, lo agregamos al FormData
    if (currentEvidenceFile) {
        formData.append('evidence', currentEvidenceFile);
    }

    try {
        const response = await fetch('/api/save_incidence.php', {
            method: 'POST',
            body: formData,
            credentials: 'include'
        });

        const text = await response.text();

        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error("Respuesta inválida:", text);
            throw new Error("No es JSON");
        }

        if (data.success) {
            Toast.success('Incidencia registrada correctamente', 'Éxito');
            
            // Limpiar formulario
            document.getElementById('incDesc').value = '';
            document.getElementById('fileInput').value = ''; // Resetear input file
            currentEvidenceFile = null; // Limpiar variable temporal
            
            // Recargar la lista de incidencias para mostrar la nueva
            loadIncidences(currentTrackingOT.codigo_ot);
        } else {
            Toast.error(data.error || 'Error al guardar', 'Error');
        }

    } catch (err) {
        console.error(err);
        Toast.error('Error de conexión al guardar', 'Error');
    }
}

// 3. Cargar incidencias desde la BASE DE DATOS (Persistencia Real)
async function loadIncidences(otCode) {
    const list = document.getElementById('incidencesList');
    list.innerHTML = '<div style="text-align:center; padding:1rem;">⏳ Cargando historial...</div>';

    try {
        // Llamamos a un endpoint que devuelve las incidencias de esta OT
        // Puedes reutilizar api/ots.php si agregas una acción, o crear uno específico.
        // Aquí asumimos que creamos una pequeña función en api/ots.php o usamos un fetch directo.
        // Por simplicidad, crearemos un fetch a un endpoint hipotético /api/get_incidences.php
        // SI NO TIENES ESTE ENDPOINT, usa el siguiente código temporal que simula o lee de localStorage como fallback:
        
        /* 
           OPCIÓN A (Recomendada): Crear api/get_incidences.php
           OPCIÓN B (Rápida): Si aún no quieres crear más archivos PHP, mantén el localStorage 
           pero ahora que ya tenemos save_incidence.php, lo ideal es leerlo de la BD.
        */

        // Vamos a asumir que agregamos esto a api/ots.php o creamos get_incidences.php
        // Por ahora, te dejo el código para que lo pegues en un nuevo archivo 
        // public/api/get_incidences.php (ver Paso Extra abajo)
        
        const res = await fetch(`/api/get_incidences.php?ot=${encodeURIComponent(otCode)}`);
        const data = await res.json();

        if (!data.success || !data.incidencias.length) {
            list.innerHTML = '<div style="text-align:center; color:#94a3b8; padding:1rem; font-size:0.85rem;">Sin incidencias registradas</div>';
            return;
        }

        list.innerHTML = data.incidencias.map(inc => {
            // Formatear fecha
            const dateObj = new Date(inc.fecha_registro);
            const dateStr = dateObj.toLocaleString('es-CL');
            
            // Icono según tipo
            let icon = '📝';
            if(inc.tipo === 'acceso') icon = '🚫';
            if(inc.tipo === 'material') icon = '📦';
            if(inc.tipo === 'seguridad') icon = '⚠️';

                        // Link a evidencia si existe
            let evidenceHtml = '';
            if (inc.evidencia_path) {
                // Limpiar la ruta por si tiene barras duplicadas o espacios
                let cleanPath = inc.evidencia_path.trim();
                
                // Asegurar que la ruta empiece con / pero no tenga doble //
                if (!cleanPath.startsWith('/')) {
                    cleanPath = '/' + cleanPath;
                }
                
                // Construir URL absoluta segura
                const url = `${window.location.origin}${cleanPath}`;
                
                console.log("📎 Intentando cargar evidencia:", url); // Log para debuggear en F12
                
                const isPdf = cleanPath.toLowerCase().endsWith('.pdf');
                
                if (isPdf) {
                    // Para PDFs, abrir en nueva pestaña
                    evidenceHtml = `<a href="${url}" target="_blank" class="inc-evidence">📄 Ver PDF</a>`;
                } else {
                    // Para imágenes, abrir en nueva pestaña
                    evidenceHtml = `<a href="${url}" target="_blank" class="inc-evidence">🖼️ Ver Foto</a>`;
                }
            }

            return `
                <div class="inc-card">
                    <div class="inc-header">
                        <span class="inc-type">${icon} ${getIncTypeName(inc.tipo)}</span>
                        <span class="inc-date">${dateStr}</span>
                    </div>
                    <div class="inc-body">${inc.descripcion}</div>
                    ${evidenceHtml}
                </div>
            `;
        }).join('');

    } catch (err) {
        console.error(err);
        list.innerHTML = '<div style="text-align:center; color:red; padding:1rem;">Error al cargar historial</div>';
    }
}

function getIncTypeName(type) {
    const types = {
        'acceso': 'ACCESO DENEGADO',
        'material': 'FALTA MATERIAL',
        'seguridad': 'SEGURIDAD',
        'otro': 'OTRO'
    };
    return types[type] || 'INCIDENCIA';
}
// === GESTIÓN DE VERTICALES ===
// Abrir Modal para Crear
function abrirModalVerticales() {
    document.getElementById('formVerticales').reset();
    document.getElementById('v_id').value = '';
    document.getElementById('tituloModalVerticales').textContent = 'Nueva Vertical';
    document.getElementById('modalVerticales').style.display = 'flex';
}

// Cerrar Modal
function cerrarModalVerticales() {
    document.getElementById('modalVerticales').style.display = 'none';
}

// Preparar Modal para Editar
function editarVertical(v) {
    document.getElementById('v_id').value = v.id_vertical;
    document.getElementById('v_nombre').value = v.nombre_vertical;
    document.getElementById('v_responsable').value = v.nombre_responsable || '';
    document.getElementById('v_especialidad').value = v.cod_especialidad_principal || '';
    document.getElementById('v_contacto').value = v.contacto_email || '';
    document.getElementById('tituloModalVerticales').textContent = 'Editar Vertical';
    document.getElementById('modalVerticales').style.display = 'flex';
}

// Guardar (Crear o Actualizar)
async function guardarVertical(e) {
    e.preventDefault();
    const formData = new FormData(document.getElementById('formVerticales'));
    
    const action = document.getElementById('v_id').value ? 'update' : 'create';
    formData.append('action', action);

    try {
        const res = await fetch('/api/verticales.php', { method: 'POST', body: formData, credentials: 'include' });
        const data = await res.json();
        
        if (data.success) {
            Toast.success(data.message || 'Operación exitosa');
            cerrarModalVerticales();
            cargarVerticales(); // Recargar tabla
        } else {
            Toast.error(data.error || 'Error al guardar');
        }
    } catch (err) {
        Toast.error('Error de conexión');
    }
}

// Eliminar Vertical
async function eliminarVertical(id, nombre) {
    if (!confirm(`¿Estás seguro de eliminar la vertical "${nombre}"?\n\nNota: Si tiene usuarios asignados, no podrá ser eliminada.`)) return;
    
    try {
        const res = await fetch(`/api/verticales.php?action=delete&id=${id}`, { method: 'DELETE' });
        const data = await res.json();
        
        if (data.success) {
            Toast.success('Vertical eliminada correctamente');
            cargarVerticales();
        } else {
            Toast.error(data.error || 'Error al eliminar');
        }
    } catch (err) {
        Toast.error('Error de conexión');
    }
}

// Inicializar cuando se muestra el módulo
document.addEventListener('DOMContentLoaded', () => {
    // Si ya estamos en la sección verticales, cargar datos
    if (document.getElementById('verticales').classList.contains('active')) {
        cargarVerticales();
    }
});

// === GESTIÓN DE RECURSOS (TÉCNICOS Y GRUPOS) ===
// === GESTIÓN DE RECURSOS (TÉCNICOS Y GRUPOS) ===

function showResourceTab(tab) {
    document.getElementById('view-tecnicos').style.display = tab === 'tecnicos' ? 'block' : 'none';
    document.getElementById('view-grupos').style.display = tab === 'grupos' ? 'block' : 'none';
    document.getElementById('view-turnos').style.display = tab === 'turnos' ? 'block' : 'none';
    
    document.getElementById('tab-tecnicos').classList.toggle('active', tab === 'tecnicos');
    document.getElementById('tab-grupos').classList.toggle('active', tab === 'grupos');
    document.getElementById('tab-turnos').classList.toggle('active', tab === 'turnos');

    // Limpiar buscador al cambiar de pestaña
    const searchInput = document.getElementById('searchRecursos');
    if(searchInput) searchInput.value = '';

    if (tab === 'tecnicos') cargarTecnicos();
    if (tab === 'grupos') cargarGrupos();
    if (tab === 'turnos') cargarTurnosActivos();
}

async function cargarGrupos() {
    const tbody = document.getElementById('tablaGruposBody');
    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:2rem; color:#94a3b8;">⏳ Cargando grupos...</td></tr>';
    
    try {
        const res = await fetch('/api/recursos.php?action=list_grupos');
        const data = await res.json();
        if (!data.success) throw new Error(data.error);

        if (data.data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:2rem; color:#94a3b8;">No hay grupos registrados.</td></tr>';
            return;
        }

        tbody.innerHTML = data.data.map(g => `
            <tr style="background:#fff; transition:background 0.2s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='#fff'">
                <td style="padding:1rem; border-bottom:1px solid #f1f5f9; font-weight:600; color:#1e293b;">${g.nombre_grupo}</td>
                <td style="padding:1rem; border-bottom:1px solid #f1f5f9; color:#475569;">${g.nombre_vertical || '-'}</td>
                <td style="padding:1rem; border-bottom:1px solid #f1f5f9;">${g.turno_actual || '<span style="color:#94a3b8; font-style:italic;">Sin turno</span>'}</td>
                <td style="padding:1rem; border-bottom:1px solid #f1f5f9; color:#64748b; max-width:250px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="${g.descripcion || ''}">${g.descripcion || '-'}</td>
                <td style="padding:1rem; border-bottom:1px solid #f1f5f9; text-align:center;">
                    <button onclick="editResource('grupo', ${JSON.stringify(g).replace(/"/g, '&quot;')})" title="Editar" style="cursor:pointer; margin-right:5px;">✏️</button>
                    <button onclick="deleteResource('grupo', ${g.id}, '${g.nombre_grupo}')" title="Eliminar" style="cursor:pointer; color:#ef4444;">🗑️</button>
                </td>
            </tr>
        `).join('');
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="5" style="text-align:center; color:#ef4444; padding:2rem;">❌ Error: ${err.message}</td></tr>`;
    }
}

async function cargarTurnosActivos() {
    const tbody = document.getElementById('tablaTurnosBody');
    tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:2rem; color:#94a3b8;">⏳ Cargando turnos...</td></tr>';
    
    try {
        const res = await fetch('/api/recursos.php?action=list_con_turno');
        const data = await res.json();
        if (!data.success) throw new Error(data.error);

        if (data.data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:2rem; color:#94a3b8;">No hay recursos con turno activo.</td></tr>';
            return;
        }

        tbody.innerHTML = data.data.map(r => `
            <tr style="background:#fff; transition:background 0.2s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='#fff'">
                <td style="padding:1rem; border-bottom:1px solid #f1f5f9;">
                    <span style="background:${r.tipo_recurso === 'tecnico' ? '#dbeafe' : '#fce7f3'}; color:${r.tipo_recurso === 'tecnico' ? '#1e40af' : '#9d174d'}; padding:0.25rem 0.5rem; border-radius:4px; font-size:0.75rem; font-weight:600;">
                        ${r.tipo_recurso === 'tecnico' ? '👨‍🔧 Técnico' : '👥 Grupo'}
                    </span>
                </td>
                <td style="padding:1rem; border-bottom:1px solid #f1f5f9; font-weight:600; color:#1e293b;">${r.nombre_display}</td>
                
                <!-- CORREGIR MOSTRAJE DE TURNO -->
                <td style="padding:1rem; border-bottom:1px solid #f1f5f9;">
                    ${r.turno_nombre ? `<span class="badge b-blue">${r.turno_nombre}</span>` : '<span style="color:#cbd5e1;">-</span>'}
                </td>
                
                <td style="padding:1rem; border-bottom:1px solid #f1f5f9; color:#64748b;">
                    ${r.vertical_nombre ? `<div>🏢 ${r.vertical_nombre}</div>` : ''}
                    ${r.especialidad_nombre ? `<div>🛠️ ${r.especialidad_nombre}</div>` : ''}
                    ${(!r.vertical_nombre && !r.especialidad_nombre) ? '-' : ''}
                </td>
            </tr>
        `).join('');
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="4" style="text-align:center; color:#ef4444; padding:2rem;">❌ Error: ${err.message}</td></tr>`;
    }
}

// === BUSCADOR INTELIGENTE CORREGIDO ===
function filterResources() {
    const query = document.getElementById('searchRecursos').value.toLowerCase().trim();
    
    // Determinar qué tbody está visible actualmente
    let tbodyId = '';
    if (document.getElementById('view-tecnicos').style.display !== 'none') tbodyId = 'tablaTecnicosBody';
    else if (document.getElementById('view-grupos').style.display !== 'none') tbodyId = 'tablaGruposBody';
    else if (document.getElementById('view-turnos').style.display !== 'none') tbodyId = 'tablaTurnosBody';
    
    if (!tbodyId) return; // Si ninguna vista está activa, salir

    const rows = document.querySelectorAll(`#${tbodyId} tr`);
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        if (text.includes(query)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// === MODALES Y FORMULARIOS ===

async function loadSelects() {
    try {
        // 1. Cargar Especialidades
        let espData = [];
        try {
            const res = await fetch('/api/especialidades.php?action=list');
            if (res.ok) {
                const data = await res.json();
                if (data.success) espData = data.data || [];
            }
        } catch (e) { console.warn("Error especialidades", e); }

        const espSelect = document.getElementById('res_especialidad');
        if (espSelect) {
            // Inyectar estilo inline en cada option para forzar visibilidad
            espSelect.innerHTML = '<option value="" style="background:#fff; color:#000;">Seleccionar...</option>' + 
                espData.map(e => `<option value="${e.id}" style="background:#ffffff; color:#000000; padding:8px;">${e.nombre}</option>`).join('');
        }

        // 2. Cargar Verticales
        let vertData = [];
        try {
            const res = await fetch('/api/verticales.php?action=list');
            if (res.ok) {
                const data = await res.json();
                if (data.success) vertData = data.verticales || [];
            }
        } catch (e) { console.warn("Error verticales", e); }

        const vertTecSelect = document.getElementById('res_vertical_tecnico');
        if (vertTecSelect) {
            vertTecSelect.innerHTML = '<option value="" style="background:#fff; color:#000;">Seleccionar Vertical...</option>' + 
                vertData.map(v => `<option value="${v.id_vertical}" style="background:#ffffff; color:#000000; padding:8px;">${v.nombre_vertical}</option>`).join('');
        }

        const vertGrpSelect = document.getElementById('res_vertical_grupo');
        if (vertGrpSelect) {
            vertGrpSelect.innerHTML = '<option value="" style="background:#fff; color:#000;">Ninguna</option>' + 
                vertData.map(v => `<option value="${v.id_vertical}" style="background:#ffffff; color:#000000; padding:8px;">${v.nombre_vertical}</option>`).join('');
        }

        // 3. Cargar Tipos de Turno
        let turnoData = [];
        try {
            const res = await fetch('/api/recursos.php?action=list_tipos_turno');
            if (res.ok) {
                const data = await res.json();
                if (data.success) turnoData = data.data || [];
            }
        } catch (e) { console.warn("Error turnos", e); }

        const turnoSelect = document.getElementById('res_turno');
        if (turnoSelect) {
            turnoSelect.innerHTML = '<option value="" style="background:#fff; color:#000;">Sin Turno Asignado</option>' + 
                turnoData.map(t => `<option value="${t.id}" style="background:#ffffff; color:#000000; padding:8px;">${t.codigo} - ${t.nombre}</option>`).join('');
        }

    } catch (err) {
        console.error("Error general en loadSelects:", err);
    }
}

async function saveResource(e) {
    e.preventDefault();
    
    const type = document.getElementById('res_type').value;
    const id = document.getElementById('res_id').value;
    
    // Validación Manual
    if (type === 'tecnico') {
        const rut = document.getElementById('res_rut').value.trim();
        const nombre = document.getElementById('res_nombre').value.trim();
        if (!rut || !nombre) {
            Toast.error('RUT y Nombre son obligatorios para Técnicos');
            return;
        }
    } else {
        const nombreGrp = document.getElementById('res_nombre_grupo').value.trim();
        if (!nombreGrp) {
            Toast.error('El nombre del grupo es obligatorio');
            return;
        }
    }

    const formData = new FormData();
    formData.append('action', id ? `update_${type}` : `create_${type}`);
    if (id) formData.append('id', id);
    
    if (type === 'tecnico') {
        formData.append('rut', document.getElementById('res_rut').value);
        formData.append('nombre', document.getElementById('res_nombre').value);
        formData.append('correo', document.getElementById('res_correo').value);
        formData.append('telefono', document.getElementById('res_telefono').value);
        formData.append('id_especialidad', document.getElementById('res_especialidad').value);
        formData.append('id_vertical', document.getElementById('res_vertical_tecnico').value);
    } else {
        formData.append('nombre_grupo', document.getElementById('res_nombre_grupo').value);
        formData.append('id_vertical', document.getElementById('res_vertical_grupo').value);
        formData.append('descripcion', document.getElementById('res_desc').value);
    }
    
    formData.append('id_tipo_turno', document.getElementById('res_turno').value);

    try {
        const res = await fetch('/api/recursos.php', { method: 'POST', body: formData });
        const data = await res.json();
        
        if (data.success) {
            Toast.success(data.message);
            closeModal();
            if (type === 'tecnico') cargarTecnicos();
            else cargarGrupos();
        } else {
            Toast.error(data.error || 'Error al guardar');
        }
    } catch (err) {
        console.error(err);
        Toast.error('Error de conexión');
    }
}



document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('recursos').classList.contains('active')) {
        cargarTecnicos();
    }
});
// === GESTIÓN DE CATÁLOGOS (ESPECIALIDADES Y TURNOS) ===

let catalogData = {
    especialidad: [],
    turno: []
};

// Toggle Menú Dropdown
function toggleCatalogMenu() {
    const menu = document.getElementById('catalogDropdown');
    menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
}

// Cerrar menú al hacer clic fuera
document.addEventListener('click', function(event) {
    const dropdown = document.getElementById('catalogDropdown');
    const button = event.target.closest('.dropdown button[onclick*="toggleCatalogMenu"]');
    if (dropdown && !dropdown.contains(event.target) && !button) {
        dropdown.style.display = 'none';
    }
});

async function openCatalogModal(type) {
    document.getElementById('catalogDropdown').style.display = 'none';
    const modalId = type === 'especialidad' ? 'modalEspecialidades' : 'modalTurnos';
    document.getElementById(modalId).style.display = 'flex';
    
    // Resetear formulario
    resetForm(type);

    // Cargar datos
    try {
        const res = await fetch(`/api/catalogos.php?action=list&type=${type}`);
        const data = await res.json();
        if (data.success) {
            catalogData[type] = data.data;
            renderCatalogList(type);
        }
    } catch (err) {
        console.error(err);
        Toast.error('Error al cargar catálogos');
    }
}

function closeCatalogModal(type) {
    const modalId = type === 'especialidad' ? 'modalEspecialidades' : 'modalTurnos';
    document.getElementById(modalId).style.display = 'none';
}

function renderCatalogList(type) {
    const tbodyId = type === 'especialidad' ? 'listaEspecialidadesBody' : 'listaTurnosBody';
    const tbody = document.getElementById(tbodyId);
    
    // Usar el array específico del tipo
    const items = catalogData[type];

    if (!items || items.length === 0) {
        tbody.innerHTML = `<tr><td colspan="${type === 'especialidad' ? 3 : 4}" style="text-align:center; padding:1rem; color:#94a3b8;">No hay registros.</td></tr>`;
        return;
    }

    tbody.innerHTML = items.map(item => {
        if (type === 'especialidad') {
            return `
                <tr style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:0.75rem; font-family:monospace; color:#64748b;">${item.codigo}</td>
                    <td style="padding:0.75rem; font-weight:500; color:#1e293b;">${item.nombre}</td>
                    <td style="padding:0.75rem; text-align:center;">
                        <button onclick="editCatalogItem(${JSON.stringify(item).replace(/"/g, '&quot;')}, 'especialidad')" style="cursor:pointer; margin-right:5px; color:#3b82f6;">✏️</button>
                        <button onclick="deleteCatalogItem(${item.id}, '${item.nombre}', 'especialidad')" style="cursor:pointer; color:#ef4444;">🗑️</button>
                    </td>
                </tr>
            `;
        } else {
            return `
                <tr style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:0.75rem; font-family:monospace; color:#64748b;">${item.codigo}</td>
                    <td style="padding:0.75rem; font-weight:500; color:#1e293b;">${item.nombre}</td>
                    <td style="padding:0.75rem; text-align:center; color:#64748b;">${item.hh_diarias}</td>
                    <td style="padding:0.75rem; text-align:center;">
                        <button onclick="editCatalogItem(${JSON.stringify(item).replace(/"/g, '&quot;')}, 'turno')" style="cursor:pointer; margin-right:5px; color:#3b82f6;">✏️</button>
                        <button onclick="deleteCatalogItem(${item.id}, '${item.nombre}', 'turno')" style="cursor:pointer; color:#ef4444;">🗑️</button>
                    </td>
                </tr>
            `;
        }
    }).join('');
}

function filterCatalog(type) {
    const query = document.getElementById(type === 'especialidad' ? 'searchEsp' : 'searchTurno').value.toLowerCase();
    const tbodyId = type === 'especialidad' ? 'listaEspecialidadesBody' : 'listaTurnosBody';
    const rows = document.querySelectorAll(`#${tbodyId} tr`);
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(query) ? '' : 'none';
    });
}

function editCatalogItem(item, type) {
    if (type === 'especialidad') {
        document.getElementById('esp_id').value = item.id;
        document.getElementById('esp_codigo').value = item.codigo;
        document.getElementById('esp_nombre').value = item.nombre;
    } else {
        document.getElementById('turno_id').value = item.id;
        document.getElementById('turno_codigo').value = item.codigo;
        document.getElementById('turno_nombre').value = item.nombre;
        document.getElementById('turno_hh').value = item.hh_diarias;
    }
}

function resetForm(type) {
    if (type === 'especialidad') {
        document.getElementById('formEspecialidad').reset();
        document.getElementById('esp_id').value = '';
    } else {
        document.getElementById('formTurno').reset();
        document.getElementById('turno_id').value = '';
        document.getElementById('turno_hh').value = '8'; // Default
    }
}

async function saveCatalogItem(e, type) {
    e.preventDefault();
    
    // Validar campos básicos antes de enviar
    if (type === 'especialidad') {
        const codigo = document.getElementById('esp_codigo').value.trim();
        const nombre = document.getElementById('esp_nombre').value.trim();
        if (!codigo || !nombre) {
            Toast.error('Código y Nombre son obligatorios');
            return;
        }
    } else {
        const codigo = document.getElementById('turno_codigo').value.trim();
        const nombre = document.getElementById('turno_nombre').value.trim();
        if (!codigo || !nombre) {
            Toast.error('Código y Nombre son obligatorios');
            return;
        }
    }

    const formData = new FormData();
    const idField = type === 'especialidad' ? 'esp_id' : 'turno_id';
    const id = document.getElementById(idField).value;
    
    formData.append('action', id ? 'update' : 'create');
    formData.append('type', type); // CRÍTICO: Enviar el tipo correcto al backend
    if (id) formData.append('id', id);

    if (type === 'especialidad') {
        formData.append('codigo', document.getElementById('esp_codigo').value);
        formData.append('nombre', document.getElementById('esp_nombre').value);
    } else {
        formData.append('codigo', document.getElementById('turno_codigo').value);
        formData.append('nombre', document.getElementById('turno_nombre').value);
        formData.append('hh_diarias', document.getElementById('turno_hh').value);
    }

    try {
        console.log(`📡 Enviando acción ${id ? 'update' : 'create'} para tipo: ${type}`);
        
        const res = await fetch('/api/catalogos.php', { method: 'POST', body: formData });
        const data = await res.json();
        
        if (data.success) {
            Toast.success(data.message);
            
            // Resetear formulario
            resetForm(type);
            
            // RECARGAR LA LISTA CORRECTA SEGÚN EL TIPO
            console.log(`🔄 Recargando lista para tipo: ${type}`);
            
            // Hacemos fetch específico para ese tipo para evitar mezclar datos
            const listRes = await fetch(`/api/catalogos.php?action=list&type=${type}`);
            const listData = await listRes.json();
            
            if (listData.success) {
                // Actualizar solo el array correspondiente en memoria
                catalogData[type] = listData.data;
                
                // Renderizar la lista específica
                renderCatalogList(type);
            } else {
                console.error("Error al recargar lista:", listData.error);
                Toast.error('Error al refrescar la lista');
            }
        } else {
            Toast.error(data.error || 'Error desconocido');
        }
    } catch (err) {
        console.error(err);
        Toast.error('Error de conexión al guardar');
    }
}

async function deleteCatalogItem(id, name, type) {
    if (!confirm(`¿Eliminar "${name}"?`)) return;
    
    try {
        const res = await fetch(`/api/catalogos.php?action=delete&id=${id}&type=${type}`, { method: 'DELETE' });
        const data = await res.json();
        
        if (data.success) {
            Toast.success('Eliminado correctamente');
            const listRes = await fetch(`/api/catalogos.php?action=list&type=${type}`);
            const listData = await listRes.json();
            if (listData.success) {
                catalogData[type] = listData.data;
                renderCatalogList(type);
            }
        } else {
            Toast.error(data.error);
        }
    } catch (err) {
        Toast.error('Error al eliminar');
    }
}
// === MÓDULO 9: PLANIFICACIÓN ===

let currentWeekStart = new Date();
let calendarData = [];
let resourcesList = [];

function initPlanificacion() {
    // Ajustar al lunes actual
    const day = currentWeekStart.getDay(); 
    const diff = currentWeekStart.getDate() - day + (day == 0 ? -6 : 1); 
    currentWeekStart.setDate(diff);
    
    loadWeekData();
}

async function loadWeekData() {
    const startDate = currentWeekStart.toISOString().split('T')[0];
    
    try {
        const res = await fetch(`/api/planificacion.php?action=get_week&start_date=${startDate}`);
        const data = await res.json();
        
        if (!data.success) throw new Error(data.error);

        calendarData = data.data;
        resourcesList = data.resources;
        
        renderCalendarHeader(data.week_start, data.week_end);
        renderCalendarGrid();
        renderSidebarResources();
        
    } catch (err) {
        console.error(err);
        Toast.error('Error al cargar planificación');
    }
}

function renderCalendarHeader(startStr, endStr) {
    const header = document.getElementById('calendarHeader');
    const label = document.getElementById('currentWeekLabel');
    
    const start = new Date(startStr);
    const end = new Date(endStr);
    
    label.textContent = `${start.toLocaleDateString('es-CL', {day:'numeric'})} - ${end.toLocaleDateString('es-CL', {month:'long', year:'numeric'})}`;
    
    let html = '';
    for (let i = 0; i < 7; i++) {
        const d = new Date(start);
        d.setDate(d.getDate() + i);
        const isToday = d.toDateString() === new Date().toDateString();
        const bg = isToday ? '#eff6ff' : '#fff';
        const color = isToday ? '#2563eb' : '#64748b';
        
        html += `
            <div style="padding:0.75rem; text-align:center; background:${bg}; border-right:1px solid #e2e8f0;">
                <div style="font-size:0.75rem; text-transform:uppercase; color:${color};">${d.toLocaleDateString('es-CL', {weekday:'short'})}</div>
                <div style="font-size:1.2rem; font-weight:700; color:${color};">${d.getDate()}</div>
            </div>
        `;
    }
    header.innerHTML = html;
}

function renderCalendarGrid() {
    const grid = document.getElementById('calendarGrid');
    grid.innerHTML = '';
    
    const start = new Date(currentWeekStart);
    
    for (let i = 0; i < 7; i++) {
        const d = new Date(start);
        d.setDate(d.getDate() + i);
        const dateStr = d.toISOString().split('T')[0];
        
        // Filtrar items de este día
        const dayItems = calendarData.filter(item => item.fecha_programada === dateStr);
        
        const cell = document.createElement('div');
        cell.style.cssText = `
            border-right:1px solid #e2e8f0; 
            border-bottom:1px solid #e2e8f0; 
            padding:0.5rem; 
            min-height:150px; 
            background:#fff;
            transition:background 0.2s;
        `;
        cell.dataset.date = dateStr;
        
        // Zona de Drop
        cell.addEventListener('dragover', handleDragOver);
        cell.addEventListener('drop', handleDropOnDay);

        // Renderizar Items
        let itemsHtml = '';
        dayItems.forEach(item => {
            const statusColor = getStatusColor(item.estado);
            const techs = item.tecnicos_asignados_str ? item.tecnicos_asignados_str.split(',').map(t => `<span class="badge b-blue" style="font-size:0.7rem; margin-right:2px;">${t}</span>`).join('') : '<span style="color:#94a3b8; font-style:italic;">Sin asignar</span>';
            
            itemsHtml += `
                <div draggable="true" 
                     ondragstart="handleDragStart(event, '${item.id}')"
                     onclick="openPlanificacionModal(${JSON.stringify(item).replace(/"/g, '&quot;')})"
                     style="background:${statusColor}; color:#fff; padding:0.5rem; border-radius:0.375rem; margin-bottom:0.5rem; cursor:move; font-size:0.85rem; box-shadow:0 1px 2px rgba(0,0,0,0.1);">
                    <div style="font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${item.codigo_ot}</div>
                    <div style="font-size:0.75rem; opacity:0.9;">${item.nombre_equipo || ''}</div>
                    <div style="margin-top:0.25rem; display:flex; flex-wrap:wrap;">${techs}</div>
                </div>
            `;
        });
        
        cell.innerHTML = itemsHtml || '<div style="text-align:center; color:#cbd5e1; font-size:0.8rem; margin-top:2rem;">Vacío</div>';
        grid.appendChild(cell);
    }
}

function renderSidebarResources() {
    const list = document.getElementById('listaTecnicosDraggable');
    list.innerHTML = resourcesList.map(r => `
        <div draggable="true" 
             ondragstart="handleResourceDragStart(event, '${r.id}', '${r.nombre}')"
             style="background:#f8fafc; border:1px solid #e2e8f0; padding:0.5rem; border-radius:0.5rem; cursor:grab; display:flex; align-items:center; gap:0.5rem;">
            <span style="font-size:1.2rem;">👨‍🔧</span>
            <div>
                <div style="font-weight:600; font-size:0.85rem; color:#1e293b;">${r.nombre}</div>
                <div style="font-size:0.75rem; color:#64748b;">${r.esp || 'General'}</div>
            </div>
        </div>
    `).join('');
}

// === DRAG & DROP LOGIC ===

let draggedItemId = null;
let draggedResourceId = null;
let draggedResourceName = null;

function handleDragStart(e, itemId) {
    draggedItemId = itemId;
    e.dataTransfer.effectAllowed = 'move';
    e.target.style.opacity = '0.5';
}

function handleResourceDragStart(e, resourceId, resourceName) {
    draggedResourceId = resourceId;
    draggedResourceName = resourceName;
    e.dataTransfer.effectAllowed = 'copy';
}

function handleDragOver(e) {
    e.preventDefault(); // Necesario para permitir drop
    e.currentTarget.style.background = '#eff6ff';
}

function handleDropOnDay(e) {
    e.preventDefault();
    const targetCell = e.currentTarget;
    targetCell.style.background = '#fff';
    const newDate = targetCell.dataset.date;

    if (draggedItemId) {
        // Replanificar: Mover OT a otro día
        changePlanificacionDate(draggedItemId, newDate);
        draggedItemId = null;
    } else if (draggedResourceId) {
        // Asignar: Aquí necesitamos saber a qué OT se soltó.
        // En este diseño simple, asumimos que se suelta sobre una OT existente para reasignar,
        // o podríamos crear una lógica más compleja de "Crear Nueva Asignación".
        // Para simplificar: Si se suelta sobre una celda vacía, no pasa nada.
        // Si se suelta sobre una OT, abrimos el modal de esa OT pre-seleccionando el técnico.
        
        // Detectar si cayó sobre una OT
        const targetOtElement = e.target.closest('[draggable="true"]');
        if (targetOtElement) {
            // Extraer ID del elemento DOM (hack rápido)
            const onClickAttr = targetOtElement.getAttribute('onclick');
            const match = onClickAttr.match(/(\d+)/);
            if (match) {
                const otId = match[1];
                openPlanificacionModalWithPreselectedTech(otId, draggedResourceId);
            }
        }
        draggedResourceId = null;
    }
}

// Restaurar opacidad al terminar drag
document.addEventListener('dragend', (e) => {
    if (e.target.style.opacity) e.target.style.opacity = '1';
});

async function changePlanificacionDate(id, newDate) {
    try {
        const res = await fetch('/api/planificacion.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'change_date', id_planificacion: id, new_date: newDate })
        });
        const data = await res.json();
        if (data.success) {
            Toast.success('Fecha actualizada');
            loadWeekData(); // Recargar vista
        } else {
            Toast.error(data.error);
        }
    } catch (err) {
        Toast.error('Error al actualizar fecha');
    }
}

// === MODAL LOGIC ===

function openPlanificacionModal(item) {
    document.getElementById('pf_id').value = item.id;
    document.getElementById('pf_ot').value = `${item.codigo_ot} - ${item.nombre_equipo || ''}`;
    document.getElementById('pf_fecha').value = item.fecha_programada;
    document.getElementById('pf_hh').value = item.hh_requeridas;
    document.getElementById('pf_estado').value = item.estado;
    
    // Mostrar asignaciones
    const listDiv = document.getElementById('pf_asignaciones_list');
    if (item.tecnicos_asignados_str) {
        listDiv.innerHTML = item.tecnicos_asignados_str.split(',').map(t => `<div style="padding:0.25rem 0;">✅ ${t}</div>`).join('');
    } else {
        listDiv.innerHTML = '<div style="color:#94a3b8;">Sin recursos asignados</div>';
    }

    document.getElementById('modalPlanificacion').style.display = 'flex';
}

function openPlanificacionModalWithPreselectedTech(otId, techId) {
    // Buscar el item en calendarData
    const item = calendarData.find(i => i.id == otId);
    if (item) {
        openPlanificacionModal(item);
        // Aquí podrías abrir un sub-modal o select para confirmar la asignación
        // Por ahora, mostramos un toast indicando que puede editar manualmente
        Toast.info(`Abriendo detalle de ${item.codigo_ot}. Puedes reasignar recursos aquí.`);
    }
}

function closeModalPlanificacion() {
    document.getElementById('modalPlanificacion').style.display = 'none';
}

async function savePlanificacionDetails(e) {
    e.preventDefault();
    const id = document.getElementById('pf_id').value;
    const fecha = document.getElementById('pf_fecha').value;
    const hh = document.getElementById('pf_hh').value;
    const estado = document.getElementById('pf_estado').value;

    try {
        // Actualizar básica
        const res = await fetch('/api/planificacion.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ 
                action: 'update_basic', // Necesitarás agregar esta acción en PHP similar a change_date
                id_planificacion: id, 
                new_date: fecha,
                hh: hh,
                estado: estado
            })
        });
        const data = await res.json();
        
        if (data.success) {
            Toast.success('Guardado correctamente');
            closeModalPlanificacion();
            loadWeekData();
        } else {
            Toast.error(data.error);
        }
    } catch (err) {
        Toast.error('Error al guardar');
    }
}

// Helpers
function getStatusColor(status) {
    switch(status) {
        case 'completada': return '#10b981'; // Green
        case 'en_ejecucion': return '#3b82f6'; // Blue
        case 'reprogramada': return '#f59e0b'; // Orange
        default: return '#94a3b8'; // Gray
    }
}

function changeWeek(offset) {
    currentWeekStart.setDate(currentWeekStart.getDate() + (offset * 7));
    loadWeekData();
}

function goToToday() {
    currentWeekStart = new Date();
    loadWeekData();
}

// Inicializar si estamos en la pestaña
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('planificacion').classList.contains('active')) {
        initPlanificacion();
    }
});
// === MÓDULO 4: KPIs LOGIC - VERSIÓN MEJORADA ===
let chartEsp = null;
let chartEstados = null;

async function loadKpis() {
    try {
        const params = new URLSearchParams({
            year: dashboardFilters.year,
            month: dashboardFilters.month,
            week: dashboardFilters.week
        });
        
        console.log('🔄 Cargando con filtros:', dashboardFilters);
        
        // 1. KPIs Globales (siempre se cargan)
        const resGlobal = await fetch(`/api/kpis.php?action=global&${params}`);
        const dataGlobal = await resGlobal.json();
        
        if (dataGlobal.success && dataGlobal.data) {
            const d = dataGlobal.data;
            
            const updateText = (id, value, suffix = '') => {
                const el = document.getElementById(id);
                if (el) el.textContent = (isFinite(value) ? value : 0) + suffix;
            };
            
            updateText('kpi-sla', d.sla_percent, '%');
            updateText('kpi-ots-closed', d.ots_closed ?? 0, '');
            updateText('kpi-ots-risk', d.ots_riesgo ?? 0, '');

            // 🆕 KPI HH DISPONIBLES
            updateText('kpi-hh-disponibles', d.hh_disponibles || 0, '');
            updateText('kpi-tecnicos-plan', d.tecnicos_plan || 0, '');

            const coberturaEl = document.getElementById('kpi-hh-cobertura');
            if (coberturaEl && d.hh_cobertura !== undefined) {
                const cov = d.hh_cobertura;
                coberturaEl.textContent = cov + '%';
                if (cov >= 90) {
                    coberturaEl.style.background = '#dcfce7';
                    coberturaEl.style.color = '#166534';
                } else if (cov >= 70) {
                    coberturaEl.style.background = '#fef3c7';
                    coberturaEl.style.color = '#92400e';
                } else {
                    coberturaEl.style.background = '#fee2e2';
                    coberturaEl.style.color = '#991b1b';
                }
            }
            
            // 🎯 KPI TOTAL HH con animación (scope unificado)
            const totalHHEl = document.getElementById('kpi-total-hh');
            if (totalHHEl && d.hh_plan !== undefined) {
                const targetHH = d.hh_plan || 0;
                const durationHH = 1200;
                const startTimeHH = performance.now();
                
                // ✅ Función dentro del mismo scope
                (function animateHH(now) {
                    const progress = Math.min((now - startTimeHH) / durationHH, 1);
                    const ease = 1 - Math.pow(1 - progress, 3);
                    const current = targetHH * ease;
                    totalHHEl.textContent = current.toLocaleString('es-CL', {
                        minimumFractionDigits: 1,
                        maximumFractionDigits: 1
                    });
                    if (progress < 1) requestAnimationFrame(animateHH);
                })(performance.now());
            }

            // 🆕 ACTUALIZAR TARJETA DE TURNOS
            const turnosDist = d.turnos_dist || {};
            const hhDia = turnosDist.dia || 0;
            const hhNoche = turnosDist.noche || 0;
            const hhMixto = turnosDist.mixto || 0; // Si tienes turnos mixtos

            // Actualizar textos
            updateText('kpi-hh-dia', hhDia, ' HH');
            updateText('kpi-hh-noche', hhNoche, ' HH');

            // Calcular porcentajes para las barras
            const totalTurnosHH = hhDia + hhNoche + hhMixto;
            const pctDia = totalTurnosHH > 0 ? (hhDia / totalTurnosHH) * 100 : 0;
            const pctNoche = totalTurnosHH > 0 ? (hhNoche / totalTurnosHH) * 100 : 0;

            // Actualizar ancho de barras
            const barDia = document.getElementById('bar-hh-dia');
            const barNoche = document.getElementById('bar-hh-noche');

            if (barDia) barDia.style.width = `${pctDia}%`;
            if (barNoche) barNoche.style.width = `${pctNoche}%`;
        }
        
        // 2. Según el modo, cargar ranking estándar o de riesgo
        if (dashboardFilters.mode === 'standard') {
            // MODO ESTÁNDAR: HHs por especialidad
            const resEsp = await fetch(`/api/kpis.php?action=chart_data&group_by=especialidad&${params}`);
            const dataEsp = await resEsp.json();
            if (dataEsp.success) {
                renderSpecialtyCards(dataEsp.data);
                restoreStandardTitles();
            }
            
            // Tabla de reprogramadas estándar
            const resRep = await fetch(`/api/kpis.php?action=reprogramadas&limit=10&${params}`);
            const dataRep = await resRep.json();
            if (dataRep.success) renderReproTable(dataRep.data);
            
        } else {
            // MODO RIESGO: OTs en riesgo por especialidad
            const resRisk = await fetch(`/api/kpis.php?action=risk_by_especialidad&${params}`);
            const dataRisk = await resRisk.json();
            if (dataRisk.success) {
                renderRiskSpecialtyCards(dataRisk.data);
                updateRiskTitles();
            }
            
            // Tabla de detalle (con posible filtro de especialidad)
            await loadRiskTable();
        }
        
        // 3. Torta de estados (siempre)
        const resPie = await fetch(`/api/kpis.php?action=chart_data&group_by=estado&${params}`);
        const dataPie = await resPie.json();
        if (dataPie.success && dataPie.data?.length > 0) {
            renderPieChart(dataPie.data);
        }
        
    } catch (err) {
        console.error('Error KPIs:', err);
        Toast.error('Error al cargar indicadores', 'Atención');
    }
}

// 🆕 Cargar tabla de riesgo (con posible drill-down)
async function loadRiskTable() {
    const params = new URLSearchParams({
        year: dashboardFilters.year,
        month: dashboardFilters.month,
        limit: 50
    });
    
    // 🆕 Si hay especialidad seleccionada, agregarla
    if (dashboardFilters.especialidad) {
        params.append('especialidad', dashboardFilters.especialidad);
    }
    
    const resOts = await fetch(`/api/kpis.php?action=risk_ots&${params}`);
    const otsData = await resOts.json();
    if (otsData.success) {
        renderRiskTable(otsData.data);
        updateTableTitle();
    }
}

let chartBar=null, chartPie=null;
function renderBarChart(data) {
    if(chartBar) chartBar.destroy();
    chartBar = new Chart(document.getElementById('chartEspecialidad').getContext('2d'), {
        type:'bar', data:{labels:data.map(d=>d.label), datasets:[{label:'HH Plan', data:data.map(d=>d.value), backgroundColor:'#3b82f6', borderRadius:4}]},
        options:{responsive:true, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true}}}
    });
}

// === GRÁFICO DE BARRAS SIMPLIFICADO ===
function renderSimpleBarChart(data) {
    const canvas = document.getElementById('chartEspecialidad');
    if (!canvas) return;
    
    const ctx = canvas.getContext('2d');
    
    // Destruir gráfico anterior
    if (window.simpleBarChart) window.simpleBarChart.destroy();
    
    const labels = data.map(d => {
        const label = d.label || 'Sin Espec.';
        return label.length > 18 ? label.substring(0, 15) + '...' : label;
    });
    const values = data.map(d => parseFloat(d.value || d.hh_plan) || 0);
    
    // ✅ Calcular máximo redondeado al siguiente 1000
    const maxValue = Math.max(...values);
    const yMax = Math.ceil(maxValue / 1000) * 1000;
    
    window.simpleBarChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'HH Planificadas',
                data: values,
                backgroundColor: 'rgba(59, 130, 246, 0.8)',
                borderColor: '#2563eb',
                borderWidth: 1,
                borderRadius: 4,
                barPercentage: 0.7
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            // ✅ DESACTIVAR animaciones pesadas
            animation: {
                duration: 400,
                easing: 'easeOutQuart'
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    enabled: true,
                    callbacks: {
                        label: (ctx) => `${ctx.parsed.y.toLocaleString('es-CL')} HH`
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    suggestedMax: yMax, // ✅ Límite superior calculado
                    // ✅ INDICADORES CADA 1.000 HH
                    ticks: {
                        stepSize: 1000,
                        font: { size: 11 },
                        color: '#64748b',
                        callback: function(value) {
                            if (value >= 1000) {
                                return (value / 1000).toFixed(0) + 'k'; // 1k, 2k, 3k...
                            }
                            return value;
                        }
                    },
                    title: { 
                        display: true, 
                        text: 'Horas Hombre (HH)', 
                        font: { size: 12, weight: '600' },
                        color: '#334155'
                    },
                    grid: {
                        color: '#f1f5f9',
                        drawBorder: false
                    }
                },
                x: {
                    ticks: { 
                        font: { size: 10 },
                        maxRotation: 45,
                        minRotation: 30,
                        color: '#64748b'
                    },
                    grid: {
                        display: false
                    }
                }
            },
            // ✅ Decimación para datasets grandes (opcional pero ayuda)
            parsing: {
                xAxisKey: false
            }
        }
    });
}

// === GRÁFICO CIRCULAR DE ESTADOS ===
function renderPieChart(data) {
    const canvas = document.getElementById('chartEstados');
    if (!canvas) return;
    
    const ctx = canvas.getContext('2d');
    if (window.simplePieChart) window.simplePieChart.destroy();
    
    const colors = {
        'completada': '#10b981', 'cerrada': '#10b981',
        'en_ejecucion': '#3b82f6', 'pendiente': '#f59e0b',
        'reprogramada': '#8b5cf6', 'no_realizada': '#ef4444'
    };
    
    window.simplePieChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: data.map(d => (d.label || 'Sin Estado').charAt(0).toUpperCase() + (d.label || '').slice(1)),
            datasets: [{
                data: data.map(d => parseInt(d.value || d.count) || 0),
                backgroundColor: data.map(d => colors[d.label] || '#cbd5e1'),
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { font: { size: 10 } } },
                tooltip: {
                    callbacks: {
                        label: (ctx) => {
                            const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                            const pct = total > 0 ? ((ctx.parsed / total) * 100).toFixed(1) : 0;
                            return `${ctx.label}: ${ctx.parsed} OTs (${pct}%)`;
                        }
                    }
                }
            },
            cutout: '65%'
        }
    });
}

// === TABLA DE REPROGRAMADAS (Modo Estándar) ===
function renderReproTable(data) {
    const tbody = document.getElementById('tablaReprogramadas');
    if (!tbody) return;
    
    // Restaurar título y encabezados estándar
    const titleEl = document.getElementById('tablaComodinTitle');
    if (titleEl) titleEl.textContent = '🔄 OTs Reprogramadas (Mayor Impacto)';
    
    const thCol4 = document.getElementById('thCol4');
    const thCol6 = document.getElementById('thCol6');
    if (thCol4) thCol4.textContent = 'Veces Reprog.';
    if (thCol6) thCol6.textContent = 'Retraso';
    
    if (!data || data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:1.5rem; color:#94a3b8;">✅ No hay OTs reprogramadas para mostrar</td></tr>';
        return;
    }
    
    // ✅ Generar FILAS de datos (no encabezados)
    tbody.innerHTML = data.map(o => `
        <tr style="border-bottom:1px solid #f1f5f9; transition:background 0.2s;" 
            onmouseover="this.style.background='#f8fafc'" 
            onmouseout="this.style.background='white'">
            <td style="padding:0.75rem; font-weight:600; font-family:monospace; color:#1e293b;">
                ${o.id_prevision_sic || '-'}
            </td>
            <td style="padding:0.75rem; color:#64748b; max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" 
                title="${o.nombre_equipo || ''}">
                ${o.nombre_equipo || '-'}
            </td>
            <td style="padding:0.75rem; text-align:center;">
                <span style="background:#e0f2fe; color:#0369a1; padding:2px 8px; border-radius:12px; font-size:0.75rem; font-weight:600;">
                    ${(o.ultimo_estado || o.estado || 'pendiente').replace('_', ' ')}
                </span>
            </td>
            <td style="padding:0.75rem; text-align:center; font-family:monospace;">
                ${parseFloat(o.total_hh_planificadas || o.hh_plan || 0).toFixed(1)}
            </td>
            <td style="padding:0.75rem; text-align:center; color:#64748b;">
                ${o.ultima_fecha_programada ? new Date(o.ultima_fecha_programada).toLocaleDateString('es-CL') : '-'}
            </td>
            <td style="padding:0.75rem; text-align:center; color:${(o.veces_reprogramadas || 0) > 0 ? '#ef4444' : '#64748b'}; font-weight:${(o.veces_reprogramadas || 0) > 0 ? 'bold' : 'normal'}">
                ${(o.veces_reprogramadas || 0) > 0 ? '+' + o.veces_reprogramadas : '0'}
            </td>
        </tr>
    `).join('');
}

function renderPieChart(data) {
    if(chartPie) chartPie.destroy();
    const colors = {'completada':'#10b981','en_ejecucion':'#3b82f6','pendiente':'#f59e0b','reprogramada':'#8b5cf6','no_realizada':'#ef4444'};
    chartPie = new Chart(document.getElementById('chartEstados').getContext('2d'), {
        type:'doughnut', data:{labels:data.map(d=>d.label.charAt(0).toUpperCase()+d.label.slice(1)), datasets:[{data:data.map(d=>d.value), backgroundColor:data.map(d=>colors[d.label]||'#cbd5e1')}]},
        options:{responsive:true, plugins:{legend:{position:'bottom'}}}
    });
}

document.addEventListener('DOMContentLoaded', loadKpis);

// === GRÁFICO SIMPLIFICADO (evita colapsos con muchos datos) ===
function renderSimpleChart(data) {
    const ctx = document.getElementById('chartEspecialidad').getContext('2d');
    
    // Destruir gráfico anterior si existe
    if (window.simpleChart) window.simpleChart.destroy();
    
    const labels = data.map(d => d.label);
    const hhPlan = data.map(d => d.hh_plan);
    const hhReal = data.map(d => d.hh_real);
    
    window.simpleChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'HH Plan',
                    data: hhPlan,
                    backgroundColor: 'rgba(59, 130, 246, 0.7)',
                    borderRadius: 4,
                    barPercentage: 0.8
                },
                {
                    label: 'HH Real*',
                    data: hhReal,
                    backgroundColor: 'rgba(16, 185, 129, 0.7)',
                    borderRadius: 4,
                    barPercentage: 0.8
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top', labels: { font: { size: 11 } } },
                tooltip: {
                    callbacks: {
                        label: (ctx) => `${ctx.dataset.label}: ${ctx.parsed.y.toFixed(1)} HH`
                    }
                }
            },
            scales: {
                y: { 
                    beginAtZero: true,
                    ticks: { font: { size: 10 } },
                    title: { display: true, text: 'Horas', font: { size: 11 } }
                },
                x: {
                    ticks: { 
                        font: { size: 9 },
                        maxRotation: 45,
                        minRotation: 45,
                        callback: function(val, idx) {
                            const label = this.getLabelForValue(val);
                            return label.length > 15 ? label.substring(0, 12) + '...' : label;
                        }
                    }
                }
            }
        }
    });
}

// === ANIMACIÓN DE NÚMEROS PARA KPIs ===
function animateValue(elementId, end, suffix = '', duration = 1000) {
    const el = document.getElementById(elementId);
    if (!el) return;
    
    const start = parseFloat(el.textContent) || 0;
    if (!isFinite(start) || !isFinite(end)) {
        el.textContent = '0' + suffix;
        return;
    }
    
    const range = end - start;
    const startTime = performance.now();
    
    function step(now) {
        const progress = Math.min((now - startTime) / duration, 1);
        const ease = 1 - Math.pow(1 - progress, 3); // Ease-out cubic
        const current = start + (range * ease);
        
        // Formatear: entero si el valor final es entero, 1 decimal si no
        el.textContent = (Number.isInteger(end) ? Math.round(current) : current.toFixed(1)) + suffix;
        
        if (progress < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
}

function renderCharts(data) {
    const labels = data.map(d => {
        const label = d.label || 'Sin Espec.';
        return label.length > 20 ? label.substring(0, 17) + '...' : label;
    });
    const hhPlan = data.map(d => parseFloat(d.hh_plan) || 0);
    const hhReal = data.map(d => parseFloat(d.hh_real) || 0);
    
    // Destruir gráficos anteriores
    if (chartEsp) chartEsp.destroy();
    if (chartEstados) chartEstados.destroy();
    
    // 📊 Gráfico de Barras: Especialidades
    const ctxEsp = document.getElementById('chartEspecialidad').getContext('2d');
    chartEsp = new Chart(ctxEsp, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'HH Planificadas',
                    data: hhPlan,
                    backgroundColor: 'rgba(203, 213, 225, 0.8)',
                    borderColor: '#94a3b8',
                    borderWidth: 1,
                    borderRadius: 4,
                    barPercentage: 0.7
                },
                {
                    label: 'HH Reales',
                    data: hhReal,
                    backgroundColor: 'rgba(59, 130, 246, 0.9)',
                    borderColor: '#2563eb',
                    borderWidth: 1,
                    borderRadius: 4,
                    barPercentage: 0.7
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { 
                legend: { position: 'top', labels: { font: { size: 11 } } },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return `${context.dataset.label}: ${context.parsed.y.toFixed(1)} HH`;
                        }
                    }
                }
            },
            scales: {
                y: { 
                    beginAtZero: true,
                    ticks: { font: { size: 10 } },
                    title: { display: true, text: 'Horas Hombre', font: { size: 11 } }
                },
                x: {
                    ticks: { font: { size: 9 }, maxRotation: 45, minRotation: 45 }
                }
            }
        }
    });
    
    // 🥧 Gráfico Circular: Estados (consulta específica o simulado)
    const ctxEstados = document.getElementById('chartEstados').getContext('2d');
    
    // Si tienes una endpoint para estados, úsalo; si no, usa distribución simulada basada en datos reales
    const stateData = {
        labels: ['Completada', 'En Proceso', 'Asignada', 'Pendiente'],
        datasets: [{
            data: [
                data.reduce((sum, d) => sum + (d.estado === 'cerrada' ? d.total_ots : 0), 0),
                data.reduce((sum, d) => sum + (d.estado === 'en_proceso' ? d.total_ots : 0), 0),
                data.reduce((sum, d) => sum + (d.estado === 'asignada' ? d.total_ots : 0), 0),
                data.reduce((sum, d) => sum + (d.estado === 'pendiente' ? d.total_ots : 0), 0)
            ].map(v => v || 1), // Evitar ceros
            backgroundColor: ['#10b981', '#3b82f6', '#f59e0b', '#94a3b8'],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    };
    
    // Si no hay datos reales de estados, usar valores por defecto
    if (stateData.datasets[0].data.every(v => v === 1)) {
        stateData.datasets[0].data = [60, 20, 10, 10];
    }
    
    chartEstados = new Chart(ctxEstados, {
        type: 'doughnut',
        data: stateData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { 
                legend: { position: 'bottom', labels: { font: { size: 10 }, padding: 12 } },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.parsed;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const pct = ((value / total) * 100).toFixed(1);
                            return `${label}: ${value} OTs (${pct}%)`;
                        }
                    }
                }
            },
            cutout: '65%'
        }
    });
}

function renderRecentTable(data) {
    const tbody = document.getElementById('tablaOtsRecentes');
    
    if (!data || data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:1.5rem; color:#94a3b8;">No hay datos recientes</td></tr>';
        return;
    }
    
    tbody.innerHTML = data.map(item => `
        <tr style="border-bottom:1px solid #f1f5f9; transition:background 0.2s;" 
            onmouseover="this.style.background='#f8fafc'" 
            onmouseout="this.style.background='white'">
            <td style="padding:0.75rem; font-weight:600; color:#1e293b; font-family:monospace;">
                ${item.codigo_ot}
            </td>
            <td style="padding:0.75rem; color:#64748b; max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="${item.equipo}">
                ${item.equipo}
            </td>
            <td style="padding:0.75rem; text-align:center;">
                <span class="badge ${item.estado_class}">${item.estado}</span>
            </td>
            <td style="padding:0.75rem; text-align:center; color:#64748b; font-family:monospace;">
                ${item.hh_plan}
            </td>
            <td style="padding:0.75rem; text-align:center; color:#64748b; font-family:monospace;">
                ${item.hh_real}
            </td>
            <td style="padding:0.75rem; text-align:center;" class="${item.retraso_class}">
                ${item.retraso}
            </td>
        </tr>
    `).join('');
}

// Cargar al iniciar si el módulo está activo
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('kpis')?.classList.contains('active')) {
        loadKpis();
    }
});

document.addEventListener('DOMContentLoaded', () => {
    // Sincronizar selects con filtros iniciales
    const yearSelect = document.getElementById('filterYear');
    const monthSelect = document.getElementById('filterMonth');
    
    if (yearSelect) yearSelect.value = dashboardFilters.year;
    if (monthSelect) monthSelect.value = dashboardFilters.month;

    if (document.getElementById('kpis')?.classList.contains('active')) {
        loadKpis();
    }
});
// === LÓGICA DE CARGA DE MANTENCIÓN ===

const dropZoneMantencion = document.getElementById('dropZoneMantencion');
const mantencionInput = document.getElementById('mantencionFile');
const mantencionSummary = document.getElementById('mantencionSummary');
const mantencionLog = document.getElementById('mantencionLog');

if (dropZoneMantencion && mantencionInput) {
    // Efectos visuales Drag & Drop
    dropZoneMantencion.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZoneMantencion.style.borderColor = '#3b82f6';
        dropZoneMantencion.style.background = '#eff6ff';
    });

    dropZoneMantencion.addEventListener('dragleave', () => {
        dropZoneMantencion.style.borderColor = '#cbd5e1';
        dropZoneMantencion.style.background = '#fff';
    });

    dropZoneMantencion.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZoneMantencion.style.borderColor = '#cbd5e1';
        dropZoneMantencion.style.background = '#fff';
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            handleMantencionUpload(files[0]);
        }
    });

    mantencionInput.addEventListener('change', (e) => {
        if (e.target.files.length > 0) {
            handleMantencionUpload(e.target.files[0]);
        }
    });
}

async function handleMantencionUpload(file) {
    // ✅ Validación ampliada: acepta .xlsx, .xls y .csv
    const validExts = ['.xlsx', '.xls', '.csv'];
    const fileExt = '.' + file.name.split('.').pop().toLowerCase();
    
    if (!validExts.includes(fileExt)) {
        alert('Por favor selecciona un archivo .xlsx o .csv válido.');
        return;
    }

    const formData = new FormData();
    formData.append('mantencion_file', file);

    try {
        mantencionSummary.style.display = 'block';
        mantencionLog.innerHTML = '<span style="color:#3b82f6; font-weight:600;">⏳ Procesando archivo... Esto puede tomar unos segundos.</span>';
        mantencionLog.style.color = '#3b82f6';

        const res = await fetch('/api/carga_mantencion.php', {
            method: 'POST',
            body: formData
        });

        const data = await res.json();

        if (data.success) {
            mantencionLog.innerHTML = `
                <div style="color:#10b981; font-weight:bold; margin-bottom:0.5rem;">✅ Carga Exitosa</div>
                <ul style="list-style:none; padding:0; margin:0; font-size:0.9rem; color:#475569;">
                    <li>📊 Filas procesadas: <strong>${data.stats.processed}</strong></li>
                    <li>✅ Registros actualizados: <strong>${data.stats.updated}</strong></li>
                    <li>❌ Errores: <strong>${data.stats.errors}</strong></li>
                </ul>
                ${data.stats.logs.length > 0 ? '<details style="margin-top:0.5rem;"><summary style="cursor:pointer; color:#64748b; font-size:0.8rem;">Ver logs detallados</summary><pre style="font-size:0.75rem; background:#fff; padding:0.5rem; border-radius:0.25rem; max-height:100px; overflow-y:auto; margin-top:0.5rem;">' + data.stats.logs.join('\n') + '</pre></details>' : ''}
            `;
            mantencionLog.style.color = '#10b981';
        } else {
            mantencionLog.innerHTML = `<div style="color:#ef4444; font-weight:bold;">❌ Error: ${data.error}</div>`;
            mantencionLog.style.color = '#ef4444';
        }

    } catch (err) {
        console.error(err);
        mantencionLog.innerHTML = '<div style="color:#ef4444; font-weight:bold;">❌ Error de conexión con el servidor.</div>';
        mantencionLog.style.color = '#ef4444';
    }
}

async function applyFilters() {
    currentFilters.year = document.getElementById('filterYear').value;
    currentFilters.month = document.getElementById('filterMonth').value;
    currentFilters.week = document.getElementById('filterWeek').value;
    
    // Actualizar lista de semanas disponibles según mes seleccionado
    await loadWeeks();
    
    // Recargar KPIs con filtros
    await loadKpis();
}

async function loadWeeks() {
    const params = new URLSearchParams({
        year: currentFilters.year,
        month: currentFilters.month
    });
    
    try {
        const res = await fetch(`/api/kpis.php?action=get_weeks&${params}`);
        const data = await res.json();
        
        const weekSelect = document.getElementById('filterWeek');
        if (data.success) {
            weekSelect.innerHTML = '<option value="">Todo el mes</option>' + 
                data.data.map(w => `<option value="${w}" ${currentFilters.week == w ? 'selected' : ''}>Semana ${w}</option>`).join('');
        }
    } catch (err) {
        console.error('Error cargando semanas:', err);
    }
}

function resetFilters() {
    document.getElementById('filterYear').value = '2026';
    document.getElementById('filterMonth').value = '';
    document.getElementById('filterWeek').value = '';
    currentFilters = { year: '2026', month: '', week: '' };
    loadKpis();
}

// === GRÁFICO CIRCULAR DE ESTADOS ===
function renderPieChartEstados(data) {
    const canvas = document.getElementById('chartEstados');
    if (!canvas) return;
    
    const ctx = canvas.getContext('2d');
    
    // Destruir gráfico anterior si existe
    if (chartEstados) chartEstados.destroy();
    
    // Mapeo de colores por estado
    const colors = {
        'completada': '#10b981',
        'cerrada': '#10b981',
        'en_ejecucion': '#3b82f6',
        'en_proceso': '#3b82f6',
        'pendiente': '#f59e0b',
        'asignada': '#5fb8d4',
        'reprogramada': '#8b5cf6',
        'cancelada': '#ef4444',
        'no_realizada': '#ef4444'
    };
    
    // Formatear labels (primera letra mayúscula)
    const labels = data.map(d => {
        const label = d.label || 'Sin Estado';
        return label.charAt(0).toUpperCase() + label.slice(1).replace('_', ' ');
    });
    
    const values = data.map(d => parseInt(d.count || d.value) || 0);
    const bgColors = data.map(d => colors[d.label] || '#cbd5e1');
    
    chartEstados = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: values,
                backgroundColor: bgColors,
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { 
                    position: 'bottom',
                    labels: { 
                        font: { size: 11 },
                        padding: 12,
                        usePointStyle: true
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                            const pct = total > 0 ? ((ctx.parsed / total) * 100).toFixed(1) : 0;
                            return `${ctx.label}: ${ctx.parsed} OTs (${pct}%)`;
                        }
                    }
                }
            },
            cutout: '65%'
        }
    });
}
// === CARDS DE ESPECIALIDADES (Rápido + Atractivo + Sin Canvas) ===
function renderSpecialtyCards(data) {
    const container = document.getElementById('containerEspecialidades');
    const totalEl = document.getElementById('kpi-total-hh-esp');
    
    if (!container) return;
    
    if (!data || data.length === 0) {
        container.innerHTML = '<p style="text-align:center; color:#94a3b8; padding:2rem;">Sin datos para el periodo seleccionado</p>';
        if (totalEl) totalEl.textContent = '0';
        return;
    }
    
    // Calcular total para porcentajes
    const totalHH = data.reduce((sum, d) => sum + (parseFloat(d.value) || 0), 0);
    const maxHH = Math.max(...data.map(d => parseFloat(d.value) || 0));
    
    if (totalEl) totalEl.textContent = totalHH.toLocaleString('es-CL', {maximumFractionDigits: 1});
    
    // Paleta de colores por especialidad
    const colors = {
        'M-POLIVALENTE': { bg: '#dbeafe', bar: '#3b82f6', text: '#1e40af' },
        'M-ELECTRICIDAD': { bg: '#fef3c7', bar: '#f59e0b', text: '#92400e' },
        'M-GASFITERIA': { bg: '#d1fae5', bar: '#10b981', text: '#065f46' },
        'M-GASFITERÍA': { bg: '#d1fae5', bar: '#10b981', text: '#065f46' },
        'M-ELECTRONICA': { bg: '#ede9fe', bar: '#8b5cf6', text: '#5b21b6' },
        'M-ELECTRÓNICA': { bg: '#ede9fe', bar: '#8b5cf6', text: '#5b21b6' },
        'M-ELECTROMECANICA': { bg: '#fee2e2', bar: '#ef4444', text: '#991b1b' },
        'M-ELECTROMECÁNICA': { bg: '#fee2e2', bar: '#ef4444', text: '#991b1b' },
        'M-CARPINTERIA': { bg: '#fed7aa', bar: '#f97316', text: '#9a3412' },
        'M-CARPINTERÍA': { bg: '#fed7aa', bar: '#f97316', text: '#9a3412' },
        'M-CLIMATIZACIÓN': { bg: '#cffafe', bar: '#06b6d4', text: '#155e75' }
    };
    
    const defaultColor = { bg: '#f1f5f9', bar: '#64748b', text: '#1e293b' };
    
    // Generar cards
    container.innerHTML = data.map((d, idx) => {
        const hh = parseFloat(d.value) || 0;
        const pct = totalHH > 0 ? ((hh / totalHH) * 100) : 0;
        const barWidth = maxHH > 0 ? ((hh / maxHH) * 100) : 0;
        const color = colors[d.label] || defaultColor;
        const rank = idx + 1;
        const rankIcon = rank === 1 ? '🥇' : rank === 2 ? '🥈' : rank === 3 ? '🥉' : `#${rank}`;
        
        return `
            <div style="background:${color.bg}; border-radius:0.75rem; padding:0.85rem 1rem; transition:transform 0.2s;" 
                 onmouseover="this.style.transform='translateX(4px)'" 
                 onmouseout="this.style.transform='translateX(0)'">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.5rem;">
                    <div style="display:flex; align-items:center; gap:0.5rem;">
                        <span style="font-size:1rem; min-width:28px;">${rankIcon}</span>
                        <span style="font-weight:700; color:${color.text}; font-size:0.95rem;">${d.label}</span>
                    </div>
                    <div style="display:flex; align-items:baseline; gap:0.4rem;">
                        <span style="font-weight:700; color:${color.text}; font-size:1.05rem; font-family:monospace;">
                            ${hh.toLocaleString('es-CL', {maximumFractionDigits: 1})}
                        </span>
                        <span style="font-size:0.75rem; color:${color.text}; opacity:0.8;">HH</span>
                        <span style="background:white; color:${color.text}; padding:2px 8px; border-radius:10px; font-size:0.7rem; font-weight:600;">
                            ${pct.toFixed(1)}%
                        </span>
                    </div>
                </div>
                <div style="height:8px; background:rgba(255,255,255,0.6); border-radius:4px; overflow:hidden;">
                    <div style="height:100%; background:${color.bar}; width:${barWidth}%; border-radius:4px; transition:width 0.8s ease-out;"></div>
                </div>
            </div>
        `;
    }).join('');
}
// 🎯 ACTIVAR/DESACTIVAR MODO RIESGO
// 🎯 TOGGLE MODO RIESGO (corregido para usar dashboardFilters)
async function toggleRiskMode() {
    if (dashboardFilters.mode === 'standard') {
        await activateRiskMode();
    } else {
        clearRiskMode();
    }
}

async function activateRiskMode() {
    console.log('🚨 Activando modo riesgo con filtros:', dashboardFilters);
    dashboardFilters.mode = 'risk';
    dashboardFilters.especialidad = null;
    
    // Estilos de ficha
    const card = document.getElementById('kpi-risk-card');
    if (card) {
        card.style.background = 'linear-gradient(135deg, #ef4444 0%, #b91c1c 100%)';
        card.style.color = 'white';
        const hint = card.querySelector('[id="kpi-risk-hint"]');
        if (hint) {
            hint.style.color = '#fecaca';
            hint.innerHTML = '🔍 Filtro activo · Click para desactivar';
        }
    }
    
    const closeBtn = document.getElementById('kpi-risk-close');
    if (closeBtn) closeBtn.style.display = 'flex';
    
    // Cambiar etiqueta total a "OTs"
    const totalEspEl = document.getElementById('kpi-total-hh-esp');
    if (totalEspEl && totalEspEl.nextElementSibling) {
        totalEspEl.nextElementSibling.textContent = 'OTs';
    }
    
    // Recargar todo con modo riesgo
    await loadKpis();
    
    Toast.info('🔍 Mostrando OTs en Riesgo', 'Filtro activo');
}

function clearRiskMode() {
    console.log('🧹 Limpiando filtro de riesgo...');
    dashboardFilters.mode = 'standard';
    dashboardFilters.especialidad = null;
    dashboardFilters.especialidadLabel = '';
    
    // Restaurar ficha
    const card = document.getElementById('kpi-risk-card');
    if (card) {
        card.style.background = 'white';
        card.style.color = 'inherit';
        const hint = card.querySelector('[id="kpi-risk-hint"]');
        if (hint) {
            hint.style.color = '#ef4444';
            hint.innerHTML = '⚠️ Click para ver detalle';
        }
    }
    
    const closeBtn = document.getElementById('kpi-risk-close');
    if (closeBtn) closeBtn.style.display = 'none';
    
    // Restaurar etiqueta a "HH"
    const totalEspEl = document.getElementById('kpi-total-hh-esp');
    if (totalEspEl && totalEspEl.nextElementSibling) {
        totalEspEl.nextElementSibling.textContent = 'HH';
    }
    
    // Recargar datos estándar
    loadKpis();
    Toast.success('✅ Vista estándar restaurada', 'Filtro limpiado');
}

// Cuando cambian los filtros superiores
function applyFilters() {
    dashboardFilters.year = document.getElementById('filterYear').value;
    dashboardFilters.month = document.getElementById('filterMonth').value;
    dashboardFilters.week = document.getElementById('filterWeek').value;
    dashboardFilters.especialidad = null; // Reset drill-down al cambiar filtros globales
    
    loadWeeks();
    loadKpis();
}

// 📊 CARGAR DATOS FILTRADOS POR RIESGO
async function loadRiskData() {
    const params = new URLSearchParams({
        year: currentFilters.year || new Date().getFullYear(),
        month: currentFilters.month || '',
        limit: 20
    });
    
    try {
        // 1. Ranking de especialidades SOLO en riesgo
        const resEsp = await fetch(`/api/kpis.php?action=risk_by_especialidad&${params}`);
        const espData = await resEsp.json();
        if (espData.success) {
            renderRiskSpecialtyCards(espData.data);
        }
        
        // 2. Detalle de OTs en riesgo
        const resOts = await fetch(`/api/kpis.php?action=risk_ots&${params}`);
        const otsData = await resOts.json();
        if (otsData.success) {
            renderRiskTable(otsData.data);
        }
    } catch (err) {
        console.error('Error cargando datos de riesgo:', err);
    }
}

// 🏆 RENDER RANKING EN MODO RIESGO (muestra OTs en lugar de HH)
function renderRiskSpecialtyCards(data) {
    const container = document.getElementById('containerEspecialidades');
    const totalEl = document.getElementById('kpi-total-hh-esp');
    
    if (!container) return;
    
    if (!data || data.length === 0) {
        container.innerHTML = '<p style="text-align:center; color:#94a3b8; padding:2rem;">✅ No hay OTs en riesgo para este periodo</p>';
        if (totalEl) totalEl.textContent = '0';
        return;
    }
    
    const totalOts = data.reduce((sum, d) => sum + (d.value || 0), 0);
    const maxOts = Math.max(...data.map(d => d.value || 0));
    
    if (totalEl) {
        totalEl.textContent = totalOts.toLocaleString('es-CL');
        if (totalEl.nextElementSibling) totalEl.nextElementSibling.textContent = 'OTs';
    }
    
    const colors = {
        'M-POLIVALENTE': { bg: '#fee2e2', bar: '#ef4444', text: '#991b1b' },
        'M-ELECTRICIDAD': { bg: '#fef3c7', bar: '#f59e0b', text: '#92400e' },
        'M-GASFITERÍA': { bg: '#fed7aa', bar: '#f97316', text: '#9a3412' },
        'M-GASFITERIA': { bg: '#fed7aa', bar: '#f97316', text: '#9a3412' },
        'M-ELECTRÓNICA': { bg: '#ede9fe', bar: '#8b5cf6', text: '#5b21b6' },
        'M-ELECTROMECÁNICA': { bg: '#fecaca', bar: '#dc2626', text: '#7f1d1d' },
        'M-CLIMATIZACIÓN': { bg: '#cffafe', bar: '#06b6d4', text: '#155e75' }
    };
    const defaultColor = { bg: '#fef2f2', bar: '#ef4444', text: '#991b1b' };
    
    container.innerHTML = data.map((d, idx) => {
        const ots = d.value || 0;
        const pct = totalOts > 0 ? ((ots / totalOts) * 100) : 0;
        const barWidth = maxOts > 0 ? ((ots / maxOts) * 100) : 0;
        const color = colors[d.label] || defaultColor;
        const rankIcon = idx === 0 ? '🚨' : idx === 1 ? '⚠️' : idx === 2 ? '⚡' : `#${idx + 1}`;
        
        // 🆕 ¿Esta especialidad está seleccionada?
        const isSelected = dashboardFilters.especialidad === d.code;
        const selectedStyle = isSelected 
            ? `border: 3px solid ${color.bar}; box-shadow: 0 0 0 3px ${color.bar}40;` 
            : 'border: 3px solid transparent;';
        
        return `
            <div onclick="filterByEspecialidad(${d.code}, '${d.label}')" 
                 style="background:${color.bg}; border-radius:0.75rem; padding:0.85rem 1rem; border-left:3px solid ${color.bar}; cursor:pointer; transition:all 0.2s; ${selectedStyle}"
                 onmouseover="this.style.transform='translateX(4px)'" 
                 onmouseout="this.style.transform='translateX(0)'">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.5rem;">
                    <div style="display:flex; align-items:center; gap:0.5rem;">
                        <span style="font-size:1rem;">${rankIcon}</span>
                        <span style="font-weight:700; color:${color.text}; font-size:0.95rem;">
                            ${d.label}
                            ${isSelected ? ' <span style="font-size:0.7rem; background:white; padding:1px 6px; border-radius:8px;">✓ FILTRADO</span>' : ''}
                        </span>
                    </div>
                    <div style="display:flex; align-items:baseline; gap:0.4rem;">
                        <span style="font-weight:700; color:${color.text}; font-size:1.05rem; font-family:monospace;">${ots}</span>
                        <span style="font-size:0.75rem; color:${color.text}; opacity:0.8;">OTs</span>
                        <span style="background:white; color:#dc2626; padding:2px 8px; border-radius:10px; font-size:0.7rem; font-weight:600;">
                            máx ${d.max_retraso}d
                        </span>
                    </div>
                </div>
                <div style="height:8px; background:rgba(255,255,255,0.6); border-radius:4px; overflow:hidden;">
                    <div style="height:100%; background:${color.bar}; width:${barWidth}%; border-radius:4px; transition:width 0.5s;"></div>
                </div>
                <div style="font-size:0.7rem; color:${color.text}; opacity:0.7; margin-top:0.3rem;">
                    ${isSelected ? '🔍 Click de nuevo para quitar filtro' : '👆 Click para ver detalle'}
                </div>
            </div>
        `;
    }).join('');
}

// 🆕 FUNCIÓN DRILL-DOWN: Click en especialidad
function filterByEspecialidad(code, label) {
    // Toggle: si ya está seleccionada, quitar filtro
    if (dashboardFilters.especialidad === code) {
        dashboardFilters.especialidad = null;
        dashboardFilters.especialidadLabel = '';
        Toast.info(`🔄 Mostrando todas las especialidades`, 'Filtro quitado');
    } else {
        dashboardFilters.especialidad = code;
        dashboardFilters.especialidadLabel = label;
        Toast.success(`🔍 Filtrando: ${label}`, 'Drill-down activo');
    }
    
    // Recargar solo lo necesario
    loadRiskTable();
    
    // Re-renderizar ranking para mostrar selección
    fetch(`/api/kpis.php?action=risk_by_especialidad&year=${dashboardFilters.year}&month=${dashboardFilters.month}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) renderRiskSpecialtyCards(data.data);
        });
}

// 📋 RENDER TABLA DE OTs EN RIESGO (Comodín reutilizable)
// === TABLA DE OTs EN RIESGO (Modo Riesgo) ===
function renderRiskTable(data) {
    const tbody = document.getElementById('tablaReprogramadas');
    if (!tbody) return;
    
    // Cambiar título y encabezados para modo riesgo
    const titleEl = document.getElementById('tablaComodinTitle');
    if (titleEl) titleEl.innerHTML = '⚠️ Detalle OTs en Riesgo (Mayor Retraso)';
    
    const thCol4 = document.getElementById('thCol4');
    const thCol6 = document.getElementById('thCol6');
    if (thCol4) thCol4.textContent = 'HH Plan';
    if (thCol6) thCol6.textContent = 'Días Retraso';
    
    if (!data || data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:1.5rem; color:#94a3b8;">✅ No hay OTs en riesgo para este periodo</td></tr>';
        return;
    }
    
    // ✅ Generar FILAS de datos (no encabezados)
    tbody.innerHTML = data.map(o => {
        const diasRetraso = o.dias_retraso || 0;
        const riesgoColor = diasRetraso > 30 ? '#991b1b' : diasRetraso > 14 ? '#dc2626' : '#ef4444';
        const riesgoBg = diasRetraso > 30 ? '#fee2e2' : diasRetraso > 14 ? '#fef2f2' : 'white';
        
        return `
            <tr style="border-bottom:1px solid #f1f5f9; background:${riesgoBg}; transition:background 0.2s;" 
                onmouseover="this.style.background='#fef2f2'" 
                onmouseout="this.style.background='${riesgoBg}'">
                <td style="padding:0.75rem; font-weight:600; font-family:monospace; color:#1e293b;">
                    ${o.id_prevision_sic || '-'}
                </td>
                <td style="padding:0.75rem; color:#64748b;">
                    ${o.nombre_equipo || '-'}
                    <br>
                    <small style="color:#94a3b8;">${o.especialidad_nombre || ''}</small>
                </td>
                <td style="padding:0.75rem; text-align:center;">
                    <span style="background:#fee2e2; color:#991b1b; padding:2px 8px; border-radius:12px; font-size:0.75rem; font-weight:600;">
                        ${(o.ultimo_estado || 'pendiente').replace('_', ' ')}
                    </span>
                </td>
                <td style="padding:0.75rem; text-align:center; font-family:monospace;">
                    ${parseFloat(o.total_hh_planificadas || 0).toFixed(1)}
                </td>
                <td style="padding:0.75rem; text-align:center; color:#64748b;">
                    ${o.ultima_fecha_programada ? new Date(o.ultima_fecha_programada).toLocaleDateString('es-CL') : '-'}
                </td>
                <td style="padding:0.75rem; text-align:center;">
                    <span style="background:${riesgoColor}; color:white; padding:4px 10px; border-radius:12px; font-weight:700; font-size:0.85rem;">
                        ${diasRetraso}d
                    </span>
                </td>
            </tr>
        `;
    }).join('');
}
function updateTableHeaders(mode) {
    const thead = document.getElementById('tablaComodinHeader');
    if (!thead) return;
    
    const ths = thead.querySelectorAll('th');
    if (mode === 'risk') {
        ths[5].textContent = 'Días Retraso'; // Última columna
    } else {
        ths[5].textContent = 'Veces Reprog.';
    }
}
function updateTableTitle() {
    const tableTitle = document.getElementById('tablaComodinTitle');
    if (!tableTitle) return;
    
    if (dashboardFilters.especialidad) {
        tableTitle.innerHTML = `⚠️ Detalle OTs en Riesgo: <strong style="color:#3b82f6;">${dashboardFilters.especialidadLabel}</strong>
            <button onclick="filterByEspecialidad(${dashboardFilters.especialidad}, '${dashboardFilters.especialidadLabel}')" 
                    style="background:#fee2e2; color:#ef4444; border:none; padding:2px 8px; border-radius:12px; font-size:0.75rem; cursor:pointer; margin-left:0.5rem;">
                ✕ Quitar filtro
            </button>`;
    } else {
        tableTitle.innerHTML = '⚠️ Detalle OTs en Riesgo (Mayor Retraso)';
    }
}

function updateRiskTitles() {
    const rankingTitle = document.querySelector('#containerEspecialidades')
        ?.closest('div[style*="background:white"]')
        ?.querySelector('h3');
    if (rankingTitle) {
        rankingTitle.innerHTML = '⚠️ OTs en Riesgo por Especialidad <span style="font-size:0.75rem; color:#64748b; font-weight:normal;">(Click para filtrar)</span>';
    }
}

function restoreStandardTitles() {
    const rankingTitle = document.querySelector('#containerEspecialidades')
        ?.closest('div[style*="background:white"]')
        ?.querySelector('h3');
    if (rankingTitle) rankingTitle.innerHTML = '🏆 Ranking de Especialidades';
    
    const tableTitle = document.getElementById('tablaComodinTitle');
    if (tableTitle) tableTitle.innerHTML = '🔄 OTs Reprogramadas (Mayor Impacto)';
}
</script>
    <!-- MODAL ESPECIALIDADES -->
    <div id="modalEspecialidades" style="display:none; position:fixed; inset:0; background:rgba(15, 23, 42, 0.6); backdrop-filter:blur(4px); z-index:2000; justify-content:center; align-items:center; padding:1rem;">
        <div style="background:#fff; padding:0; border-radius:1rem; width:90%; max-width:600px; box-shadow:0 25px 50px -12px rgba(0, 0, 0, 0.25); overflow:hidden;">
            <div style="padding:1.25rem 1.5rem; border-bottom:1px solid #e2e8f0; background:#f8fafc; display:flex; justify-content:space-between; align-items:center;">
                <h3 style="margin:0; font-size:1.1rem; font-weight:600; color:#1e293b;">🛠️ Mantenedor de Especialidades</h3>
                <button onclick="closeCatalogModal('especialidad')" style="background:none; border:none; font-size:1.5rem; color:#94a3b8; cursor:pointer;">&times;</button>
            </div>
            <div style="padding:1.5rem;">
                <!-- Buscador -->
                <input type="text" id="searchEsp" placeholder="🔍 Buscar especialidad..." onkeyup="filterCatalog('esp')" style="width:100%; padding:0.6rem; margin-bottom:1rem; border:1px solid #cbd5e1; border-radius:0.5rem;">
                
                <!-- Lista -->
                <div style="max-height:300px; overflow-y:auto; border:1px solid #e2e8f0; border-radius:0.5rem;">
                    <table style="width:100%; border-collapse:collapse;">
                        <thead style="background:#f1f5f9; position:sticky; top:0;">
                            <tr>
                                <th style="padding:0.75rem; text-align:left; font-size:0.85rem; color:#64748b;">Código</th>
                                <th style="padding:0.75rem; text-align:left; font-size:0.85rem; color:#64748b;">Nombre</th>
                                <th style="padding:0.75rem; text-align:center; font-size:0.85rem; color:#64748b;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="listaEspecialidadesBody"></tbody>
                    </table>
                </div>

                <!-- Formulario Crear/Editar -->
                <form id="formEspecialidad" onsubmit="saveCatalogItem(event, 'especialidad')" style="margin-top:1rem; padding-top:1rem; border-top:1px solid #e2e8f0;">
                    <input type="hidden" id="esp_id">
                    <div style="display:grid; grid-template-columns:1fr 2fr; gap:1rem; margin-bottom:1rem;">
                        <input type="text" id="esp_codigo" placeholder="Código (ej: M-CLIMA)" required style="padding:0.6rem; border:1px solid #cbd5e1; border-radius:0.5rem;">
                        <input type="text" id="esp_nombre" placeholder="Nombre Completo" required style="padding:0.6rem; border:1px solid #cbd5e1; border-radius:0.5rem;">
                    </div>
                    <div style="display:flex; gap:0.5rem; justify-content:flex-end;">
                        <button type="button" onclick="resetForm('esp')" style="padding:0.5rem 1rem; border:1px solid #e2e8f0; border-radius:0.5rem; background:#fff; cursor:pointer;">Cancelar</button>
                        <button type="submit" style="padding:0.5rem 1rem; border:none; border-radius:0.5rem; background:#3b82f6; color:#fff; cursor:pointer;">💾 Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL TURNOS -->
    <div id="modalTurnos" style="display:none; position:fixed; inset:0; background:rgba(15, 23, 42, 0.6); backdrop-filter:blur(4px); z-index:2000; justify-content:center; align-items:center; padding:1rem;">
        <div style="background:#fff; padding:0; border-radius:1rem; width:90%; max-width:600px; box-shadow:0 25px 50px -12px rgba(0, 0, 0, 0.25); overflow:hidden;">
            <div style="padding:1.25rem 1.5rem; border-bottom:1px solid #e2e8f0; background:#f8fafc; display:flex; justify-content:space-between; align-items:center;">
                <h3 style="margin:0; font-size:1.1rem; font-weight:600; color:#1e293b;">⏱️ Mantenedor de Turnos</h3>
                <button onclick="closeCatalogModal('turno')" style="background:none; border:none; font-size:1.5rem; color:#94a3b8; cursor:pointer;">&times;</button>
            </div>
            <div style="padding:1.5rem;">
                <!-- Buscador -->
                <input type="text" id="searchTurno" placeholder="🔍 Buscar turno..." onkeyup="filterCatalog('turno')" style="width:100%; padding:0.6rem; margin-bottom:1rem; border:1px solid #cbd5e1; border-radius:0.5rem;">
                
                <!-- Lista -->
                <div style="max-height:300px; overflow-y:auto; border:1px solid #e2e8f0; border-radius:0.5rem;">
                    <table style="width:100%; border-collapse:collapse;">
                        <thead style="background:#f1f5f9; position:sticky; top:0;">
                            <tr>
                                <th style="padding:0.75rem; text-align:left; font-size:0.85rem; color:#64748b;">Código</th>
                                <th style="padding:0.75rem; text-align:left; font-size:0.85rem; color:#64748b;">Nombre</th>
                                <th style="padding:0.75rem; text-align:center; font-size:0.85rem; color:#64748b;">HH/Día</th>
                                <th style="padding:0.75rem; text-align:center; font-size:0.85rem; color:#64748b;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="listaTurnosBody"></tbody>
                    </table>
                </div>

                <!-- Formulario Crear/Editar -->
                <form id="formTurno" onsubmit="saveCatalogItem(event, 'turno')" style="margin-top:1rem; padding-top:1rem; border-top:1px solid #e2e8f0;">
                    <input type="hidden" id="turno_id">
                    <div style="display:grid; grid-template-columns:1fr 2fr 1fr; gap:1rem; margin-bottom:1rem;">
                        <input type="text" id="turno_codigo" placeholder="Código (ej: 5x2)" required style="padding:0.6rem; border:1px solid #cbd5e1; border-radius:0.5rem;">
                        <input type="text" id="turno_nombre" placeholder="Nombre (ej: Rotativo 5x2)" required style="padding:0.6rem; border:1px solid #cbd5e1; border-radius:0.5rem;">
                        <input type="number" step="0.5" id="turno_hh" placeholder="HH" value="8" required style="padding:0.6rem; border:1px solid #cbd5e1; border-radius:0.5rem;">
                    </div>
                    <div style="display:flex; gap:0.5rem; justify-content:flex-end;">
                        <button type="button" onclick="resetForm('turno')" style="padding:0.5rem 1rem; border:1px solid #e2e8f0; border-radius:0.5rem; background:#fff; cursor:pointer;">Cancelar</button>
                        <button type="submit" style="padding:0.5rem 1rem; border:none; border-radius:0.5rem; background:#3b82f6; color:#fff; cursor:pointer;">💾 Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- HTML: Colócalo antes de cerrar </body> -->
    <div id="loading-overlay" style="display: none;">
        <div class="spinner"></div>
        <p>Procesando archivo... Por favor, espere.</p>
    </div>

    <style>
    #loading-overlay {
        position: fixed;
        top: 0; left: 0;
        width: 100%; height: 100%;
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(4px);
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        z-index: 9999;
        font-family: system-ui, -apple-system, sans-serif;
        font-size: 1.1rem;
        color: #1e293b;
        text-align: center;
    }
    .spinner {
        width: 50px;
        height: 50px;
        border: 5px solid #e2e8f0;
        border-top: 5px solid #2563eb;
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
        margin-bottom: 16px;
    }
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    </style>
    <!-- ═══════════════════════════════════════════════════════ -->
<!-- 🤖 WIDGET IA ASISTENTE + SWITCH ON/OFF                 -->
<!-- ═══════════════════════════════════════════════════════ -->
<style>
    /* Switch On/Off en header del módulo KPIs */
    .ai-toggle-container {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.4rem 0.8rem;
        background: #f1f5f9;
        border-radius: 2rem;
        border: 1px solid #e2e8f0;
    }
    .ai-toggle-label {
        font-size: 0.8rem;
        color: #64748b;
        font-weight: 600;
    }
    .ai-toggle-switch {
        position: relative;
        width: 44px;
        height: 24px;
        background: #cbd5e1;
        border-radius: 12px;
        cursor: pointer;
        transition: background 0.3s;
    }
    .ai-toggle-switch.active {
        background: linear-gradient(135deg, #8b5cf6, #6366f1);
    }
    .ai-toggle-switch::after {
        content: '';
        position: absolute;
        top: 2px;
        left: 2px;
        width: 20px;
        height: 20px;
        background: white;
        border-radius: 50%;
        transition: transform 0.3s;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }
    .ai-toggle-switch.active::after {
        transform: translateX(20px);
    }
    
    /* Botón flotante del chat */
    .ai-chat-button {
        position: fixed;
        bottom: 2rem;
        right: 2rem;
        width: 64px;
        height: 64px;
        border-radius: 50%;
        background: linear-gradient(135deg, #8b5cf6, #6366f1);
        border: none;
        cursor: pointer;
        box-shadow: 0 8px 24px rgba(139, 92, 246, 0.4);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.8rem;
        transition: all 0.3s;
        z-index: 1500;
        animation: pulseAI 2s infinite;
    }
    .ai-chat-button:hover {
        transform: scale(1.1);
        box-shadow: 0 12px 32px rgba(139, 92, 246, 0.5);
    }
    .ai-chat-button.hidden {
        display: none;
    }
    @keyframes pulseAI {
        0%, 100% { box-shadow: 0 8px 24px rgba(139, 92, 246, 0.4); }
        50% { box-shadow: 0 8px 32px rgba(139, 92, 246, 0.7); }
    }
    
    /* Ventana del chat */
    .ai-chat-window {
        position: fixed;
        bottom: 2rem;
        right: 2rem;
        width: 400px;
        height: 600px;
        max-height: calc(100vh - 4rem);
        background: white;
        border-radius: 1.5rem;
        box-shadow: 0 20px 60px rgba(0,0,0,0.2);
        display: none;
        flex-direction: column;
        z-index: 1501;
        overflow: hidden;
        animation: slideInChat 0.3s ease;
    }
    .ai-chat-window.open {
        display: flex;
    }
    @keyframes slideInChat {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    /* Header del chat */
    .ai-chat-header {
        background: linear-gradient(135deg, #8b5cf6, #6366f1);
        color: white;
        padding: 1rem 1.25rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .ai-chat-title {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-weight: 700;
    }
    .ai-chat-close {
        background: rgba(255,255,255,0.2);
        border: none;
        color: white;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        cursor: pointer;
        font-size: 1rem;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .ai-chat-close:hover {
        background: rgba(255,255,255,0.3);
    }
    
    /* Mensajes */
    .ai-chat-messages {
        flex: 1;
        overflow-y: auto;
        padding: 1rem;
        background: #f8fafc;
    }
    .ai-message {
        margin-bottom: 1rem;
        display: flex;
        gap: 0.5rem;
        animation: fadeIn 0.3s ease;
    }
    .ai-message.user {
        flex-direction: row-reverse;
    }
    .ai-message-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        flex-shrink: 0;
    }
    .ai-message.ai .ai-message-avatar {
        background: linear-gradient(135deg, #8b5cf6, #6366f1);
        color: white;
    }
    .ai-message.user .ai-message-avatar {
        background: #3b82f6;
        color: white;
    }
    .ai-message-bubble {
        max-width: 80%;
        padding: 0.75rem 1rem;
        border-radius: 1rem;
        font-size: 0.9rem;
        line-height: 1.5;
    }
    .ai-message.ai .ai-message-bubble {
        background: white;
        color: #1e293b;
        border-bottom-left-radius: 4px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    .ai-message.user .ai-message-bubble {
        background: #3b82f6;
        color: white;
        border-bottom-right-radius: 4px;
    }
    .ai-message-bubble strong { font-weight: 700; }
    .ai-message-bubble ul { margin: 0.5rem 0; padding-left: 1.2rem; }
    
    /* Sugerencias rápidas */
    .ai-suggestions {
        padding: 0.5rem 1rem;
        background: white;
        border-top: 1px solid #e2e8f0;
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }
    .ai-suggestion {
        padding: 0.4rem 0.8rem;
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        font-size: 0.75rem;
        cursor: pointer;
        transition: all 0.2s;
    }
    .ai-suggestion:hover {
        background: #e0e7ff;
        border-color: #8b5cf6;
        color: #6366f1;
    }
    
    /* Input */
    .ai-chat-input-container {
        padding: 0.75rem 1rem;
        background: white;
        border-top: 1px solid #e2e8f0;
        display: flex;
        gap: 0.5rem;
    }
    .ai-chat-input {
        flex: 1;
        padding: 0.6rem 0.9rem;
        border: 1px solid #e2e8f0;
        border-radius: 1.5rem;
        font-size: 0.9rem;
        outline: none;
        transition: border-color 0.2s;
    }
    .ai-chat-input:focus {
        border-color: #8b5cf6;
    }
    .ai-chat-send {
        background: linear-gradient(135deg, #8b5cf6, #6366f1);
        color: white;
        border: none;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
    }
    .ai-chat-send:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    
    /* Indicador de escritura */
    .ai-typing {
        display: flex;
        gap: 4px;
        padding: 0.75rem 1rem;
    }
    .ai-typing-dot {
        width: 8px;
        height: 8px;
        background: #cbd5e1;
        border-radius: 50%;
        animation: typing 1.4s infinite;
    }
    .ai-typing-dot:nth-child(2) { animation-delay: 0.2s; }
    .ai-typing-dot:nth-child(3) { animation-delay: 0.4s; }
    @keyframes typing {
        0%, 60%, 100% { opacity: 0.3; transform: translateY(0); }
        30% { opacity: 1; transform: translateY(-4px); }
    }
</style>

<!-- Botón flotante -->
<button class="ai-chat-button hidden" id="aiChatButton" onclick="openAIChat()" title="Asistente IA">
    🤖
</button>

<!-- Ventana del chat -->
<div class="ai-chat-window" id="aiChatWindow">
    <div class="ai-chat-header">
        <div class="ai-chat-title">
            <span>🤖</span>
            <span>Asistente MedicalOT</span>
        </div>
        <button class="ai-chat-close" onclick="closeAIChat()">✕</button>
    </div>
    
    <div class="ai-chat-messages" id="aiChatMessages">
        <!-- Mensaje de bienvenida -->
        <div class="ai-message ai">
            <div class="ai-message-avatar">🤖</div>
            <div class="ai-message-bubble">
                ¡Hola! Soy tu asistente de MedicalOT. Puedo ayudarte a analizar los datos de mantenimiento del hospital.
                <br><br>
                ¿Qué te gustaría saber? Por ejemplo:
                <ul>
                    <li>Resumen del mes</li>
                    <li>OTs en riesgo</li>
                    <li>Análisis por especialidad</li>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="ai-suggestions" id="aiSuggestions">
        <button class="ai-suggestion" onclick="sendSuggestion('Dame un resumen ejecutivo del mes actual')">📊 Resumen del mes</button>
        <button class="ai-suggestion" onclick="sendSuggestion('¿Qué OTs debería priorizar hoy?')">🎯 Prioridades</button>
        <button class="ai-suggestion" onclick="sendSuggestion('Analiza las especialidades con más riesgo')">⚠️ Especialidades en riesgo</button>
        <button class="ai-suggestion" onclick="sendSuggestion('¿Hay algo inusual en los datos?')">🔍 Anomalías</button>
    </div>
    
    <div class="ai-chat-input-container">
        <input type="text" 
               class="ai-chat-input" 
               id="aiChatInput" 
               placeholder="Pregunta algo sobre los datos..."
               onkeypress="if(event.key==='Enter') sendMessage()">
        <button class="ai-chat-send" id="aiChatSend" onclick="sendMessage()">➤</button>
    </div>
</div>

<script>
    // ═══════════════════════════════════════════════════════
    // 🤖 LÓGICA DEL ASISTENTE IA
    // ═══════════════════════════════════════════════════════

    // Estado global de la IA (persistente en localStorage)
    const AI_STORAGE_KEY = 'medicalot_ai_enabled';

    // Inicializar estado al cargar
    (function initAI() {
        const isEnabled = localStorage.getItem(AI_STORAGE_KEY) !== 'false'; // Default: ON
        updateAIToggle(isEnabled);
    })();

    function toggleAI() {
        const currentState = localStorage.getItem(AI_STORAGE_KEY) !== 'false';
        const newState = !currentState;
        localStorage.setItem(AI_STORAGE_KEY, newState);
        updateAIToggle(newState);
        
        if (!newState) {
            closeAIChat();
            Toast.info('🤖 Asistente IA desactivado', 'Configuración');
        } else {
            Toast.success('🤖 Asistente IA activado', 'Configuración');
        }
    }

    function updateAIToggle(enabled) {
        const toggle = document.getElementById('aiToggleSwitch');
        const button = document.getElementById('aiChatButton');
        const container = document.getElementById('aiToggleContainer');
        
        if (enabled) {
            toggle.classList.add('active');
            button.classList.remove('hidden');
        } else {
            toggle.classList.remove('active');
            button.classList.add('hidden');
        }
    }

    function isAIEnabled() {
        return localStorage.getItem(AI_STORAGE_KEY) !== 'false';
    }

    function openAIChat() {
        if (!isAIEnabled()) {
            Toast.error('Activa la IA primero con el switch', 'Atención');
            return;
        }
        document.getElementById('aiChatWindow').classList.add('open');
        document.getElementById('aiChatButton').classList.add('hidden');
        document.getElementById('aiChatInput').focus();
    }

    function closeAIChat() {
        document.getElementById('aiChatWindow').classList.remove('open');
        if (isAIEnabled()) {
            document.getElementById('aiChatButton').classList.remove('hidden');
        }
    }

    function addMessage(content, isUser = false) {
        const messages = document.getElementById('aiChatMessages');
        const avatar = isUser ? '👤' : '🤖';
        const className = isUser ? 'user' : 'ai';
        
        // Formato básico de markdown (negritas, bullets, saltos)
        let formattedContent = content
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            .replace(/^\s*[-•]\s+(.+)$/gm, '<li>$1</li>')
            .replace(/(<li>.*<\/li>)/s, '<ul>$1</ul>')
            .replace(/\n/g, '<br>');
        
        const messageHTML = `
            <div class="ai-message ${className}">
                <div class="ai-message-avatar">${avatar}</div>
                <div class="ai-message-bubble">${formattedContent}</div>
            </div>
        `;
        messages.insertAdjacentHTML('beforeend', messageHTML);
        messages.scrollTop = messages.scrollHeight;
    }

    function showTyping() {
        const messages = document.getElementById('aiChatMessages');
        const typingHTML = `
            <div class="ai-message ai" id="aiTyping">
                <div class="ai-message-avatar">🤖</div>
                <div class="ai-message-bubble">
                    <div class="ai-typing">
                        <div class="ai-typing-dot"></div>
                        <div class="ai-typing-dot"></div>
                        <div class="ai-typing-dot"></div>
                    </div>
                </div>
            </div>
        `;
        messages.insertAdjacentHTML('beforeend', typingHTML);
        messages.scrollTop = messages.scrollHeight;
    }

    function hideTyping() {
        const typing = document.getElementById('aiTyping');
        if (typing) typing.remove();
    }

    function sendSuggestion(text) {
        document.getElementById('aiChatInput').value = text;
        sendMessage();
    }

    async function sendMessage() {
        const input = document.getElementById('aiChatInput');
        const sendBtn = document.getElementById('aiChatSend');
        const message = input.value.trim();
        
        if (!message) return;
        
        // Deshabilitar input
        input.value = '';
        input.disabled = true;
        sendBtn.disabled = true;
        
        // Mostrar mensaje del usuario
        addMessage(message, true);
        
        // Mostrar indicador de escritura
        showTyping();
        
        try {
            const response = await fetch('/api/ai_assistant.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message: message })
            });
            
            const data = await response.json();
            
            hideTyping();
            
            if (data.success) {
                addMessage(data.message, false);
            } else {
                addMessage(`⚠️ Error: ${data.error || 'No se pudo obtener respuesta'}`, false);
            }
        } catch (err) {
            hideTyping();
            addMessage(`⚠️ Error de conexión: ${err.message}`, false);
        } finally {
            input.disabled = false;
            sendBtn.disabled = false;
            input.focus();
        }
    }
    async function loadTurnosKPI() {
        const params = new URLSearchParams({
            year: dashboardFilters.year,
            month: dashboardFilters.month
        });
        
        try {
            const res = await fetch(`/api/kpis.php?action=turnos_distribution&${params}`);
            const data = await res.json();
            
            if (data.success && data.data.length > 0) {
                // Ejemplo: Mostrar HH Nocturnas vs Diurnas
                const hhNoche = data.data.filter(d => d.turno_tipo === 'noche').reduce((sum, d) => sum + parseFloat(d.total_hh), 0);
                const hhDia = data.data.filter(d => d.turno_tipo === 'dia').reduce((sum, d) => sum + parseFloat(d.total_hh), 0);
                
                console.log(`HH Día: ${hhDia}, HH Noche: ${hhNoche}`);
                // Aquí puedes actualizar un gráfico de Chart.js o una barra de progreso
            }
        } catch (err) {
            console.error("Error cargando KPI Turnos:", err);
        }
    }
</script>
</body>
</html>