<?php
/**
 * config.php - Configuración de Base de Datos
 * Ubicación: RAÍZ del proyecto (fuera de /public)
 */

// Cargar .env si existe
if (file_exists(__DIR__ . '/.env')) {
    $envVars = parse_ini_file(__DIR__ . '/.env');
    foreach ($envVars as $key => $value) {
        if (!getenv($key)) putenv("$key=$value");
    }
}

// 1. SEGURIDAD: Prevenir acceso directo por navegador
if (PHP_SAPI !== 'cli' && !defined('APP_ENTRY_POINT')) {
    http_response_code(403);
    exit('Acceso denegado');
}

// 2. FUNCIÓN HELPER: Obtener variable de entorno desde múltiples fuentes
function env($key, $default = null) {
    // Método 1: getenv()
    $value = getenv($key);
    if ($value !== false && $value !== null) return $value;
    
    // Método 2: $_ENV
    if (isset($_ENV[$key])) return $_ENV[$key];
    
    // Método 3: $_SERVER
    if (isset($_SERVER[$key])) return $_SERVER[$key];
    
    // Método 4: putenv fallback (para algunos entornos)
    $value = putenv($key);
    if ($value !== false) return $value;
    
    return $default;
}

// 3. DETECCIÓN DE ENTORNO
$isProduction = false;
$host = null; $dbname = null; $username = null; $password = null; $port = '3306';

// --- Método A: DATABASE_URL (formato URI completo) ---
$databaseUrl = env('DATABASE_URL');
if ($databaseUrl && strpos($databaseUrl, 'mysql://') === 0) {
    $parsed = parse_url($databaseUrl);
    $host = $parsed['host'] ?? null;
    $port = $parsed['port'] ?? '3306';
    $dbname = ltrim($parsed['path'] ?? '', '/');
    $username = $parsed['user'] ?? null;
    $password = $parsed['pass'] ?? null;
    
    if ($host && $dbname && $username !== null) {
        $isProduction = true;
        error_log("✅ Conectando vía DATABASE_URL: $host:$port/$dbname");
    }
}

// --- Método B: Variables MYSQL_* individuales ---
if (!$isProduction && env('MYSQL_HOST')) {
    $host = env('MYSQL_HOST');
    $port = env('MYSQL_PORT', '3306');
    $dbname = env('MYSQL_DATABASE');
    $username = env('MYSQL_USER');
    $password = env('MYSQL_PASSWORD');
    
    if ($host && $dbname && $username !== null) {
        $isProduction = true;
        error_log("✅ Conectando vía MYSQL_* variables: $host:$port/$dbname");
    }
}

// --- Método C: Variables RAILWAY_MYSQL_* ---
if (!$isProduction && env('RAILWAY_MYSQL_HOST')) {
    $host = env('RAILWAY_MYSQL_HOST');
    $port = env('RAILWAY_MYSQL_PORT', '3306');
    $dbname = env('RAILWAY_MYSQL_DATABASE');
    $username = env('RAILWAY_MYSQL_USER');
    $password = env('RAILWAY_MYSQL_PASSWORD');
    
    if ($host && $dbname && $username !== null) {
        $isProduction = true;
        error_log("✅ Conectando vía RAILWAY_MYSQL_* variables: $host:$port/$dbname");
    }
}

// --- Método D: .env file (para desarrollo local con php-dotenv) ---
if (!$isProduction && file_exists(__DIR__ . '/.env')) {
    $envVars = parse_ini_file(__DIR__ . '/.env');
    if (!empty($envVars['DB_HOST'])) {
        $host = $envVars['DB_HOST'];
        $port = $envVars['DB_PORT'] ?? '3306';
        $dbname = $envVars['DB_NAME'];
        $username = $envVars['DB_USER'];
        $password = $envVars['DB_PASS'] ?? '';
        error_log("✅ Conectando vía archivo .env local: $host:$port/$dbname");
    }
}

// --- Fallback: Configuración local XAMPP ---
if (!$isProduction) {
    $host = '127.0.0.1';  // Usar IP en lugar de localhost para evitar sockets
    $dbname = 'medicalot_local';
    $username = 'root';
    $password = '';
    $port = '3306';
    error_log("⚠️ Usando configuración LOCAL: $host:$port/$dbname");
}

// 4. CONEXIÓN PDO
try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    
    $options = [
        PDO::ATTR_ERRMODE => $isProduction ? PDO::ERRMODE_WARNING : PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone='+00:00'",
        PDO::ATTR_TIMEOUT => 10
    ];
    
    $pdo = new PDO($dsn, $username, $password, $options);
    
    // Test de conexión en producción (solo log)
    if ($isProduction) {
        error_log("✅ Conexión PDO exitosa a $dbname en $host");
    }
    
} catch (PDOException $e) {
    $errorMsg = "DB Connection Failed: " . $e->getMessage();
    $debugInfo = "Host:$host Port:$port DB:$dbname User:$username Prod:" . ($isProduction ? 'YES' : 'NO');
    
    error_log("❌ $errorMsg | $debugInfo");
    
    // En producción: mensaje genérico + variables disponibles para debug
    if ($isProduction) {
        http_response_code(500);
        $availableVars = array_filter([
            'DATABASE_URL' => env('DATABASE_URL') ? 'SET' : 'NOT SET',
            'MYSQL_HOST' => env('MYSQL_HOST') ? 'SET' : 'NOT SET',
            'RAILWAY_MYSQL_HOST' => env('RAILWAY_MYSQL_HOST') ? 'SET' : 'NOT SET',
        ]);
        error_log("🔍 Variables disponibles: " . json_encode($availableVars));
        exit("⚠️ Error de conexión. Revisar logs de Railway para detalles.");
    }
    
    // En local: mostrar error completo para depuración
    throw $e;
}
?>