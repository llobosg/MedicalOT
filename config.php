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

// 2. DETECCIÓN DE ENTORNO - Múltiples métodos
$isProduction = false;
$host = null;
$dbname = null;
$username = null;
$password = null;
$port = '3306';

// Método 1: Variables individuales de Railway (MYSQL_*)
if (getenv('MYSQL_HOST')) {
    $host = getenv('MYSQL_HOST');
    $dbname = getenv('MYSQL_DATABASE');
    $username = getenv('MYSQL_USER');
    $password = getenv('MYSQL_PASSWORD');
    $port = getenv('MYSQL_PORT') ?: '3306';
    $isProduction = true;
    
// Método 2: DATABASE_URL (formato URI que Railway a veces usa)
} elseif (getenv('DATABASE_URL')) {
    $databaseUrl = getenv('DATABASE_URL');
    // Parsear: mysql://user:pass@host:port/database
    $parsed = parse_url($databaseUrl);
    
    $host = $parsed['host'] ?? null;
    $port = $parsed['port'] ?? '3306';
    $dbname = ltrim($parsed['path'] ?? '', '/');
    $username = $parsed['user'] ?? null;
    
    if (isset($parsed['pass'])) {
        $password = $parsed['pass'];
    }
    
    if ($host && $dbname && $username) {
        $isProduction = true;
    }
    
// Método 3: Variables de Railway con prefijo RAILWAY_MYSQL_*
} elseif (getenv('RAILWAY_MYSQL_HOST')) {
    $host = getenv('RAILWAY_MYSQL_HOST');
    $dbname = getenv('RAILWAY_MYSQL_DATABASE');
    $username = getenv('RAILWAY_MYSQL_USER');
    $password = getenv('RAILWAY_MYSQL_PASSWORD');
    $port = getenv('RAILWAY_MYSQL_PORT') ?: '3306';
    $isProduction = true;
}

// Si no es producción, usar configuración local
if (!$isProduction) {
    $host = '127.0.0.1';
    $dbname = 'medicalot_local';
    $username = 'root';
    $password = '';
    $port = '3306';
}

// 3. CONEXIÓN PDO
try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => $isProduction ? PDO::ERRMODE_WARNING : PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone='+00:00'"
    ]);
    
} catch (PDOException $e) {
    error_log("DB Connection Error: " . $e->getMessage());
    error_log("Host: $host, DB: $dbname, User: $username, Port: $port");
    
    if ($isProduction) {
        http_response_code(500);
        exit("⚠️ Error de conexión a base de datos. Verifique configuración de Railway.");
    }
    
    throw $e;
}
?>