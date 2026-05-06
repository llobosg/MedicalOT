<?php
/**
 * MedicalOT - Cierre de Sesión Seguro
 */
session_start();

// 1. Vaciar variables de sesión
$_SESSION = [];

// 2. Destruir cookie de sesión si existe
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. Destruir sesión en servidor
session_destroy();

// 4. Redirigir al login
header('Location: /login.php?logout=1');
exit;