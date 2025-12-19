<?php

declare(strict_types=1);

namespace App\Infrastructure\Migrations;

use App\Infrastructure\Database;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

class MigrationScriptRunner
{
    /**
     * Execute the migration runner and return any tenant-specific errors.
     *
     * @return list<string>
     */
    public static function run(string $migrationsPath): array
    {
        $availableDrivers = PDO::getAvailableDrivers();

        try {
            $base = Database::connectWithSchema('public');
        } catch (Throwable $e) {
            throw self::connectionException($e, $availableDrivers);
        }

        self::assertProductionDatabaseDriver($base);

        Migrator::migrate($base, $migrationsPath);

        try {
            $stmt = $base->query('SELECT subdomain FROM tenants');
            if ($stmt === false) {
                throw new RuntimeException('Unable to query tenant subdomains.');
            }

            /** @var list<string> $tenants */
            $tenants = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable $e) {
            throw new RuntimeException('Failed to retrieve tenant schemas: ' . $e->getMessage(), 0, $e);
        }

        $schemas = [];
        foreach ($tenants as $subdomain) {
            $schema = trim((string) $subdomain);
            if ($schema === '' || $schema === 'public' || $schema === 'main') {
                $schema = 'public';
            }

            $schemas[$schema] = true;
        }

        unset($schemas['public']);

        $errors = [];
        foreach (array_keys($schemas) as $schema) {
            try {
                $tenant = Database::connectWithSchema($schema);
                Migrator::migrate($tenant, $migrationsPath);
            } catch (Throwable $e) {
                $errors[] = sprintf('Migration failed for schema "%s": %s', $schema, $e->getMessage());
            }
        }

        return $errors;
    }

    private static function assertProductionDatabaseDriver(PDO $connection): void
    {
        $appEnv = getenv('APP_ENV') ?: 'dev';
        if ($appEnv !== 'production') {
            return;
        }

        $driver = $connection->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'pgsql') {
            return;
        }

        $allowSqlite = getenv('ALLOW_SQLITE_MIGRATIONS') === '1';
        if ($driver === 'sqlite' && $allowSqlite) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Refusing to run production migrations with "%s". Use a PostgreSQL DSN or set ALLOW_SQLITE_MIGRATIONS=1 explicitly.',
            $driver
        ));
    }

    /**
     * @param list<string> $availableDrivers
     */
    private static function connectionException(Throwable $e, array $availableDrivers): RuntimeException
    {
        $dsn = getenv('POSTGRES_DSN') ?: '';
        $driver = strtolower($dsn !== '' ? explode(':', $dsn, 2)[0] : '');
        if ($driver === '') {
            $driver = 'unknown';
        }

        $message = $e->getMessage();

        if ($driver !== 'unknown' && !in_array($driver, $availableDrivers, true) && $e instanceof PDOException) {
            $message = sprintf(
                "PDO driver '%s' is not available. Enable the extension for this driver or adjust POSTGRES_DSN (current: %s).",
                $driver,
                $dsn === '' ? '[empty]' : $dsn
            );
        }

        return new RuntimeException($message, 0, $e);
    }
}
