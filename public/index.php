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
            <div style="max-width:800px; margin:0 auto;">
                <h3 style="margin-bottom:1rem;">Carga y Validación SIC</h3>
                <div class="upload-zone" id="dropZone"><input type="file" id="sicFile" accept=".csv" style="display:none"><p style="font-weight:600;">Arrastra tu archivo SIC o haz clic aquí</p><p style="font-size:0.8rem; color:var(--gray-600);">Solo archivos .csv | Máx 50MB</p></div>
                <div id="sicSummary" class="summary-box"><h4>📋 Resumen de Validación</h4><div id="sicLog" style="font-size:0.9rem; margin:0.5rem 0;"></div><button class="btn-volver" style="background:var(--primary); margin-top:0.5rem;" onclick="confirmLoad()">✅ Confirmar Carga</button></div>
                <table style="width:100%; margin-top:1.5rem; border-collapse:collapse; background:#fff; border-radius:0.75rem; overflow:hidden;"><thead><tr style="background:#f1f5f9;"><th style="padding:0.75rem; text-align:center;">Fecha</th><th style="text-align:center;">Hora</th><th style="text-align:center;">Nuevas</th><th style="text-align:center;">Omitidas</th></tr></thead><tbody id="loadHistory"></tbody></table>
                <!-- ZONA DE CARGA PLANILLA MANTENCIÓN -->
                <div style="margin-top:3rem; padding-top:2rem; border-top:2px solid #e2e8f0;">
                    <h3 style="margin-bottom:0.5rem; color:#1e293b;">📥 Cargar Planilla de Mantención (NEW BD)</h3>
                    <p style="font-size:0.9rem; color:#64748b; margin-bottom:1rem;">
                        Sube el archivo CSV exportado de la hoja "NEW BD". Este paso actualiza las HHs planificadas, especialidades y estados técnicos de las OTs ya cargadas desde el SIC.
                    </p>
                    
                    <div class="upload-zone" id="dropZoneMantencion" onclick="document.getElementById('mantencionFile').click()" style="border:2px dashed #cbd5e1; background:#fff; cursor:pointer; transition:all 0.2s;">
                        <input type="file" id="mantencionFile" accept=".csv" style="display:none">
                        <div style="text-align:center; padding:2rem;">
                            <div style="font-size:2rem; margin-bottom:0.5rem;">📄</div>
                            <p style="font-weight:600; color:#334155; margin:0;">Arrastra tu archivo CSV aquí o haz clic para seleccionar</p>
                            <p style="font-size:0.8rem; color:#94a3b8; margin-top:0.5rem;">Solo archivos .csv | Hoja "NEW BD"</p>
                        </div>
                    </div>
                    
                    <!-- Resumen de Carga -->
                    <div id="mantencionSummary" class="summary-box" style="display:none; margin-top:1rem; background:#f8fafc; padding:1rem; border-radius:0.5rem; border:1px solid #e2e8f0;">
                        <h4 style="margin-top:0; font-size:1rem; color:#1e293b;">📋 Resultado de Procesamiento</h4>
                        <div id="mantencionLog" style="font-size:0.9rem; margin:0.5rem 0; white-space:pre-wrap;"></div>
                        <button onclick="location.reload()" class="btn-primary" style="margin-top:1rem; width:100%;">🔄 Ver Dashboard Actualizado</button>
                    </div>
                </div>
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
        <section id="kpis" class="module-section">
            <div style="padding: 2rem; max-width: 1400px; margin: 0 auto;">
                
                <!-- Header -->
                <div style="margin-bottom: 2rem; display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <h2 style="margin:0; color:#1e293b;">Panel de Control Operativo</h2>
                        <p style="color:#64748b; margin-top:0.5rem;">Análisis en tiempo real de la ejecución de mantenimientos.</p>
                    </div>
                    <button onclick="loadKpis()" style="background:#3b82f6; color:white; border:none; padding:0.5rem 1rem; border-radius:0.5rem; cursor:pointer;">
                        🔄 Actualizar Datos
                    </button>
                </div>

                <!-- Fichas Superiores (KPIs Globales) -->
                <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:1.5rem; margin-bottom:2rem; overflow-x:auto;">
                    
                    <!-- Ficha 1: SLA Cumplido -->
                    <div style="background:white; padding:1.5rem; border-radius:1rem; box-shadow:0 4px 6px -1px rgba(0,0,0,0.1); border-left:4px solid #10b981;">
                        <div style="font-size:0.85rem; color:#64748b; font-weight:600; text-transform:uppercase;">SLA Cumplido</div>
                        <div id="kpi-sla" style="font-size:2rem; font-weight:700; color:#1e293b; margin-top:0.5rem;">--%</div>
                        <div style="font-size:0.8rem; color:#10b981; margin-top:0.25rem;">✅ De OTs completadas a tiempo</div>
                    </div>

                    <!-- Ficha 2: HHs Plan vs Real -->
                    <div style="background:white; padding:1.5rem; border-radius:1rem; box-shadow:0 4px 6px -1px rgba(0,0,0,0.1); border-left:4px solid #3b82f6;">
                        <div style="font-size:0.85rem; color:#64748b; font-weight:600; text-transform:uppercase;">HHs Ejecutadas</div>
                        <div style="display:flex; align-items:baseline; gap:0.5rem; margin-top:0.5rem;">
                            <span id="kpi-hh-real" style="font-size:2rem; font-weight:700; color:#1e293b;">--</span>
                            <span style="font-size:0.9rem; color:#64748b;">/ <span id="kpi-hh-plan">--</span> hh plan</span>
                        </div>
                        <div id="kpi-hh-bar" style="height:6px; background:#e2e8f0; border-radius:3px; margin-top:0.75rem; overflow:hidden;">
                            <div id="kpi-hh-progress" style="width:0%; height:100%; background:#3b82f6; transition:width 1s;"></div>
                        </div>
                    </div>

                    <!-- Ficha 3: OTs Cerradas -->
                    <div style="background:white; padding:1.5rem; border-radius:1rem; box-shadow:0 4px 6px -1px rgba(0,0,0,0.1); border-left:4px solid #8b5cf6;">
                        <div style="font-size:0.85rem; color:#64748b; font-weight:600; text-transform:uppercase;">OTs Cerradas</div>
                        <div id="kpi-ots-closed" style="font-size:2rem; font-weight:700; color:#1e293b; margin-top:0.5rem;">--</div>
                        <div style="font-size:0.8rem; color:#8b5cf6; margin-top:0.25rem;">📦 Completadas en el periodo</div>
                    </div>

                    <!-- Ficha 4: OTs en Riesgo -->
                    <div style="background:white; padding:1.5rem; border-radius:1rem; box-shadow:0 4px 6px -1px rgba(0,0,0,0.1); border-left:4px solid #ef4444;">
                        <div style="font-size:0.85rem; color:#64748b; font-weight:600; text-transform:uppercase;">OTs en Riesgo</div>
                        <div id="kpi-ots-risk" style="font-size:2rem; font-weight:700; color:#1e293b; margin-top:0.5rem;">--</div>
                        <div style="font-size:0.8rem; color:#ef4444; margin-top:0.25rem;">⚠️ Retrasadas > 7 días</div>
                    </div>
                </div>

                <!-- Gráficos -->
                <div style="display:grid; grid-template-columns: 2fr 1fr; gap:1.5rem; margin-bottom:2rem;">
                    
                    <!-- Gráfico Principal: HHs por Especialidad -->
                    <div style="background:white; padding:1.5rem; border-radius:1rem; box-shadow:0 4px 6px -1px rgba(0,0,0,0.1);">
                        <h3 style="margin-top:0; font-size:1.1rem; color:#1e293b;">Horas Hombre por Especialidad</h3>
                        <canvas id="chartEspecialidad" height="250"></canvas>
                    </div>

                    <!-- Gráfico Secundario: Estados -->
                    <div style="background:white; padding:1.5rem; border-radius:1rem; box-shadow:0 4px 6px -1px rgba(0,0,0,0.1);">
                        <h3 style="margin-top:0; font-size:1.1rem; color:#1e293b;">Distribución de Estados</h3>
                        <canvas id="chartEstados" height="250"></canvas>
                    </div>
                </div>

                <!-- Lista Reciente -->
                <div style="background:white; padding:1.5rem; border-radius:1rem; box-shadow:0 4px 6px -1px rgba(0,0,0,0.1);">
                    <h3 style="margin-top:0; font-size:1.1rem; color:#1e293b;">Últimas OTs Procesadas</h3>
                    <div style="overflow-x:auto;">
                        <table style="width:100%; border-collapse:collapse; margin-top:1rem;">
                            <thead>
                                <tr style="background:#f8fafc; border-bottom:2px solid #e2e8f0;">
                                    <th style="padding:0.75rem; text-align:left; font-size:0.85rem; color:#64748b;">Código OT</th>
                                    <th style="padding:0.75rem; text-align:left; font-size:0.85rem; color:#64748b;">Equipo</th>
                                    <th style="padding:0.75rem; text-align:center; font-size:0.85rem; color:#64748b;">Estado</th>
                                    <th style="padding:0.75rem; text-align:center; font-size:0.85rem; color:#64748b;">HH Plan</th>
                                    <th style="padding:0.75rem; text-align:center; font-size:0.85rem; color:#64748b;">HH Real</th>
                                    <th style="padding:0.75rem; text-align:center; font-size:0.85rem; color:#64748b;">Retraso</th>
                                </tr>
                            </thead>
                            <tbody id="tablaOtsRecentes">
                                <!-- Se llena con JS -->
                            </tbody>
                        </table>
                    </div>
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
        <!-- NUEVAS FICHAS PARA EL MOCKUP -->

        <!-- MÓDULO 8: RECURSOS -->
        <section id="recursos" class="module-section">
            <div style="max-width:1200px; margin:0 auto; padding:1rem;">
                <h3 style="margin-bottom:1.5rem; color:#1e293b;">👷 Mantenedor de Recursos</h3>
                
                <!-- Pestañas Modernas -->
                <div style="display:flex; gap:0.5rem; margin-bottom:1.5rem; border-bottom:2px solid #e2e8f0; padding-bottom:0;">
                    <button onclick="showResourceTab('tecnicos')" id="tab-tecnicos" class="resource-tab active">👨‍🔧 Técnicos</button>
                    <button onclick="showResourceTab('grupos')" id="tab-grupos" class="resource-tab">👥 Grupos</button>
                    <button onclick="showResourceTab('turnos')" id="tab-turnos" class="resource-tab">⏱️ Turnos Activos</button>
                </div>

                <!-- BUSCADOR INTELIGENTE -->
                <div style="margin-bottom:1rem; position:relative;">
                    <input type="text" id="searchRecursos" placeholder="🔍 Buscar por nombre, grupo o turno..." 
                        onkeyup="filterResources()"
                        style="width:100%; padding:0.75rem 1rem 0.75rem 2.5rem; border:1px solid #cbd5e1; border-radius:0.5rem; font-size:0.95rem; box-sizing:border-box; transition:border-color 0.2s;"
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
                                <tr style="background:#f8fafc; color:#64748b; font-size:0.85rem; text-transform:uppercase; letter-spacing:0.05em;">
                                    <th style="padding:1rem; text-align:left; border-top-left-radius:0.5rem;">RUT</th>
                                    <th style="padding:1rem; text-align:left;">Nombre Completo</th>
                                    <th style="padding:1rem; text-align:left;">Especialidad</th>
                                    <th style="padding:1rem; text-align:left;">Vertical</th>
                                    <th style="padding:1rem; text-align:left;">Turno</th>
                                    <th style="padding:1rem; text-align:left;">Contacto</th>
                                    <th style="padding:1rem; text-align:center; border-top-right-radius:0.5rem;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="tablaTecnicosBody">
                                <tr><td colspan="7" style="text-align:center; padding:2rem; color:#94a3b8;">Cargando...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- CONTENEDOR GRUPOS -->
                <div id="view-grupos" style="display:none;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                        <p style="color:#64748b; font-size:0.9rem;">Gestión de grupos de trabajo y asignación vertical.</p>
                        <button onclick="openModal('grupo')" class="btn-primary">➕ Nuevo Grupo</button>
                    </div>
                    <div class="card" style="padding:0; overflow-x:auto;">
                        <table style="width:100%; border-collapse:separate; border-spacing:0 0.5rem;">
                            <thead>
                                <tr style="background:#f8fafc; color:#64748b; font-size:0.85rem; text-transform:uppercase; letter-spacing:0.05em;">
                                    <th style="padding:1rem; text-align:left; border-top-left-radius:0.5rem;">Nombre Grupo</th>
                                    <th style="padding:1rem; text-align:left;">Vertical Asociada</th>
                                    <th style="padding:1rem; text-align:left;">Turno Asignado</th>
                                    <th style="padding:1rem; text-align:left;">Descripción</th>
                                    <th style="padding:1rem; text-align:center; border-top-right-radius:0.5rem;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="tablaGruposBody">
                                <tr><td colspan="5" style="text-align:center; padding:2rem; color:#94a3b8;">Cargando...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- CONTENEDOR TURNOS ACTIVOS -->
                <div id="view-turnos" style="display:none;">
                    <div style="margin-bottom:1rem;">
                        <p style="color:#64748b; font-size:0.9rem;">Lista unificada de todos los recursos con turno activo.</p>
                    </div>
                    <div class="card" style="padding:0; overflow-x:auto;">
                        <table style="width:100%; border-collapse:separate; border-spacing:0 0.5rem;">
                            <thead>
                                <tr style="background:#f8fafc; color:#64748b; font-size:0.85rem; text-transform:uppercase; letter-spacing:0.05em;">
                                    <th style="padding:1rem; text-align:left; border-top-left-radius:0.5rem;">Tipo</th>
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
            </div>
        </section>

        <!-- MODAL GENÉRICO PARA TÉCNICO / GRUPO -->
        <div id="modalRecursos" style="display:none; position:fixed; inset:0; background:rgba(15, 23, 42, 0.6); backdrop-filter:blur(4px); z-index:2000; justify-content:center; align-items:center; padding:1rem;">
            <div style="background:#fff; padding:0; border-radius:1rem; width:90%; max-width:550px; box-shadow:0 25px 50px -12px rgba(0, 0, 0, 0.25); overflow:hidden; animation: slideIn 0.3s ease-out;">
                
                <!-- Header Modal -->
                <div style="padding:1.25rem 1.5rem; border-bottom:1px solid #e2e8f0; background:#f8fafc; display:flex; justify-content:space-between; align-items:center;">
                    <h3 id="tituloModalRecursos" style="margin:0; font-size:1.1rem; font-weight:600; color:#1e293b;">Nuevo Registro</h3>
                    <button onclick="closeModal()" style="background:none; border:none; font-size:1.5rem; color:#94a3b8; cursor:pointer; line-height:1; transition:color 0.2s;" onmouseover="this.style.color='#ef4444'" onmouseout="this.style.color='#94a3b8'">&times;</button>
                </div>

                <!-- Formulario -->
                <form id="formRecursos" onsubmit="saveResource(event)" autocomplete="off" style="padding:1.5rem;">
                    <input type="hidden" id="res_type" value="">
                    <input type="hidden" id="res_id" value="">

                    <!-- CAMPOS TÉCNICO -->
                    <div id="fields-tecnico" style="display:none;">
                        <div style="margin-bottom:1rem;">
                            <label style="display:block; font-size:0.85rem; font-weight:600; color:#475569; margin-bottom:0.5rem;">RUT *</label>
                            <input type="text" id="res_rut" placeholder="12.345.678-9" style="width:100%; padding:0.75rem; border:1px solid #cbd5e1; border-radius:0.5rem; font-size:0.95rem; transition:border-color 0.2s;" onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#cbd5e1'">
                        </div>
                        
                        <div style="margin-bottom:1rem;">
                            <label style="display:block; font-size:0.85rem; font-weight:600; color:#475569; margin-bottom:0.5rem;">Nombre Completo *</label>
                            <input type="text" id="res_nombre" placeholder="Nombre Apellido" style="width:100%; padding:0.75rem; border:1px solid #cbd5e1; border-radius:0.5rem; font-size:0.95rem; transition:border-color 0.2s;" onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#cbd5e1'">
                        </div>

                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1rem;">
                            <div>
                                <label style="display:block; font-size:0.85rem; font-weight:600; color:#475569; margin-bottom:0.5rem;">Vertical</label>
                                <select id="res_vertical_tecnico" style="width:100%; padding:0.75rem; border:1px solid #cbd5e1; border-radius:0.5rem; background:#fff; color:#1e293b; font-size:0.95rem;">
                                    <option value="">Seleccionar...</option>
                                </select>
                            </div>
                            <div>
                                <label style="display:block; font-size:0.85rem; font-weight:600; color:#475569; margin-bottom:0.5rem;">Especialidad</label>
                                <select id="res_especialidad" style="width:100%; padding:0.75rem; border:1px solid #cbd5e1; border-radius:0.5rem; background:#fff; color:#1e293b; font-size:0.95rem;">
                                    <option value="">Seleccionar...</option>
                                </select>
                            </div>
                        </div>

                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1rem;">
                            <div>
                                <label style="display:block; font-size:0.85rem; font-weight:600; color:#475569; margin-bottom:0.5rem;">Email</label>
                                <input type="email" id="res_correo" style="width:100%; padding:0.75rem; border:1px solid #cbd5e1; border-radius:0.5rem; font-size:0.95rem;">
                            </div>
                            <div>
                                <label style="display:block; font-size:0.85rem; font-weight:600; color:#475569; margin-bottom:0.5rem;">Teléfono</label>
                                <input type="text" id="res_telefono" style="width:100%; padding:0.75rem; border:1px solid #cbd5e1; border-radius:0.5rem; font-size:0.95rem;">
                            </div>
                        </div>
                    </div>

                    <!-- CAMPOS GRUPO -->
                    <div id="fields-grupo" style="display:none;">
                        <div style="margin-bottom:1rem;">
                            <label style="display:block; font-size:0.85rem; font-weight:600; color:#475569; margin-bottom:0.5rem;">Nombre del Grupo *</label>
                            <input type="text" id="res_nombre_grupo" placeholder="Ej: Equipo A Climatización" style="width:100%; padding:0.75rem; border:1px solid #cbd5e1; border-radius:0.5rem; font-size:0.95rem;">
                        </div>
                        
                        <div style="margin-bottom:1rem;">
                            <label style="display:block; font-size:0.85rem; font-weight:600; color:#475569; margin-bottom:0.5rem;">Vertical Asociada</label>
                            <select id="res_vertical_grupo" style="width:100%; padding:0.75rem; border:1px solid #cbd5e1; border-radius:0.5rem; background:#fff; color:#1e293b; font-size:0.95rem;">
                                <option value="">Ninguna</option>
                            </select>
                        </div>

                        <div style="margin-bottom:1rem;">
                            <label style="display:block; font-size:0.85rem; font-weight:600; color:#475569; margin-bottom:0.5rem;">Descripción</label>
                            <textarea id="res_desc" rows="3" style="width:100%; padding:0.75rem; border:1px solid #cbd5e1; border-radius:0.5rem; font-size:0.95rem; resize:vertical;"></textarea>
                        </div>
                    </div>

                    <!-- CAMPO COMÚN: TURNO -->
                    <div style="margin-bottom:1.5rem;">
                        <label style="display:block; font-size:0.85rem; font-weight:600; color:#475569; margin-bottom:0.5rem;">Tipo de Turno</label>
                        <select id="res_turno" style="width:100%; padding:0.75rem; border:1px solid #cbd5e1; border-radius:0.5rem; background:#fff; color:#1e293b; font-size:0.95rem;">
                            <option value="">Sin Turno Asignado</option>
                        </select>
                    </div>

                    <div style="display:flex; gap:0.75rem; justify-content:flex-end; padding-top:1rem; border-top:1px solid #f1f5f9;">
                        <button type="button" onclick="closeModal()" style="padding:0.6rem 1.2rem; border:1px solid #e2e8f0; border-radius:0.5rem; background:#fff; cursor:pointer; font-weight:500; color:#475569; transition:background 0.2s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='#fff'">Cancelar</button>
                        <button type="submit" style="padding:0.6rem 1.2rem; border:none; border-radius:0.5rem; background:var(--primary); color:#fff; cursor:pointer; font-weight:600; transition:opacity 0.2s;" onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">💾 Guardar</button>
                    </div>
                </form>
            </div>
        </div>

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
        let currentFilters = { page: 1, search: '', esp: '', estado: '', mes: '' };
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

