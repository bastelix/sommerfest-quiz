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

use App\Infrastructure\Database;
use App\Infrastructure\Migrations\Migrator;

$availableDrivers = PDO::getAvailableDrivers();

try {
    $base = Database::connectFromEnv();
} catch (PDOException $e) {
    $dsn = getenv('POSTGRES_DSN') ?: '';
    $driver = strtolower($dsn !== '' ? explode(':', $dsn, 2)[0] : '');
    if ($driver === '') {
        $driver = 'unknown';
    }

    $message = $e->getMessage();

    if ($driver !== 'unknown' && !in_array($driver, $availableDrivers, true)) {
        $message = sprintf(
            "PDO driver '%s' is not available. Enable the extension for this driver or adjust POSTGRES_DSN (current: %s).",
            $driver,
            $dsn === '' ? '[empty]' : $dsn
        );
    }

    fwrite(STDERR, '[ERROR] ' . $message . PHP_EOL);
    exit(1);
}
Migrator::migrate($base, __DIR__ . '/../migrations');

try {
    $stmt = $base->query('SELECT subdomain FROM tenants');
    if ($stmt === false) {
        throw new RuntimeException('Unable to query tenant subdomains.');
    }

    $tenants = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
    fwrite(STDERR, '[ERROR] Failed to retrieve tenant schemas: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

$schemas = [];
foreach ($tenants as $subdomain) {
    $schema = trim((string) $subdomain);
    if ($schema === '' || $schema === 'public' || $schema === 'main') {
        $schema = 'public';
    }

    $schemas[$schema] = true;
}

unset($schemas['public']);

$hasErrors = false;
foreach (array_keys($schemas) as $schema) {
    try {
        $tenant = Database::connectWithSchema($schema);
        Migrator::migrate($tenant, __DIR__ . '/../migrations');
    } catch (Throwable $e) {
        $hasErrors = true;
        fwrite(STDERR, sprintf('[ERROR] Migration failed for schema "%s": %s', $schema, $e->getMessage()) . PHP_EOL);
    }
}

if ($hasErrors) {
    exit(1);
}
