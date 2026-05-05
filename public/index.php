<?php
/**
 * MedicalOT - Dashboard Principal
 */

define('APP_ENTRY_POINT', true);
require_once __DIR__ . '/../includes/layout.php';

requireLogin();
$user = getCurrentUser();

// Determinar módulo inicial según rol
$moduleMap = [
    'admin_hosp' => 'Panel de Control - Admin Hospital',
    'admin_cont' => 'Panel de Control - Admin Contratista',
    'tecnico' => 'Mis Órdenes de Trabajo'
];

$moduleName = $moduleMap[$user['role']] ?? 'MedicalOT';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MedicalOT - Dashboard</title>
    <?php includeCSS(); ?>
    <link rel="icon" type="image/png" href="/img/logohospitalantofagasta.jpeg">
</head>
<body>
    <?php renderHeader($moduleName); ?>
    
    <main style="padding: 2rem; max-width: 1400px; margin: 0 auto;">
        <!-- Bienvenida -->
        <div class="card" style="margin-bottom: 2rem;">
            <div class="card-body" style="text-align: center; padding: 3rem;">
                <img src="/img/logohospitalantofagasta.jpeg" alt="Hospital" style="width: 80px; height: 80px; object-fit: contain; margin-bottom: 1rem; opacity: 0.8;">
                <h2 style="font-size: 2rem; color: var(--primary-dark); margin-bottom: 0.5rem;">
                    Bienvenido a MedicalOT
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
        
        <!-- Accesos rápidos según rol -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
            <div class="card" style="cursor: pointer; transition: var(--transition);" onclick="location.href='/modules/ots.php'">
                <div class="card-body" style="text-align: center; padding: 2rem;">
                    <div style="width: 60px; height: 60px; background: var(--primary); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; font-size: 1.5rem;">
                        📋
                    </div>
                    <h3 style="color: var(--primary-dark); margin-bottom: 0.5rem;">Órdenes de Trabajo</h3>
                    <p style="color: var(--gray-600); font-size: 0.9rem;">Gestionar y visualizar OTs</p>
                </div>
            </div>
            
            <div class="card" style="cursor: pointer; transition: var(--transition);" onclick="location.href='/modules/asignacion.php'">
                <div class="card-body" style="text-align: center; padding: 2rem;">
                    <div style="width: 60px; height: 60px; background: var(--secondary); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; font-size: 1.5rem;">
                        👥
                    </div>
                    <h3 style="color: var(--primary-dark); margin-bottom: 0.5rem;">Asignación</h3>
                    <p style="color: var(--gray-600); font-size: 0.9rem;">Asignar técnicos y grupos</p>
                </div>
            </div>
            
            <div class="card" style="cursor: pointer; transition: var(--transition);" onclick="location.href='/modules/reportes.php'">
                <div class="card-body" style="text-align: center; padding: 2rem;">
                    <div style="width: 60px; height: 60px; background: var(--success); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; font-size: 1.5rem;">
                        📊
                    </div>
                    <h3 style="color: var(--primary-dark); margin-bottom: 0.5rem;">Reportes</h3>
                    <p style="color: var(--gray-600); font-size: 0.9rem;">KPIs y estadísticas</p>
                </div>
            </div>
        </div>
    </main>
    
    <?php includeJS(); ?>
    
    <script>
        // Demo de notificaciones
        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => {
                Toast.success('Sistema inicializado correctamente', 'Bienvenido');
            }, 500);
        });
    </script>
</body>
</html>