let currentResourceType = 'tecnico'; 

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
                
                <!-- AMPLIAR COLUMNA ESPECIALIDAD -->
                <td style="padding:1rem; border-bottom:1px solid #f1f5f9; min-width:180px;">
                    ${t.especialidad_nombre ? `<span class="badge b-pen">${t.especialidad_nombre}</span>` : '<span style="color:#cbd5e1;">-</span>'}
                </td>
                
                <td style="padding:1rem; border-bottom:1px solid #f1f5f9; color:#475569;">${t.nombre_vertical || '-'}</td>
                <td style="padding:1rem; border-bottom:1px solid #f1f5f9;">${t.turno_actual || '<span style="color:#94a3b8; font-style:italic;">Sin turno</span>'}</td>
                <td style="padding:1rem; border-bottom:1px solid #f1f5f9; font-size:0.9rem; color:#64748b;">
                    ${t.correo ? `<div>📧 ${t.correo}</div>` : ''}
                    ${t.telefono ? `<div>📱 ${t.telefono}</div>` : ''}
                    ${(!t.correo && !t.telefono) ? '-' : ''}
                </td>
                <td style="padding:1rem; border-bottom:1px solid #f1f5f9; text-align:center;">
                    <button onclick="editResource('tecnico', ${JSON.stringify(t).replace(/"/g, '&quot;')})" title="Editar" style="cursor:pointer; margin-right:5px;">✏️</button>
                    <button onclick="deleteResource('tecnico', ${t.id}, '${t.nombre}')" title="Eliminar" style="cursor:pointer; color:#ef4444;">🗑️</button>
                </td>
            </tr>
        `).join('');
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; color:#ef4444; padding:2rem;">❌ Error: ${err.message}</td></tr>`;
    }
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

