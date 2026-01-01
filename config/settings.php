<?php

$settings = [];

// Load environment variables from .env if available
\App\Support\EnvLoader::loadAndSet(__DIR__ . '/../.env');

$settings += [
    'displayErrorDetails' => false,
];

$envError = getenv('DISPLAY_ERROR_DETAILS');
if ($envError !== false) {
    $settings['displayErrorDetails'] = filter_var($envError, FILTER_VALIDATE_BOOLEAN);
}

$settings['postgres_dsn'] = getenv('POSTGRES_DSN') ?: ($settings['postgres_dsn'] ?? null);
$settings['postgres_user'] = getenv('POSTGRES_USER') ?: ($settings['postgres_user'] ?? null);
$settings['postgres_password'] = getenv('POSTGRES_PASSWORD')
    ?: ($settings['postgres_password'] ?? null);

$settings['main_domain'] = getenv('MAIN_DOMAIN') ?: ($settings['main_domain'] ?? null);
$settings['service_user'] = getenv('SERVICE_USER') ?: ($settings['service_user'] ?? null);
$settings['service_pass'] = getenv('SERVICE_PASS') ?: ($settings['service_pass'] ?? null);
$settings['tenants_dir'] = getenv('TENANTS_DIR') ?: (dirname(__DIR__) . '/tenants');

return $settings;
