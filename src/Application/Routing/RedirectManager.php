<?php

declare(strict_types=1);

namespace App\Application\Routing;

use App\Infrastructure\Database;
use PDO;

/**
 * Stores and registers HTTP redirects.
 */
class RedirectManager
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null) {
        $this->pdo = $pdo ?? Database::connectFromEnv();
    }

    /**
     * Register a redirect from one path or URL to another.
     */
    public function register(string $from, string $to, int $status = 301): void {
        $stmt = $this->pdo->prepare('INSERT INTO redirects(source, target, status) VALUES(?,?,?)');
        $stmt->execute([$from, $to, $status]);
    }
}