async function openModal(type, item = null) {
    currentResourceType = type;
    const modal = document.getElementById('modalRecursos');
    const form = document.getElementById('formRecursos');
    
    form.reset();
    document.getElementById('res_id').value = '';
    document.getElementById('res_type').value = type;
    
    document.getElementById('fields-tecnico').style.display = type === 'tecnico' ? 'block' : 'none';
    document.getElementById('fields-grupo').style.display = type === 'grupo' ? 'block' : 'none';
    
    document.getElementById('tituloModalRecursos').textContent = item ? `Editar ${type === 'tecnico' ? 'Técnico' : 'Grupo'}` : `Nuevo ${type === 'tecnico' ? 'Técnico' : 'Grupo'}`;

    // 1. Primero cargamos los selects (esto limpia las opciones anteriores)
    await loadSelects();

    // 2. Luego prellenamos los datos SI hay un item (edición)
    if (item) {
        if (type === 'tecnico') {
            document.getElementById('res_rut').value = item.rut || '';
            document.getElementById('res_nombre').value = item.nombre || '';
            
            // Asignar IDs numéricos explícitamente
            if (item.id_especialidad) document.getElementById('res_especialidad').value = item.id_especialidad;
            if (item.id_vertical) document.getElementById('res_vertical_tecnico').value = item.id_vertical;
            if (item.id_tipo_turno) document.getElementById('res_turno').value = item.id_tipo_turno;
            
            document.getElementById('res_correo').value = item.correo || '';
            document.getElementById('res_telefono').value = item.telefono || '';
            document.getElementById('res_id').value = item.id;
        } else {
            document.getElementById('res_nombre_grupo').value = item.nombre_grupo || '';
            
            if (item.id_vertical) document.getElementById('res_vertical_grupo').value = item.id_vertical;
            if (item.id_tipo_turno) document.getElementById('res_turno').value = item.id_tipo_turno;
            
            document.getElementById('res_desc').value = item.descripcion || '';
            document.getElementById('res_id').value = item.id;
        }
    }

    modal.style.display = 'flex';
}

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
// === MÓDULO 4: KPIs LOGIC ===

