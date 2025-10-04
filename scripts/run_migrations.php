<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

// Load environment variables from .env if available
\App\Support\EnvLoader::loadAndSet(__DIR__ . '/../.env');

try {
    $errors = \App\Infrastructure\Migrations\MigrationScriptRunner::run(__DIR__ . '/../migrations');
} catch (\RuntimeException $e) {
    fwrite(STDERR, '[ERROR] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

if ($errors !== []) {
    foreach ($errors as $message) {
        fwrite(STDERR, '[ERROR] ' . $message . PHP_EOL);
    }

    exit(1);
}
