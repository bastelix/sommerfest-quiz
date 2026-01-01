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

$tenantId = getenv('TENANT_ID');
if (!$tenantId) {
    fwrite(STDERR, "TENANT_ID not set\n");
    exit(1);
}
$tenantDir = "/var/www/data/$tenantId";
if (!is_dir($tenantDir) && !mkdir($tenantDir, 0777, true) && !is_dir($tenantDir)) {
    fwrite(STDERR, "Unable to create tenant directory: $tenantDir\n");
    exit(1);
}
$pwdFile = "$tenantDir/admin_password.txt";

$pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
if ($count > 0) {
    if (is_readable($pwdFile)) {
        echo "Users already present. Password stored in $pwdFile\n";
    } else {
        echo "Users already present\n";
    }
    exit(0);
}

$pwd = bin2hex(random_bytes(8));
$stmt = $pdo->prepare('INSERT INTO users(username,password,role) VALUES(?,?,?)');
$stmt->execute(['admin', password_hash($pwd, PASSWORD_DEFAULT), Roles::ADMIN]);

file_put_contents($pwdFile, $pwd . "\n");

echo "Admin user created with password stored in $pwdFile\n";