let chartEsp = null;
let chartEstados = null;

async function loadKpis() {
    try {
        // 1. Cargar KPIs Globales
        const resGlobal = await fetch('/api/kpis.php?action=global');
        const dataGlobal = await resGlobal.json();
        
        if (dataGlobal.success) {
            const d = dataGlobal.data;
            document.getElementById('kpi-sla').textContent = d.sla_percent + '%';
            document.getElementById('kpi-hh-real').textContent = d.hh_real.toFixed(1);
            document.getElementById('kpi-hh-plan').textContent = d.hh_plan.toFixed(1);
            document.getElementById('kpi-ots-closed').textContent = '--'; // Calcularíamos si tuviéramos ese dato específico, por ahora usamos total_ots como referencia o lo dejamos vacío
            document.getElementById('kpi-ots-risk').textContent = d.ots_riesgo;

            // Barra de progreso HH
            const percent = d.hh_plan > 0 ? (d.hh_real / d.hh_plan) * 100 : 0;
            document.getElementById('kpi-hh-progress').style.width = Math.min(percent, 100) + '%';
        }

        // 2. Cargar Datos para Gráficos
        const resChart = await fetch('/api/kpis.php?action=chart_data&group_by=especialidad');
        const dataChart = await resChart.json();

        if (dataChart.success) {
            renderCharts(dataChart.data);
        }

        // 3. Cargar Tabla Reciente (Simulado con los primeros 10 items del gráfico por ahora, idealmente otra API call)
        renderRecentTable(dataChart.data.slice(0, 10));

    } catch (err) {
        console.error(err);
        Toast.error('Error al cargar KPIs');
    }
}

