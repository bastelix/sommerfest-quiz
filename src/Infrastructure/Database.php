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
     */
    public static function connectFromEnv(int $retries = 5, int $delay = 1): PDO
    {
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
            }
        }
    }

    /**
     * Create a PDO connection and switch to the given schema.
     */
    public static function connectWithSchema(string $schema): PDO
    {
        $pdo = self::connectFromEnv();
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
            $pdo->exec('SET search_path TO ' . $pdo->quote($schema));
        }
        return $pdo;
    }
}
