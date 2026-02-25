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

            $sql = self::rewritePageSlugConflict($pdo, $sql);

            self::ensurePagesStartpageColumn($pdo, $sql);
            self::ensureMarketingMenuTables($pdo, $sql);
            self::ensureConfigFkDropped($pdo, $sql);

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
        if (
            str_contains($normalizedSql, 'add column onboarding_state') === false ||
            str_contains($normalizedSql, 'alter table tenants') === false
        ) {
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
        if (
            str_contains($normalizedSql, 'add column onboarding_state') === false ||
            str_contains($normalizedSql, 'alter table tenants') === false
        ) {
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

    private static function rewritePageSlugConflict(PDO $pdo, string $sql): string
    {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'pgsql') {
            return $sql;
        }

        $normalizedSql = strtolower($sql);
        if (
            str_contains($normalizedSql, 'insert into pages') === false ||
            str_contains($normalizedSql, 'on conflict (slug)') === false
        ) {
            return $sql;
        }

        try {
            $stmt = $pdo->query(<<<'SQL'
                SELECT 1
                FROM information_schema.columns
                WHERE table_schema = current_schema()
                  AND table_name = 'pages'
                  AND column_name = 'namespace'
            SQL);
        } catch (Throwable) {
            return $sql;
        }

        if ($stmt === false || $stmt->fetchColumn() === false) {
            return $sql;
        }

        $updated = preg_replace(
            '/ON\\s+CONFLICT\\s*\\(\\s*slug\\s*\\)/i',
            'ON CONFLICT (namespace, slug)',
            $sql
        );

        return $updated ?? $sql;
    }

    /**
     * Ensure the pages.is_startpage column exists before running migrations
     * that reference it. The column is formally added in 20270505 but referenced
     * earlier by 20260217.
     */
    private static function ensurePagesStartpageColumn(PDO $pdo, string $sql): void
    {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'pgsql') {
            return;
        }

        $normalizedSql = strtolower($sql);
        if (
            str_contains($normalizedSql, 'is_startpage') === false ||
            str_contains($normalizedSql, 'into pages') === false
        ) {
            return;
        }

        try {
            $stmt = $pdo->query(<<<'SQL'
                SELECT 1
                FROM information_schema.columns
                WHERE table_schema = current_schema()
                  AND table_name = 'pages'
                  AND column_name = 'is_startpage'
            SQL);
        } catch (Throwable) {
            return;
        }

        if ($stmt !== false && $stmt->fetchColumn() !== false) {
            return;
        }

        $pdo->exec('ALTER TABLE pages ADD COLUMN IF NOT EXISTS is_startpage BOOLEAN NOT NULL DEFAULT FALSE');
    }

    /**
     * Ensure the marketing_menus, marketing_menu_items and
     * marketing_menu_assignments tables exist before migrations that insert
     * into them. The tables are formally created in 20291213 but referenced
     * earlier by 20260218.
     */
    private static function ensureMarketingMenuTables(PDO $pdo, string $sql): void
    {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'pgsql') {
            return;
        }

        $normalizedSql = strtolower($sql);
        if (str_contains($normalizedSql, 'marketing_menu_items') === false) {
            return;
        }

        // Only act on INSERT/UPDATE statements, not CREATE TABLE itself.
        if (
            str_contains($normalizedSql, 'insert into marketing_menu') === false &&
            str_contains($normalizedSql, 'update marketing_menu') === false
        ) {
            return;
        }

        try {
            $stmt = $pdo->query(<<<'SQL'
                SELECT 1
                FROM information_schema.tables
                WHERE table_schema = current_schema()
                  AND table_name = 'marketing_menus'
            SQL);
        } catch (Throwable) {
            return;
        }

        if ($stmt !== false && $stmt->fetchColumn() !== false) {
            return;
        }

        $pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS marketing_menus (
                id INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                namespace TEXT NOT NULL DEFAULT 'default',
                label TEXT NOT NULL,
                locale TEXT NOT NULL DEFAULT 'de',
                is_active BOOLEAN NOT NULL DEFAULT TRUE,
                updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS marketing_menu_items (
                id INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                menu_id INTEGER NOT NULL REFERENCES marketing_menus(id) ON DELETE CASCADE,
                parent_id INTEGER REFERENCES marketing_menu_items(id) ON DELETE CASCADE,
                namespace TEXT NOT NULL DEFAULT 'default',
                label TEXT NOT NULL,
                href TEXT NOT NULL,
                icon TEXT,
                position INTEGER NOT NULL DEFAULT 0,
                is_external BOOLEAN NOT NULL DEFAULT FALSE,
                locale TEXT NOT NULL DEFAULT 'de',
                is_active BOOLEAN NOT NULL DEFAULT TRUE,
                layout TEXT NOT NULL DEFAULT 'link',
                detail_title TEXT,
                detail_text TEXT,
                detail_subline TEXT,
                is_startpage BOOLEAN NOT NULL DEFAULT FALSE,
                updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS marketing_menu_assignments (
                id INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                menu_id INTEGER NOT NULL REFERENCES marketing_menus(id) ON DELETE CASCADE,
                page_id INTEGER REFERENCES pages(id) ON DELETE CASCADE,
                namespace TEXT NOT NULL DEFAULT 'default',
                slot TEXT NOT NULL,
                locale TEXT NOT NULL DEFAULT 'de',
                is_active BOOLEAN NOT NULL DEFAULT TRUE,
                updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
            );
        SQL);
    }

    /**
     * Drop the fk_config_event foreign key early when a migration inserts
     * into the config table with event_uid values that may not exist in
     * events. The FK is formally dropped in 20290625 but config inserts
     * with namespace-only event_uid happen earlier (20260217).
     */
    private static function ensureConfigFkDropped(PDO $pdo, string $sql): void
    {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'pgsql') {
            return;
        }

        $normalizedSql = strtolower($sql);
        if (
            str_contains($normalizedSql, 'insert into config') === false ||
            str_contains($normalizedSql, 'event_uid') === false
        ) {
            return;
        }

        try {
            $stmt = $pdo->query(<<<'SQL'
                SELECT 1
                FROM information_schema.table_constraints
                WHERE table_schema = current_schema()
                  AND table_name = 'config'
                  AND constraint_name = 'fk_config_event'
            SQL);
        } catch (Throwable) {
            return;
        }

        if ($stmt === false || $stmt->fetchColumn() === false) {
            return;
        }

        $pdo->exec('ALTER TABLE config DROP CONSTRAINT IF EXISTS fk_config_event');
    }
}
