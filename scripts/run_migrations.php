<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Infrastructure\Database;
use App\Infrastructure\Migrations\Migrator;

$base = Database::connectFromEnv();
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
