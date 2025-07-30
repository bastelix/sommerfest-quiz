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
$pass = getenv('POSTGRES_PASSWORD') ?: getenv('POSTGRES_PASS') ?: ($config['postgres_pass'] ?? null);
$db   = getenv('POSTGRES_DB') ?: ($config['postgres_db'] ?? null);

if (!$dsn || !$user || !$db) {
    fwrite(STDERR, "PostgreSQL connection parameters missing\n");
    exit(1);
}

$pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
if ($count > 0) {
    echo "Users already present\n";
    exit(0);
}

$pwd = bin2hex(random_bytes(8));
$stmt = $pdo->prepare('INSERT INTO users(username,password,role) VALUES(?,?,?)');
$stmt->execute(['admin', password_hash($pwd, PASSWORD_DEFAULT), Roles::ADMIN]);

file_put_contents('/var/www/data/admin_password.txt', $pwd . "\n");

echo "Admin user created with password stored in data/admin_password.txt\n";
