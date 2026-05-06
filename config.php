<?php
/**
 * config.php - Configuración para Railway (Versión Final)
 * Ubicación: RAÍZ del proyecto
 */

if (PHP_SAPI !== 'cli' && !defined('APP_ENTRY_POINT')) {
    http_response_code(403);
    exit('Acceso denegado');
}

// Helper robusto para variables de entorno
function env($key, $default = null) {
    $val = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    return $val === false ? $default : $val;
}

$isProduction = false;
$host = null; $port = '3306'; $dbname = null; $username = null; $password = null;

// 🎯 PRIORIDAD ABSOLUTA: DATABASE_URL (formato estándar Railway)
$databaseUrl = env('DATABASE_URL');
if ($databaseUrl && stripos($databaseUrl, 'mysql://') === 0) {
    $parsed = parse_url($databaseUrl);
    
    $host = $parsed['host'] ?? null;
    $port = $parsed['port'] ?? '3306';
    $dbname = ltrim($parsed['path'] ?? '', '/');
    $username = $parsed['user'] ?? null;
    $password = $parsed['pass'] ?? null;
    
    // 🔧 CORRECCIÓN CRÍTICA: En Railway, si el host contiene 'proxy', 
    // es una URL externa. Para conexiones internas entre servicios:
    // - Host interno: mysql.railway.internal
    // - Puerto interno: 3306
    // Pero PHP en Railway puede necesitar la URL externa.
    // Probamos primero con los valores parseados directamente.
    
    if ($host && $dbname && $username !== null) {
        $isProduction = true;
        error_log(" Railway: DATABASE_URL parseada -> $host:$port/$dbname");
    }
}

// Fallback: Configuración local XAMPP
if (!$isProduction) {
    $host = '127.0.0.1';
    $port = '3306';
    $dbname = 'medicalot_local';
    $username = 'root';
    $password = '';
    error_log("💻 Local: $host:$port/$dbname");
}

// 🔄 CONEXIÓN CON REINTENTOS (para race conditions en startup)
$pdo = null;
$lastError = null;

for ($attempt = 1; $attempt <= 3; $attempt++) {
    try {
        $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
        
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 10,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone='+00:00'"
        ]);
        
        error_log("✅ PDO conectado (intento $attempt)");
        break;
        
    } catch (PDOException $e) {
        $lastError = $e->getMessage();
        error_log("❌ Intento $attempt: $lastError");
        
        if ($attempt < 3) {
            sleep(2);
            // Segundo intento: probar con host interno si falló el externo
            if ($attempt === 2 && stripos($host ?? '', 'proxy') !== false) {
                $host = 'mysql.railway.internal';
                $port = '3306';
                error_log("🔄 Reintentando con host interno: $host:$port");
            }
        }
    }
}

// Si todo falla
if (!$pdo) {
    error_log(" FATAL: No se pudo conectar. Debug: host=$host port=$port db=$dbname user=$username");
    
    if ($isProduction) {
        http_response_code(500);
        exit("⚠️ Error de conexión a base de datos. Revise configuración de Railway.");
    }
    throw new PDOException($lastError ?? 'Unknown connection error');
}
?>