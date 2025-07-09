<?php
declare(strict_types=1);

$base = dirname(__DIR__);
$configFile = "$base/data/config.json";
$config = [];
if (is_readable($configFile)) {
    $config = json_decode(file_get_contents($configFile), true) ?? [];
}
$dsn = getenv('POSTGRES_DSN') ?: ($config['postgres_dsn'] ?? null);
$user = getenv('POSTGRES_USER') ?: ($config['postgres_user'] ?? null);
$pass = getenv('POSTGRES_PASSWORD') ?: getenv('POSTGRES_PASS') ?: ($config['postgres_pass'] ?? null);
$db   = getenv('POSTGRES_DB') ?: ($config['postgres_db'] ?? null);

if (!$dsn || !$user || !$db) {
    fwrite(STDERR, "PostgreSQL connection parameters missing\n");
    exit(1);
}

$pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->beginTransaction();
$insert = $pdo->prepare('INSERT INTO users(username,password,role) VALUES(?,?,?) ON CONFLICT (username) DO NOTHING');
$insert->execute(['admin', password_hash('admin', PASSWORD_DEFAULT), 'admin']);
$insert->execute(['staff', password_hash('staff', PASSWORD_DEFAULT), 'catalog-editor']);
$pdo->commit();

echo "Seeded example users\n";

