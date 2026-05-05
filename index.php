<?php
/**
 * MedicalOT - Router Principal
 * Redirige todas las peticiones a public/
 */

// Obtener la URI solicitada
$requestUri = $_SERVER['REQUEST_URI'];
$scriptName = $_SERVER['SCRIPT_NAME'];

// Si es una petición a un archivo estático en public/, servirlo
$publicPath = __DIR__ . '/public';
$filePath = $publicPath . parse_url($requestUri, PHP_URL_PATH);

if (file_exists($filePath) && is_file($filePath)) {
    // Servir archivo estático (CSS, JS, imágenes)
    return false;
}

// Incluir el index.php de public/
require __DIR__ . '/public/index.php';