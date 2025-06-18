<?php
declare(strict_types=1);

namespace App\Infrastructure;

use PDO;
use RuntimeException;

class Database
{
    public static function connect(): PDO
    {
        $dsn  = getenv('POSTGRES_DSN');
        $user = getenv('POSTGRES_USER');
        $pass = getenv('POSTGRES_PASSWORD') ?: getenv('POSTGRES_PASS');

        if ($dsn === false || $user === false) {
            throw new RuntimeException('PostgreSQL connection parameters missing');
        }

        return new PDO($dsn, $user, $pass ?: null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }
}
