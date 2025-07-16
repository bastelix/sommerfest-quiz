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
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        foreach ($files as $file) {
            $version = basename($file);
            if (in_array($version, $applied, true)) {
                continue;
            }

            if ($driver === 'sqlite' && $version !== '20240618_initial_schema.sql') {
                // Subsequent migrations rely on PostgreSQL features.
                // The initial schema already reflects their outcome for tests.
                continue;
            }

            $sql = file_get_contents($file);
            if ($sql === false) {
                continue;
            }

            if ($driver === 'sqlite') {
                // Strip schema prefixes and unsupported blocks
                $sql = preg_replace('/public\./', '', $sql);
                $sql = preg_replace('/DO \$\$.*?\$\$/s', '', $sql);
                $sql = preg_replace('/ALTER TABLE \w+ DROP CONSTRAINT IF EXISTS .*?;/', '', $sql);
                $sql = preg_replace('/\bSERIAL\s+PRIMARY\s+KEY\b/', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
            }

            $pdo->exec($sql);
            $ins = $pdo->prepare('INSERT INTO migrations(version) VALUES(?)');
            $ins->execute([$version]);
        }
    }
}
