<?php

$settings = [];

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

$envError = getenv('DISPLAY_ERROR_DETAILS');
if ($envError !== false) {
    $settings['displayErrorDetails'] = filter_var($envError, FILTER_VALIDATE_BOOLEAN);
}

$settings['postgres_dsn'] = getenv('POSTGRES_DSN') ?: ($settings['postgres_dsn'] ?? null);
$settings['postgres_user'] = getenv('POSTGRES_USER') ?: ($settings['postgres_user'] ?? null);
$settings['postgres_pass'] = getenv('POSTGRES_PASSWORD')
    ?: getenv('POSTGRES_PASS')
    ?: ($settings['postgres_pass'] ?? null);

$settings['main_domain'] = getenv('MAIN_DOMAIN') ?: ($settings['main_domain'] ?? null);
$settings['service_user'] = getenv('SERVICE_USER') ?: ($settings['service_user'] ?? null);
$settings['service_pass'] = getenv('SERVICE_PASS') ?: ($settings['service_pass'] ?? null);

return $settings;
