<?php

define('APP_ENTRY_POINT', true);

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Services\MantencionImportService;

$service = new MantencionImportService($pdo);

$file = __DIR__ . '/../../../storage/mantenimiento.csv';

$service->processFile($file);

echo "<pre>";
echo "📊 Procesadas: " . $service->stats['processed'] . "\n";
echo "✅ Insertadas: " . ($service->stats['updated'] ?? 0) . "\n";
echo "❌ Errores: " . $service->stats['errors'] . "\n";
echo "</pre>";