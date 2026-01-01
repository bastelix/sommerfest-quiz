<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Domain\Roles;

$base = dirname(__DIR__);
$configFile = "$base/data/config.json";
$config = [];
if (is_readable($configFile)) {
    $config = json_decode(file_get_contents($configFile), true) ?? [];
}
$dsn = getenv('POSTGRES_DSN') ?: ($config['postgres_dsn'] ?? null);
$user = getenv('POSTGRES_USER') ?: ($config['postgres_user'] ?? null);
$pass = getenv('POSTGRES_PASSWORD') ?: ($config['postgres_password'] ?? null);
$db   = getenv('POSTGRES_DB') ?: ($config['postgres_db'] ?? null);

if (!$dsn || !$user || !$db) {
    fwrite(STDERR, "PostgreSQL connection parameters missing\n");
    exit(1);
}


$pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->beginTransaction();
$insert = $pdo->prepare('INSERT INTO users(username,password,role) VALUES(?,?,?) ON CONFLICT (username) DO NOTHING');

foreach (Roles::ALL as $role) {
    $insert->execute([
        $role,
        password_hash($role, PASSWORD_DEFAULT),
        $role,
    ]);
}

$pdo->commit();

echo "Seeded example users\n";
