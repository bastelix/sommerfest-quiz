<?php

declare(strict_types=1);

namespace App\Infrastructure;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Utility class for creating database connections.
 */
class Database
{
    /** @var callable|null */
    private static $factory = null;

    /** @var callable|null */
    private static $connectHook = null;

    /**
     * Create a PDO connection using credentials from environment variables.
     *
     * The number of connection attempts and the initial delay between retries
     * can be overridden using the `POSTGRES_CONNECT_RETRIES` and
     * `POSTGRES_CONNECT_RETRY_DELAY` environment variables. The delay doubles
     * after each failed attempt to give the database more time to recover.
     */
    public static function connectFromEnv(int $retries = 5, int $delay = 1): PDO {
        if (self::$factory !== null) {
            $pdo = (self::$factory)();
            if (!$pdo instanceof PDO) {
                throw new RuntimeException('Database factory must return a PDO instance.');
            }

            return $pdo;
        }

        $envRetries = getenv('POSTGRES_CONNECT_RETRIES');
        if ($envRetries !== false) {
            $retries = (int) $envRetries;
        }

        $envDelay = getenv('POSTGRES_CONNECT_RETRY_DELAY');
        if ($envDelay !== false) {
            $delay = (int) $envDelay;
        }

        $dsn  = getenv('POSTGRES_DSN') ?: '';
        $user = getenv('POSTGRES_USER') ?: '';
        $pass = getenv('POSTGRES_PASSWORD') ?: '';
        $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];

        while (true) {
            try {
                return new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
                if (stripos($e->getMessage(), 'could not find driver') !== false) {
                    $driver = strtolower($dsn !== '' ? explode(':', $dsn, 2)[0] : '');
                    if ($driver === '') {
                        $driver = 'unknown';
                    }
                    $hint = 'Enable the corresponding PDO extension for the "' . $driver . '" driver';
                    if ($driver === 'pgsql') {
                        $hint .= ' (for example: install the "pdo_pgsql" extension).';
                    } else {
                        $hint .= '.';
                    }

                    throw new PDOException(
                        $hint . ' Current DSN: ' . ($dsn === '' ? '[empty]' : $dsn),
                        (int) $e->getCode(),
                        $e
                    );
                }

                if ($retries-- <= 0) {
                    throw $e;
                }

                sleep($delay);
                $delay *= 2; // exponential backoff
            }
        }
    }

    /**
     * Create a PDO connection and switch to the given schema.
     *
     * This method is intended for PostgreSQL databases and will always
     * execute a `SET search_path` statement to select the provided schema.
     */
    public static function connectWithSchema(string $schema): PDO {
        $pdo = self::connectFromEnv();
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql') {
            $quotedSchema = $pdo->quote($schema);

            if ($schema === 'public') {
                $pdo->exec('SET search_path TO ' . $quotedSchema);
            } else {
                $pdo->exec(sprintf('SET search_path TO %s, public', $quotedSchema));
            }
        }

        if (self::$connectHook !== null) {
            (self::$connectHook)($schema, $pdo);
        }

        return $pdo;
    }

    /**
     * Override the connection factory for testing purposes.
     */
    public static function setFactory(?callable $factory): void {
        self::$factory = $factory;
    }

    /**
     * Attach a hook that receives every schema-specific connection.
     */
    public static function setConnectHook(?callable $hook): void {
        self::$connectHook = $hook;
    }
}
