<?php
$path = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (file_exists($path) && !is_dir($path)) {
    return false;
}
require __DIR__ . '/index.php';
