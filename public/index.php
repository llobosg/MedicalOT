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
                <?php if($isAdmin || $user['role'] === 'admin_cont'): ?>
                   <div class="home-card" onclick="showModule('presentacion')">
                        <div class="icon-3d-container" style="background:transparent; box-shadow:none; border:none;">
                            <img src="/img/icons/presentacion.png" alt="Carga SIC" style="width:50px; height:50px; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.1));">
                        </div>
                        <div class="home-card-title">Presentación</div>
                        <div class="home-card-desc">KPIs de carga y disponibilidad</div>
                    </div>   
                <?php endif; ?>
            </div>
        </section>

        <?php if($isAdmin): ?>
        <section id="carga-sic" class="module-section">
            <div style="max-width:800px; margin:0 auto;">
                <h3 style="margin-bottom:1rem;">Carga y Validación SIC</h3>
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

        <section id="kpis" class="module-section">
            <div style="max-width:900px; margin:0 auto;">
                <h3 style="margin-bottom:1rem;">Indicadores de Gestión</h3>
                <div class="kpi-grid"><div class="kpi-card"><div style="font-size:0.8rem; color:#64748b;">SLA Cumplimiento</div><div class="kpi-val" style="color:#10b981;">94%</div></div><div class="kpi-card"><div style="font-size:0.8rem; color:#64748b;">HH Presup/Real</div><div class="kpi-val" style="color:#f59e0b;">102%</div></div><div class="kpi-card"><div style="font-size:0.8rem; color:#64748b;">OTs Cerradas/Mes</div><div class="kpi-val">47</div></div><div class="kpi-card"><div style="font-size:0.8rem; color:#64748b;">En Proceso</div><div class="kpi-val" style="color:var(--primary);">12</div></div></div>
                <h4 style="margin-bottom:0.5rem;">Distribución de HH por Categoría</h4>
                <div class="pills-container"><button class="pill-btn active" onclick="updateKPIs(this, 'Especialidad')">Especialidad</button><button class="pill-btn" onclick="updateKPIs(this, 'Área')">Área</button><button class="pill-btn" onclick="updateKPIs(this, 'Equipo')">Equipo</button></div>
                <div class="progress-container"><div class="progress-bar" id="kpiBar" style="width:65%;"></div></div>
                <div class="top-list" id="topList"></div>
            </div>
        </section>

        <!-- MÓDULO 7: VERTICALES -->
        <?php 
            // 1. Leer el rol desde la clave CORRECTA de la sesión (recinto_rol)
            $rolActual = $_SESSION['recinto_rol'] ?? '';
            
            // 2. Definir si es Admin Hospital (o admin general) ANTES de usarlo en HTML
            $esAdmin = ($rolActual === 'admin_hospital' || $rolActual === 'admin');
        ?>

        <!-- DEBUG VERTICALES -->
        <div style="background:#fff3cd; padding:10px; border:1px solid #ffeeba; color:#856404; margin-bottom:1rem;">
            <strong>🔍 Debug Sesión:</strong><br>
            <ul style="margin:0; padding-left:20px;">
                <?php 
                    // Forzar inicio de sesión si no existe
                    if (session_status() === PHP_SESSION_NONE) session_start();
                    
                    $rolRecinto = $_SESSION['recinto_rol'] ?? 'NO DEFINIDO';
                    $rolGeneral = $_SESSION['rol'] ?? 'NO DEFINIDO';
                    $userId = $_SESSION['user_id'] ?? 'NO DEFINIDO';
                    
                    // Lógica de permisos flexible
                    $esAdmin = ($rolRecinto === 'admin' || $rolRecinto === 'admin_hospital' || 
                                $rolGeneral === 'admin' || $rolGeneral === 'admin_hospital');
                ?>
                <li>User ID: <?= htmlspecialchars($userId) ?></li>
                <li>Sesión recinto_rol: <?= htmlspecialchars($rolRecinto) ?></li>
                <li>Sesión rol (general): <?= htmlspecialchars($rolGeneral) ?></li>
                <li>¿Es Admin?: <?= $esAdmin ? 'SÍ ✅' : 'NO ❌' ?></li>
                <li>Todas las keys de sesión: <?= implode(', ', array_keys($_SESSION)) ?></li>
            </ul>
        </div>
        <!-- FIN DEBUG -->

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
                        // Definir permisos flexibles (acepta ambos nombres de sesión)
                        $rolRecinto = $_SESSION['recinto_rol'] ?? '';
                        $rolGeneral = $_SESSION['rol'] ?? '';
                        
                        $esAdmin = ($rolRecinto === 'admin' || $rolRecinto === 'admin_hospital' || 
                                    $rolGeneral === 'admin' || $rolGeneral === 'admin_hospital');
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

        <!-- MÓDULO 1: RECURSOS -->
        <section id="recursos" class="module-section">
            <div style="max-width:1000px; margin:0 auto;">
                <h3 style="margin-bottom:1rem;">Mantenedor de Recursos</h3>
                <div style="display:flex; gap:1rem; margin-bottom:1rem;">
                    <button onclick="alert('Modal Nuevo Técnico')" style="background:var(--primary); color:white; padding:0.5rem 1rem; border-radius:0.5rem; border:none; cursor:pointer;">➕ Nuevo Técnico</button>
                    <button onclick="alert('Modal Nuevo Grupo')" style="background:#64748b; color:white; padding:0.5rem 1rem; border-radius:0.5rem; border:none; cursor:pointer;">➕ Nuevo Grupo</button>
                </div>
                
                <!-- Tabla de Técnicos -->
                <table style="width:100%; background:#fff; border-radius:0.75rem; overflow:hidden; border-collapse:collapse; box-shadow:var(--shadow);">
                    <thead><tr style="background:#f1f5f9;"><th style="padding:0.75rem; text-align:left;">Técnico</th><th>RUT</th><th>Especialidad</th><th>Grupo</th><th>Turno</th><th>Estado</th><th>Acciones</th></tr></thead>
                    <tbody>
                        <tr><td style="padding:0.75rem;">Juan Pérez</td><td>12.345.678-9</td><td>M-CLIMATIZACION</td><td>Pool ClimA</td><td>2x2 Mañana</td><td><span class="badge b-pro">Activo</span></td><td><button style="cursor:pointer;">✏️</button></td></tr>
                        <tr><td style="padding:0.75rem;">Maria González</td><td>11.222.333-4</td><td>M-ELECTROMECANICA</td><td>Pool ElecB</td><td>5x2 Tarde</td><td><span class="badge b-pen">Vacaciones</span></td><td><button style="cursor:pointer;">✏️</button></td></tr>
                        <tr><td style="padding:0.75rem;">Carlos Ruiz</td><td>15.678.901-K</td><td>M-CLIMATIZACION</td><td>Pool ClimA</td><td>2x2 Noche</td><td><span class="badge b-cer">Licencia Médica</span></td><td><button style="cursor:pointer;">✏️</button></td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- MÓDULO 2: PLANIFICACIÓN (Calendario Hotelero) -->
        <section id="planificacion" class="module-section">
            <div style="height:100%; display:flex; flex-direction:column;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                    <h3 style="margin:0;">Planificación Semanal (Semana 19)</h3>
                    <div style="display:flex; gap:0.5rem;">
                        <button onclick="changeWeek(-1)" style="padding:0.4rem 0.8rem; border:1px solid #e2e8f0; border-radius:0.5rem; background:#fff; cursor:pointer;">◀ Anterior</button>
                        <button onclick="changeWeek(1)" style="padding:0.4rem 0.8rem; border:1px solid #e2e8f0; border-radius:0.5rem; background:#fff; cursor:pointer;">Siguiente ▶</button>
                    </div>
                </div>
                
                <!-- Contenedor del Calendario Scrollable -->
                <div style="flex:1; overflow:auto; background:#fff; border:1px solid #e2e8f0; border-radius:0.75rem; position:relative;">
                    <div id="calendarGrid" style="display:grid; grid-template-columns: 150px repeat(7, 1fr); min-height:100%;">
                        <!-- Header Días -->
                        <div style="position:sticky; top:0; left:0; z-index:10; background:#f1f5f9; padding:0.75rem; font-weight:600; border-bottom:2px solid #e2e8f0; border-right:2px solid #e2e8f0;">Recurso</div>
                        <div style="position:sticky; top:0; z-index:9; background:#f1f5f9; padding:0.75rem; font-weight:600; text-align:center; border-bottom:2px solid #e2e8f0; border-right:1px solid #e2e8f0;">Lunes</div>
                        <div style="position:sticky; top:0; z-index:9; background:#f1f5f9; padding:0.75rem; font-weight:600; text-align:center; border-bottom:2px solid #e2e8f0; border-right:1px solid #e2e8f0;">Martes</div>
                        <div style="position:sticky; top:0; z-index:9; background:#f1f5f9; padding:0.75rem; font-weight:600; text-align:center; border-bottom:2px solid #e2e8f0; border-right:1px solid #e2e8f0;">Miércoles</div>
                        <div style="position:sticky; top:0; z-index:9; background:#f1f5f9; padding:0.75rem; font-weight:600; text-align:center; border-bottom:2px solid #e2e8f0; border-right:1px solid #e2e8f0;">Jueves</div>
                        <div style="position:sticky; top:0; z-index:9; background:#f1f5f9; padding:0.75rem; font-weight:600; text-align:center; border-bottom:2px solid #e2e8f0; border-right:1px solid #e2e8f0;">Viernes</div>
                        <div style="position:sticky; top:0; z-index:9; background:#f1f5f9; padding:0.75rem; font-weight:600; text-align:center; border-bottom:2px solid #e2e8f0; border-right:1px solid #e2e8f0;">Sábado</div>
                        <div style="position:sticky; top:0; z-index:9; background:#f1f5f9; padding:0.75rem; font-weight:600; text-align:center; border-bottom:2px solid #e2e8f0; border-right:1px solid #e2e8f0;">Domingo</div>

                        <!-- Fila Grupo 1 -->
                        <div style="position:sticky; left:0; z-index:5; background:#fff; padding:0.75rem; border-bottom:1px solid #e2e8f0; border-right:2px solid #e2e8f0; font-weight:600;">Pool ClimA</div>
                        <div class="cal-cell" onclick="openPlanningModal('Pool ClimA', 'Lunes')" style="border-bottom:1px solid #e2e8f0; border-right:1px solid #e2e8f0; min-height:100px;"></div>
                        <div class="cal-cell" onclick="openPlanningModal('Pool ClimA', 'Martes')" style="border-bottom:1px solid #e2e8f0; border-right:1px solid #e2e8f0; min-height:100px;"></div>
                        <div class="cal-cell has-event" onclick="showEventDetails()" style="border-bottom:1px solid #e2e8f0; border-right:1px solid #e2e8f0; min-height:100px; background:#dbeafe;">
                            <div style="padding:0.5rem; font-size:0.75rem; color:#1e40af; background:#bfdbfe; border-radius:4px; margin:0.25rem;">OT-2026-001<br>08:00 - 16:00</div>
                        </div>
                        <div class="cal-cell" onclick="openPlanningModal('Pool ClimA', 'Jueves')" style="border-bottom:1px solid #e2e8f0; border-right:1px solid #e2e8f0; min-height:100px;"></div>
                        <div class="cal-cell" onclick="openPlanningModal('Pool ClimA', 'Viernes')" style="border-bottom:1px solid #e2e8f0; border-right:1px solid #e2e8f0; min-height:100px;"></div>
                        <div class="cal-cell" onclick="openPlanningModal('Pool ClimA', 'Sábado')" style="border-bottom:1px solid #e2e8f0; border-right:1px solid #e2e8f0; min-height:100px;"></div>
                        <div class="cal-cell" onclick="openPlanningModal('Pool ClimA', 'Domingo')" style="border-bottom:1px solid #e2e8f0; border-right:1px solid #e2e8f0; min-height:100px;"></div>

                        <!-- Fila Grupo 2 -->
                        <div style="position:sticky; left:0; z-index:5; background:#fff; padding:0.75rem; border-bottom:1px solid #e2e8f0; border-right:2px solid #e2e8f0; font-weight:600;">Pool ElecB</div>
                        <div class="cal-cell" onclick="openPlanningModal('Pool ElecB', 'Lunes')" style="border-bottom:1px solid #e2e8f0; border-right:1px solid #e2e8f0; min-height:100px;"></div>
                        <div class="cal-cell" onclick="openPlanningModal('Pool ElecB', 'Martes')" style="border-bottom:1px solid #e2e8f0; border-right:1px solid #e2e8f0; min-height:100px;"></div>
                        <div class="cal-cell" onclick="openPlanningModal('Pool ElecB', 'Miércoles')" style="border-bottom:1px solid #e2e8f0; border-right:1px solid #e2e8f0; min-height:100px;"></div>
                        <div class="cal-cell has-event" onclick="showEventDetails()" style="border-bottom:1px solid #e2e8f0; border-right:1px solid #e2e8f0; min-height:100px; background:#dcfce7;">
                            <div style="padding:0.5rem; font-size:0.75rem; color:#166534; background:#bbf7d0; border-radius:4px; margin:0.25rem;">OT-2026-002<br>09:00 - 17:00</div>
                        </div>
                        <div class="cal-cell" onclick="openPlanningModal('Pool ElecB', 'Viernes')" style="border-bottom:1px solid #e2e8f0; border-right:1px solid #e2e8f0; min-height:100px;"></div>
                        <div class="cal-cell" onclick="openPlanningModal('Pool ElecB', 'Sábado')" style="border-bottom:1px solid #e2e8f0; border-right:1px solid #e2e8f0; min-height:100px;"></div>
                        <div class="cal-cell" onclick="openPlanningModal('Pool ElecB', 'Domingo')" style="border-bottom:1px solid #e2e8f0; border-right:1px solid #e2e8f0; min-height:100px;"></div>
                    </div>
                </div>
            </div>
        </section>

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
                const res = await fetch('/api/import_sic.php', { method: 'POST', body: formData }); const rawText = await res.text();
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
document.addEventListener('DOMContentLoaded', () => {
    const homeGrid = document.querySelector('.home-grid');
    if(homeGrid) {
        // Insertar después de la primera tarjeta
        const newCards = `
            <div class="home-card" onclick="showModule('planificacion')">
                <div class="icon-3d-container" style="background:transparent; box-shadow:none; border:none;">
                    <img src="/img/icons/planificacion.png" alt="Carga SIC" style="width:50px; height:50px; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.1));">
                </div>
                <div class="home-card-title">Planificación</div>
                <div class="home-card-desc">Calendario y asignación HH</div>
            </div>
            <div class="home-card" onclick="showModule('verticales')">
                    <div class="icon-3d-container" style="background:transparent; box-shadow:none; border:none;">
                        <img src="/img/icons/verticales.png" alt="Carga SIC" style="width:50px; height:50px; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.1));">
                    </div>
                    <div class="home-card-title">Verticales</div>
                    <div class="home-card-desc">Mantenedor de Verticales</div>
            </div>
            <div class="home-card" onclick="showModule('recursos')">
                <div class="icon-3d-container" style="background:transparent; box-shadow:none; border:none;">
                    <img src="/img/icons/recursos.png" alt="Carga SIC" style="width:50px; height:50px; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.1));">
                </div>
                <div class="home-card-title">Recursos</div>
                <div class="home-card-desc">Técnicos, Grupos y Turnos</div>
            </div>
        `;
        // Insertar al final de la grilla
        homeGrid.insertAdjacentHTML('beforeend', newCards);
    }
});
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
                // Asumiendo que los archivos están accesibles vía web en /uploads/...
                const url = `/${inc.evidencia_path}`;
                const isPdf = inc.evidencia_path.endsWith('.pdf');
                
                if (isPdf) {
                    evidenceHtml = `<a href="${url}" target="_blank" class="inc-evidence">📄 Ver PDF</a>`;
                } else {
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
        const res = await fetch('/api/verticales.php', { method: 'POST', body: formData });
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
    </script>
</body>
</html>