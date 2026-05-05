<?php
/**
 * MedicalOT - Login
 */

define('APP_ENTRY_POINT', true);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/session.php';

// Si ya está logueado, redirigir
if (isset($_SESSION['user_id'])) {
    header('Location: /index.php');
    exit;
}

$error = '';

// Procesar login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Validación básica (luego se conectará a BD)
    if (empty($username) || empty($password)) {
        $error = 'Por favor ingrese usuario y contraseña';
    } else {
        // TODO: Validar contra base de datos
        // Por ahora, credenciales de prueba
        $validUsers = [
            'adminhospital' => ['pass' => '12345', 'role' => 'admin_hosp', 'name' => 'Administrador Hospital'],
            'admicontratista' => ['pass' => '12345', 'role' => 'admin_cont', 'name' => 'Administrador Contratista'],
            'tecnico' => ['pass' => '12345', 'role' => 'tecnico', 'name' => 'Técnico Terreno']
        ];
        
        if (isset($validUsers[$username]) && $validUsers[$username]['pass'] === $password) {
            // Crear sesión
            $_SESSION['user_id'] = uniqid('usr_');
            $_SESSION['user_name'] = $validUsers[$username]['name'];
            $_SESSION['user_email'] = $username . '@medicalot.com';
            $_SESSION['user_role'] = $validUsers[$username]['role'];
            $_SESSION['role_name'] = $validUsers[$username]['name'];
            $_SESSION['login_time'] = time();
            
            // Redirigir
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
    <div class="app-background"></div>
    
    <div class="login-container">
        <div class="login-box">
            <img src="/img/logohospitalantofagasta.jpeg" alt="Hospital Antofagasta" class="login-logo">
            <h1 class="login-title">MedicalOT</h1>
            <p class="login-subtitle">Sistema de Gestión de Órdenes de Trabajo</p>
            
            <?php if ($error): ?>
                <div style="background: #FEF2F2; border: 1px solid #FECACA; color: #DC2626; padding: 0.875rem; border-radius: 0.5rem; margin-bottom: 1.5rem; font-size: 0.9rem;">
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
                        placeholder="Ingrese su contraseña"
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
            
            <div class="pulse-line" style="margin: 2rem 0;"></div>
            
            <div style="text-align: center; font-size: 0.85rem; color: var(--gray-600);">
                <p style="margin-bottom: 0.5rem;"><strong>Credenciales de prueba:</strong></p>
                <p>adminhospital / admicontratista / tecnico</p>
                <p>Contraseña: 12345</p>
            </div>
        </div>
    </div>
    
    <script src="/js/app.js"></script>
</body>
</html>