function renderCharts(data) {
    const labels = data.map(d => d.label || 'Sin Espec.');
    const hhPlan = data.map(d => parseFloat(d.hh_plan) || 0);
    const hhReal = data.map(d => parseFloat(d.hh_real) || 0);

    // Destruir gráficos anteriores si existen
    if (chartEsp) chartEsp.destroy();
    if (chartEstados) chartEstados.destroy();

    // Gráfico de Barras: Especialidades
    const ctxEsp = document.getElementById('chartEspecialidad').getContext('2d');
    chartEsp = new Chart(ctxEsp, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'HH Planificadas',
                    data: hhPlan,
                    backgroundColor: '#cbd5e1',
                    borderRadius: 4
                },
                {
                    label: 'HH Reales',
                    data: hhReal,
                    backgroundColor: '#3b82f6',
                    borderRadius: 4
                }
            ]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'top' } },
            scales: { y: { beginAtZero: true } }
        }
    });

    // Gráfico Circular: Estados (Simulado basado en datos globales o extraído de otra query)
    // Para simplificar, usaremos una distribución ficticia basada en los estados comunes
    const ctxEstados = document.getElementById('chartEstados').getContext('2d');
    chartEstados = new Chart(ctxEstados, {
        type: 'doughnut',
        data: {
            labels: ['Completada', 'En Ejecución', 'Pendiente', 'Reprogramada'],
            datasets: [{
                data: [60, 20, 10, 10], // Estos valores deberían venir de una API específica de estados
                backgroundColor: ['#10b981', '#3b82f6', '#f59e0b', '#8b5cf6']
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'bottom' } }
        }
    });
}

