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

$host = getenv('SLIM_VIRTUAL_HOST') ?: getenv('DOMAIN');
$schema = 'public';
if ($host) {
    $sub = explode('.', $host)[0];
    if ($sub && $sub !== $host) {
        $stmt = $base->prepare('SELECT subdomain FROM tenants WHERE subdomain = ?');
        $stmt->execute([$sub]);
        $found = $stmt->fetchColumn();
        if ($found !== false) {
            $schema = (string) $found;
        }
    }
}

$tenant = Database::connectWithSchema($schema);
Migrator::migrate($tenant, __DIR__ . '/../migrations');
