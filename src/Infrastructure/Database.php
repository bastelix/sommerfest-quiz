<?php

declare(strict_types=1);

namespace App\Infrastructure;

use PDO;

/**
 * Utility class for creating database connections.
 */
class Database
{
    /**
     * Create a PDO connection using credentials from environment variables.
     */
    public static function connectFromEnv(): PDO
    {
        $dsn  = getenv('POSTGRES_DSN') ?: '';
        $user = getenv('POSTGRES_USER') ?: '';
        $pass = getenv('POSTGRES_PASSWORD') ?: getenv('POSTGRES_PASS') ?: '';
        return new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }

    /**
     * Create a PDO connection and switch to the given schema.
     */
    public static function connectWithSchema(string $schema): PDO
    {
        $pdo = self::connectFromEnv();
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
            $stmt = $pdo->prepare('SET search_path TO :schema');
            $stmt->execute(['schema' => $schema]);
        }
        return $pdo;
    }
}

