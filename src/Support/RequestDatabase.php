<?php

declare(strict_types=1);

namespace App\Support;

use App\Infrastructure\Database;
use PDO;
use Psr\Http\Message\ServerRequestInterface as Request;

class RequestDatabase
{
    /**
     * Resolve the PDO instance from the request or fall back to environment config.
     */
    public static function resolve(Request $request): PDO
    {
        $pdo = $request->getAttribute('pdo');

        return $pdo instanceof PDO ? $pdo : Database::connectFromEnv();
    }
}
