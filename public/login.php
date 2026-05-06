<?php
/**
 * MedicalOT - Login
 * Página de acceso al sistema
 */

define('APP_ENTRY_POINT', true);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/session.php';

// Si ya está logueado, redirigir al dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: /index.php');
    exit;
}

$error = '';

// Procesar login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Por favor ingrese usuario y contraseña';
    } else {
        // Credenciales de prueba (luego se conectará a BD)
        $validUsers = [
            'adminhospital' => ['pass' => '12345', 'role' => 'admin_hosp', 'name' => 'Administrador Hospital'],
            'admicontratista' => ['pass' => '12345', 'role' => 'admin_cont', 'name' => 'Administrador Contratista'],
            'tecnico' => ['pass' => '12345', 'role' => 'tecnico', 'name' => 'Técnico Terreno']
        ];
        
        if (isset($validUsers[$username]) && $validUsers[$username]['pass'] === $password) {
            $_SESSION['user_id'] = uniqid('usr_');
            $_SESSION['user_name'] = $validUsers[$username]['name'];
            $_SESSION['user_email'] = $username . '@medicalot.com';
            $_SESSION['user_role'] = $validUsers[$username]['role'];
            $_SESSION['role_name'] = $validUsers[$username]['name'];
            $_SESSION['login_time'] = time();
            
            header('Location: /index.php');
            exit;
        } else {
            $error = 'Usuario o contraseña incorrectos';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - MedicalOT | Hospital Antofagasta</title>
    <link rel="stylesheet" href="/css/medicalot.css">
    <link rel="icon" type="image/png" href="/img/logohospitalantofagasta.jpeg">
</head>
<body>
    <!-- Background con imagen del hospital -->
    <div class="app-background"></div>
    
    <!-- Header minimalista para login -->
    <header class="login-header">
        <div class="header-brand">
            <img src="/img/logohospitalantofagasta.jpeg" alt="Hospital Antofagasta" class="header-logo-small">
            <div class="header-text">
                <strong>MedicalOT</strong>
                <span>Gestión de Mantenimiento Hospitalario</span>
            </div>
        </div>
    </header>
    
    <div class="login-container">
        <div class="login-box">
            <!-- Logo oficial del Hospital Antofagasta -->
            <img src="/img/logohospitalantofagasta.jpeg" 
                 alt="Hospital Antofagasta" 
                 class="login-logo"
                 onerror="this.src='/img/logo-placeholder.png'; this.onerror=null;">
            
            <h1 class="login-title">MedicalOT</h1>
            <p class="login-subtitle">Sistema de Gestión de Órdenes de Trabajo</p>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label" for="username">Usuario</label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        class="form-input" 
                        placeholder="Ingrese su usuario"
                        value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                        required 
                        autofocus
                    >
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="password">Contraseña</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        class="form-input" 
                        placeholder="••••••••"
                        required
                    >
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
                    </svg>
                    Ingresar al Sistema
                </button>
            </form>
            
            <div class="pulse-line"></div>
            
            <div class="login-credentials">
                <p><strong>Credenciales de prueba:</strong></p>
                <code>adminhospital</code> | <code>admicontratista</code> | <code>tecnico</code><br>
                <span>Contraseña: <code>12345</code></span>
            </div>
            
            <div class="login-footer">
                <img src="/img/logo.png" alt="MedicalOT" class="footer-logo">
                <span>© 2026 Hospital Antofagasta</span>
            </div>
        </div>
    </div>
    
    <script src="/js/app.js"></script>
</body>
</html>