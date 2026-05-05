<?php
// config.php - UBICAR EN RAÍZ, FUERA DE /public

// Prevenir acceso directo por navegador
if (PHP_SAPI !== 'cli' && !defined('APP_ENTRY_POINT')) {
    http_response_code(403);
    exit('Acceso denegado');
}

// Cargar .env si existe (para local)
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $envVars = parse_ini_file($envFile);
    foreach ($envVars as $key => $value) {
        if (!getenv($key)) putenv("$key=$value");
    }
}

// Detectar entorno
$isProduction = getenv('RAILWAY_ENVIRONMENT') === 'true';

if ($isProduction) {
    $host = getenv('RAILWAY_MYSQL_HOST') ?: getenv('MYSQL_HOST');
    $dbname = getenv('RAILWAY_MYSQL_DATABASE') ?: getenv('MYSQL_DATABASE');
    $username = getenv('RAILWAY_MYSQL_USER') ?: getenv('MYSQL_USER');
    $password = getenv('RAILWAY_MYSQL_PASSWORD') ?: getenv('MYSQL_PASSWORD');
    $port = getenv('RAILWAY_MYSQL_PORT') ?: '3306';
} else {
    // Local con fallback seguro
    $host = getenv('DB_HOST') ?: 'localhost';
    $dbname = getenv('DB_NAME') ?: 'medicalot_local';
    $username = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASS') ?: '';
    $port = getenv('DB_PORT') ?: '3306';
}

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => $isProduction ? PDO::ERRMODE_SILENT : PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false, // Prevenir SQL injection
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone='+00:00'"
        ]
    );
    
    // En producción, no mostrar errores de BD al usuario
    if ($isProduction && !$pdo) {
        error_log("DB Connection failed");
        exit('Error de sistema');
    }
    
} catch (PDOException $e) {
    error_log("DB Error: " . $e->getMessage());
    if (!$isProduction) throw $e;
    exit('Error de conexión');
}

// Constantes de la app
define('APP_NAME', 'MedicalOT');
define('APP_DOMAIN', 'medicalot.com');
define('APP_URL', $isProduction ? 'https://medicalot.com' : 'http://localhost');
?>