<?php
/**
 * config.php - Configuración de Base de Datos
 * Ubicación: RAÍZ del proyecto (fuera de /public)
 */

// 1. SEGURIDAD: Prevenir acceso directo por navegador
if (PHP_SAPI !== 'cli' && !defined('APP_ENTRY_POINT')) {
    http_response_code(403);
    exit('Acceso denegado');
}

// 2. DETECCIÓN DE ENTORNO
// Railway inyecta 'MYSQL_HOST' automáticamente si el servicio MySQL está conectado
if (getenv('MYSQL_HOST')) {
    // 🚀 ENTORNO PRODUCCIÓN (RAILWAY)
    $host = getenv('MYSQL_HOST');
    $dbname = getenv('MYSQL_DATABASE');
    $username = getenv('MYSQL_USER');
    $password = getenv('MYSQL_PASSWORD');
    $port = getenv('MYSQL_PORT') ?: '3306';
    $isProduction = true;
} else {
    // 💻 ENTORNO DESARROLLO (LOCAL XAMPP)
    // Nota: Usamos '127.0.0.1' para evitar conflictos de sockets en macOS/Linux
    $host = '127.0.0.1'; 
    $dbname = 'medicalot_local'; // Nombre de tu BD local
    $username = 'root';
    $password = ''; // Tu contraseña de XAMPP
    $port = '3306';
    $isProduction = false;
}

// 3. CONEXIÓN PDO
try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        // En producción, ERRMODE_WARNING evita mostrar errores crudos al usuario
        PDO::ATTR_ERRMODE => $isProduction ? PDO::ERRMODE_WARNING : PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false, // Previene SQL Injection
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone='+00:00'"
    ]);
} catch (PDOException $e) {
    error_log("DB Connection Error: " . $e->getMessage());
    
    // En producción, mostramos mensaje genérico y matamos el script
    if ($isProduction) {
        http_response_code(500);
        // Mensaje amigable para el usuario (puedes personalizarlo)
        exit("⚠️ Error de conexión a base de datos. Por favor contacte al administrador.");
    }
    
    // En local, lanzamos el error para depurar
    throw $e;
}
?>