<?php

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Services\MantencionImportService;

$service = new MantencionImportService($pdo);

$file = __DIR__ . '/../../../storage/mantenimiento.csv';

$service->processFile($file);

echo "<pre>";
print_r($service->stats);
echo "</pre>";