<?php
/**
 * MedicalOT - Layout Base
 * Incluye header, scripts y estilos comunes
 */

if (!defined('APP_ENTRY_POINT')) {
    define('APP_ENTRY_POINT', true);
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/session.php';

// Verificar autenticación
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: /login.php');
        exit;
    }
}

// Obtener datos del usuario actual
function getCurrentUser() {
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'name' => $_SESSION['user_name'] ?? 'Usuario',
        'email' => $_SESSION['user_email'] ?? '',
        'role' => $_SESSION['user_role'] ?? 'user',
        'role_name' => $_SESSION['role_name'] ?? 'Usuario'
    ];
}

// Renderizar header
function renderHeader($moduleName = 'MedicalOT') {
    $user = getCurrentUser();
    ?>
    <div class="app-background"></div>
    
    <header class="main-header">
        <div class="header-left">
            <img src="/img/logohospitalantofagasta.jpeg" alt="Hospital Antofagasta" class="header-logo">
            <div class="header-module">
                <div class="header-module-title"><?php echo htmlspecialchars($moduleName); ?></div>
                <div class="header-role"><?php echo htmlspecialchars($user['role_name']); ?></div>
            </div>
        </div>
        
        <div class="header-right">
            <div class="header-datetime">
                <div style="font-weight: 600; color: var(--gray-800);"><?php echo updateDateTime(); ?></div>
                <div style="font-size: 0.75rem;">Sistema MedicalOT</div>
            </div>
            
            <div class="header-user">
                <div class="user-avatar"></div>
                <span><?php echo htmlspecialchars($user['name']); ?></span>
            </div>
            
            <div class="menu-dots">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path>
                </svg>
                
                <div class="dropdown-menu">
                    <a href="/profile.php" class="dropdown-item">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                        Mi Perfil
                    </a>
                    <a href="/settings.php" class="dropdown-item">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        Configuración
                    </a>
                    <div class="dropdown-divider"></div>
                    <button onclick="logout()" class="dropdown-item">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                        </svg>
                        Cerrar Sesión
                    </button>
                </div>
            </div>
        </div>
    </header>
    
    <script>
        // Inicializar header con datos del usuario
        initHeader(
            '<?php echo addslashes($moduleName); ?>',
            '<?php echo addslashes($user['role_name']); ?>',
            '<?php echo addslashes($user['name']); ?>'
        );
    </script>
    <?php
}

// Incluir CSS
function includeCSS() {
    echo '<link rel="stylesheet" href="/css/medicalot.css">';
}

// Incluir JS
function includeJS() {
    echo '<script src="/js/app.js"></script>';
}

// Helper para datetime
function updateDateTime() {
    $now = new DateTime();
    $now->setTimezone(new DateTimeZone('America/Santiago'));
    return $now->format('l, d \d\e F \d\e Y - H:i');
}
?>