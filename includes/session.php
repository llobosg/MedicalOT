<?php
// includes/session.php
if (session_status() === PHP_SESSION_NONE) {
    // Configuración segura de cookies
    $secure = isset($_SERVER['HTTPS']) || getenv('RAILWAY_ENVIRONMENT') === 'true';
    
    session_set_cookie_params([
        'lifetime' => 28800, // 8 horas
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    
    session_start();
    
    // Regenerar ID cada 15 min para prevenir fijación de sesión
    if (!isset($_SESSION['created'])) {
        $_SESSION['created'] = time();
    } elseif (time() - $_SESSION['created'] > 900) {
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    }
    
    // Timeout de inactividad (30 min)
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
        session_unset();
        session_destroy();
        header('Location: /login.php?timeout=1');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

// Función helper para verificar autenticación
function requireAuth($roles = []) {
    if (!isset($_SESSION['user_id'])) {
        header('Location: /login.php');
        exit;
    }
    if (!empty($roles) && !in_array($_SESSION['role'] ?? '', $roles)) {
        http_response_code(403);
        exit('Acceso no autorizado');
    }
}
?>