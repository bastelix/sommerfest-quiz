<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

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
