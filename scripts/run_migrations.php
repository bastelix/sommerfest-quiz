<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

// Load environment variables from .env if available
\App\Support\EnvLoader::loadAndSet(__DIR__ . '/../.env');

try {
    $errors = \App\Infrastructure\Migrations\MigrationScriptRunner::run(__DIR__ . '/../migrations');
} catch (\RuntimeException $e) {
    // Public schema or connection errors are critical – abort.
    fwrite(STDERR, '[ERROR] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

// Tenant-schema migration errors are non-critical. The main application
// and public schema are intact; individual tenants may need manual attention.
if ($errors !== []) {
    foreach ($errors as $message) {
        fwrite(STDERR, '[WARN] ' . $message . PHP_EOL);
    }
}
