<?php

declare(strict_types=1);

namespace App\Infrastructure;

use PDO;
use PDOException;

/**
 * Utility class for creating database connections.
 */
class Database
{
    /**
     * Create a PDO connection using credentials from environment variables.
     *
     * The number of connection attempts and the initial delay between retries
     * can be overridden using the `POSTGRES_CONNECT_RETRIES` and
     * `POSTGRES_CONNECT_RETRY_DELAY` environment variables. The delay doubles
     * after each failed attempt to give the database more time to recover.
     */
    public static function connectFromEnv(int $retries = 5, int $delay = 1): PDO {
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
        $pass = getenv('POSTGRES_PASSWORD') ?: getenv('POSTGRES_PASS') ?: '';
        $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];

        while (true) {
            try {
                return new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
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
            $pdo->exec('SET search_path TO ' . $pdo->quote($schema));
        }

        return $pdo;
    }
}
