<?php

declare(strict_types=1);

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '';
$decoded = rawurldecode($uri);

$base = getenv('BASE_PATH') ?: '';
$base = '/' . trim($base, '/');
if ($base !== '/' && str_starts_with($decoded, $base)) {
    $decoded = substr($decoded, strlen($base)) ?: '';
    if ($decoded === '' || $decoded[0] !== '/') {
        $decoded = '/' . $decoded;
    }
}

// deny traversal attempts
if (str_contains($decoded, '..') || str_contains($decoded, '\\')) {
    http_response_code(400);
    exit('Invalid path');
}

$publicDir = realpath(__DIR__);
$path = realpath($publicDir . $decoded);
$allowedExt = [
    'css',
    'js',
    'png',
    'jpg',
    'jpeg',
    'gif',
    'svg',
    'ico',
    'html',
    'json',
    'txt',
    'map',
    'webp',
    'avif',
    'woff',
    'woff2',
    'ttf',
    'mp4',
    'webm',
    'ogg',
    'mp3',
    'pdf',
];

if (
    $path !== false
    && str_starts_with($path, $publicDir)
    && is_file($path)
    && in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), $allowedExt, true)
) {
    if ($base === '/') {
        return false;
    }

    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $types = [
        'css' => 'text/css; charset=UTF-8',
        'js' => 'application/javascript; charset=UTF-8',
        'json' => 'application/json; charset=UTF-8',
        'html' => 'text/html; charset=UTF-8',
        'txt' => 'text/plain; charset=UTF-8',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'avif' => 'image/avif',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'map' => 'application/json; charset=UTF-8',
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'ogg' => 'application/ogg',
        'mp3' => 'audio/mpeg',
        'pdf' => 'application/pdf',
    ];
    $type = $types[$ext] ?? mime_content_type($path) ?: 'application/octet-stream';
    header('Content-Type: ' . $type);
    header('Content-Length: ' . (string)filesize($path));
    if ($_SERVER['REQUEST_METHOD'] !== 'HEAD') {
        readfile($path);
    }
    return true;
}

require __DIR__ . '/index.php';