function renderRecentTable(data) {
    const tbody = document.getElementById('tablaOtsRecentes');
    
    // Si no hay datos, mostrar mensaje
    if (!data || data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:1rem; color:#94a3b8;">No hay datos recientes</td></tr>';
        return;
    }

    tbody.innerHTML = data.map(item => {
        // Conversión segura a número, si falla usa 0
        const hhPlan = parseFloat(item.hh_plan) || 0;
        const hhReal = parseFloat(item.hh_real) || 0;
        
        // Formatear a 1 decimal
        const hhPlanFormatted = hhPlan.toFixed(1);
        const hhRealFormatted = hhReal.toFixed(1);

        return `
            <tr style="border-bottom:1px solid #f1f5f9; transition:background 0.2s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='white'">
                <td style="padding:0.75rem; font-weight:600; color:#1e293b;">${item.label || 'N/A'}</td>
                <td style="padding:0.75rem; color:#64748b;">Varios Equipos</td>
                <td style="padding:0.75rem; text-align:center;">
                    <span style="background:#dbeafe; color:#1e40af; padding:0.25rem 0.5rem; border-radius:99px; font-size:0.75rem;">Mixto</span>
                </td>
                <td style="padding:0.75rem; text-align:center; color:#64748b; font-family:monospace;">${hhPlanFormatted}</td>
                <td style="padding:0.75rem; text-align:center; color:#64748b; font-family:monospace;">${hhRealFormatted}</td>
                <td style="padding:0.75rem; text-align:center; color:#ef4444;">--</td>
            </tr>
        `;
    }).join('');
}

// Cargar al inicio
document.addEventListener('DOMContentLoaded', () => {
    loadKpis();
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
    // Validación básica
    if (!file.name.endsWith('.csv')) {
        alert('Por favor selecciona un archivo CSV válido.');
        return;
    }

    const formData = new FormData();
    formData.append('mantencion_file', file);

    try {
        // Mostrar estado de carga
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
</body>
</html>