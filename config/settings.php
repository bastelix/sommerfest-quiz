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

$settings['postgres_dsn'] = getenv('POSTGRES_DSN') ?: ($settings['postgres_dsn'] ?? null);
$settings['postgres_user'] = getenv('POSTGRES_USER') ?: ($settings['postgres_user'] ?? null);
$settings['postgres_pass'] = getenv('POSTGRES_PASSWORD')
    ?: getenv('POSTGRES_PASS')
    ?: ($settings['postgres_pass'] ?? null);

$settings['main_domain'] = getenv('MAIN_DOMAIN') ?: ($settings['main_domain'] ?? null);

return $settings;
