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

        // Manuelle Migration für PostgreSQL: QRRemember-Spalte mit Altwertübernahme
        if ($driver === 'pgsql') {
            // Spalte qrremember hinzufügen, falls noch nicht vorhanden
            $pdo->exec(<<<'SQL'
                ALTER TABLE public.config
                ADD COLUMN IF NOT EXISTS qrremember BOOLEAN DEFAULT FALSE;
SQL
            );

            // Prüfen, ob eine alte Spalte "QRRemember" existiert
            $hasOldColumn = $pdo->query(<<<'SQL'
                SELECT EXISTS (
                    SELECT 1
                    FROM information_schema.columns
                    WHERE table_name = 'config'
                      AND column_name = 'QRRemember'
                )
SQL
            )->fetchColumn();

            if ($hasOldColumn) {
                // Werte übernehmen und alte Spalte entfernen
                $pdo->exec(<<<'SQL'
                    UPDATE public.config
                    SET qrremember = COALESCE(qrremember, "QRRemember");
                    ALTER TABLE public.config DROP COLUMN IF EXISTS "QRRemember";
SQL
                );
            }
        }
    }
}
