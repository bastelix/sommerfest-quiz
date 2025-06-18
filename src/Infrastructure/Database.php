<?php
declare(strict_types=1);

namespace App\Infrastructure;

use PDO;

class Database
{
    public static function connectFromEnv(): PDO
    {
        $dsn  = getenv('POSTGRES_DSN') ?: '';
        $user = getenv('POSTGRES_USER') ?: '';
        $pass = getenv('POSTGRES_PASSWORD') ?: getenv('POSTGRES_PASS') ?: '';
        return new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    }
}
