<?php

declare(strict_types=1);

namespace App\Infrastructure\Migrations;

use PDO;

class Migrator
{
    public static function migrate(PDO $pdo, string $dir): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS migrations (version TEXT PRIMARY KEY)');
        $stmt = $pdo->query('SELECT version FROM migrations');
        $applied = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $files = glob(rtrim($dir, '/') . '/*.sql');
        sort($files);
        foreach ($files as $file) {
            $version = basename($file);
            if (in_array($version, $applied, true)) {
                continue;
            }
            $sql = file_get_contents($file);
            if ($sql === false) {
                continue;
            }
            $pdo->exec($sql);
            $ins = $pdo->prepare('INSERT INTO migrations(version) VALUES(?)');
            $ins->execute([$version]);
        }
    }
}
