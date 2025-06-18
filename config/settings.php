<?php
$path = __DIR__ . '/../data/config.json';
$settings = [];
if (file_exists($path)) {
    $settings = json_decode(file_get_contents($path), true) ?? [];
}

// Load environment variables from .env if available
$envFile = __DIR__ . '/../.env';
if (is_readable($envFile)) {
    $vars = parse_ini_file($envFile, false, INI_SCANNER_RAW);
    if (is_array($vars)) {
        foreach ($vars as $key => $value) {
            if (getenv($key) === false) {
                putenv($key . '=' . $value);
                $_ENV[$key] = $value;
            }
        }
    }
}

$settings += [
    'displayErrorDetails' => false,
];



return $settings;
