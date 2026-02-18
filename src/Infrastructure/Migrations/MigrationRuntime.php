<?php

declare(strict_types=1);

namespace App\Infrastructure\Migrations;

use App\Service\DesignTokenService;
use PDO;

/**
 * Coordinates running database migrations during the lifetime of a request.
 */
final class MigrationRuntime
{
    private const ENV_FLAG = 'RUN_MIGRATIONS_ON_REQUEST';

    /**
     * @var array<string, bool>
     */
    private static array $executed = [];

    private static bool $stylesheetsRebuilt = false;

    /**
     * Run migrations for the given connection once per process when enabled.
     */
    public static function ensureUpToDate(PDO $connection, string $directory, string $key): void
    {
        if (!self::shouldRunOnRequest()) {
            return;
        }

        if (isset(self::$executed[$key])) {
            return;
        }

        Migrator::migrate($connection, $directory);
        self::$executed[$key] = true;

        if (!self::$stylesheetsRebuilt) {
            self::$stylesheetsRebuilt = true;
            try {
                (new DesignTokenService($connection))->rebuildStylesheet();
            } catch (\Throwable $e) {
                // Non-critical: stylesheet rebuild can be retried on next request.
            }
        }
    }

    /**
     * Reset tracked state – useful for tests.
     */
    public static function reset(): void
    {
        self::$executed = [];
        self::$stylesheetsRebuilt = false;
    }

    private static function shouldRunOnRequest(): bool
    {
        $value = getenv(self::ENV_FLAG);
        if ($value === false) {
            return false;
        }

        $normalised = strtolower(trim((string) $value));

        return $normalised !== '' && in_array($normalised, ['1', 'true', 'yes', 'on'], true);
    }
}
