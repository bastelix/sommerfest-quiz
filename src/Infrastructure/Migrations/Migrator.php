<?php

declare(strict_types=1);

namespace App\Infrastructure\Migrations;

use PDO;
use PDOException;
use Throwable;

class Migrator
{
    /** @var callable|null */
    private static $hook = null;

    public static function setHook(?callable $hook): void {
        self::$hook = $hook;
    }

    public static function migrate(PDO $pdo, string $dir): void {
        if (self::$hook !== null) {
            $shouldContinue = (self::$hook)($pdo, $dir);
            if ($shouldContinue === false) {
                return;
            }
        }

        $pdo->exec('CREATE TABLE IF NOT EXISTS migrations (version TEXT PRIMARY KEY)');
        $stmt = $pdo->query('SELECT version FROM migrations');
        $applied = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $files = glob(rtrim($dir, '/') . '/*.sql');
        sort($files);
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $schema = __DIR__ . '/sqlite-schema.sql';
            $sql = file_get_contents($schema);
            if ($sql !== false) {
                $pdo->exec($sql);
            }
            foreach ($files as $file) {
                $version = basename($file);
                $ins = $pdo->prepare('INSERT OR IGNORE INTO migrations(version) VALUES(?)');
                $ins->execute([$version]);
            }

            return;
        }

        foreach ($files as $file) {
            $version = basename($file);
            if (in_array($version, $applied, true)) {
                continue;
            }

            $sql = file_get_contents($file);
            if ($sql === false) {
                continue;
            }

            // Remove pure comment lines and skip empty migrations
            $trimmed = preg_replace('/^\s*--.*$/m', '', $sql);
            if (trim($trimmed) === '') {
                $ins = $pdo->prepare('INSERT INTO migrations(version) VALUES(?)');
                $ins->execute([$version]);
                continue;
            }

            $shouldStripOnboardingAlter = self::shouldStripOnboardingStateAlter($pdo, $sql);
            if ($shouldStripOnboardingAlter) {
                $sql = self::stripOnboardingStateAlter($sql);
            }

            try {
                if (trim($sql) !== '') {
                    $pdo->exec($sql);
                }
            } catch (PDOException $e) {
                if (self::shouldIgnoreOnboardingStateDuplicate($pdo, $sql, $e)) {
                    self::finalizeOnboardingStateMigration($pdo);
                } else {
                    throw $e;
                }
            }

            $ins = $pdo->prepare('INSERT INTO migrations(version) VALUES(?)');
            $ins->execute([$version]);
        }

        // Manuelle Migration für PostgreSQL: QRRemember-Spalte mit Altwertübernahme
        if ($driver === 'pgsql') {
            // Spalte qrremember hinzufügen, falls noch nicht vorhanden
            $pdo->exec(<<<'SQL'
                ALTER TABLE config
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
                      AND table_schema = current_schema()
                )
SQL
            )->fetchColumn();

            if ($hasOldColumn) {
                // Werte übernehmen und alte Spalte entfernen
                $pdo->exec(<<<'SQL'
                    UPDATE config
                    SET qrremember = COALESCE("QRRemember", qrremember);
SQL
                );

                $pdo->exec('ALTER TABLE config DROP COLUMN IF EXISTS "QRRemember";');
            }
        }

        $eventStmt = $pdo->query('SELECT uid FROM events LIMIT 2');
        $events = $eventStmt->fetchAll(PDO::FETCH_COLUMN);
        if (count($events) === 1) {
            $pdo->exec('DELETE FROM active_event');
            $ins = $pdo->prepare('INSERT INTO active_event(event_uid) VALUES(?)');
            $ins->execute([$events[0]]);
        }
    }

    private static function shouldIgnoreOnboardingStateDuplicate(PDO $pdo, string $sql, PDOException $e): bool
    {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'pgsql') {
            return false;
        }

        $normalizedSql = strtolower($sql);
        if (str_contains($normalizedSql, 'add column onboarding_state') === false ||
            str_contains($normalizedSql, 'alter table tenants') === false) {
            return false;
        }

        $sqlState = (string) $e->getCode();
        $message = strtolower($e->getMessage());
        if ($sqlState !== '42701' && str_contains($message, 'onboarding_state') === false) {
            return false;
        }

        try {
            $stmt = $pdo->query(<<<'SQL'
                SELECT 1
                FROM information_schema.columns
                WHERE table_schema = current_schema()
                  AND table_name = 'tenants'
                  AND column_name = 'onboarding_state'
            SQL);
        } catch (Throwable) {
            return false;
        }

        if ($stmt === false) {
            return false;
        }

        return $stmt->fetchColumn() !== false;
    }

    private static function shouldStripOnboardingStateAlter(PDO $pdo, string $sql): bool
    {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'pgsql') {
            return false;
        }

        $normalizedSql = strtolower($sql);
        if (str_contains($normalizedSql, 'add column onboarding_state') === false ||
            str_contains($normalizedSql, 'alter table tenants') === false) {
            return false;
        }

        try {
            $stmt = $pdo->query(<<<'SQL'
                SELECT 1
                FROM information_schema.columns
                WHERE table_schema = current_schema()
                  AND table_name = 'tenants'
                  AND column_name = 'onboarding_state'
            SQL);
        } catch (Throwable) {
            return false;
        }

        if ($stmt === false) {
            return false;
        }

        return $stmt->fetchColumn() !== false;
    }

    private static function stripOnboardingStateAlter(string $sql): string
    {
        $pattern = '/^\\s*ALTER\\s+TABLE\\s+tenants\\s+ADD\\s+COLUMN\\s+onboarding_state\\b[^;]*;?/mi';
        $updated = preg_replace($pattern, '', $sql);

        return $updated ?? $sql;
    }

    private static function finalizeOnboardingStateMigration(PDO $pdo): void
    {
        $pdo->exec("UPDATE tenants SET onboarding_state = 'completed'");
    }
}
