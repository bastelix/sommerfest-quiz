<?php

declare(strict_types=1);

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '';
$decoded = rawurldecode($uri);

// deny traversal attempts
if (str_contains($decoded, '..') || str_contains($decoded, '\\')) {
    http_response_code(400);
    exit('Invalid path');
}

$publicDir = realpath(__DIR__);
$path = realpath($publicDir . $decoded);
$allowedExt = ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'ico', 'html', 'json', 'map', 'webp', 'woff', 'woff2', 'ttf'];

if (
    $path !== false
    && str_starts_with($path, $publicDir)
    && is_file($path)
    && in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), $allowedExt, true)
) {
    return false;
}

require __DIR__ . '/index.php';
