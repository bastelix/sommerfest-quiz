<?php

declare(strict_types=1);

namespace App\Service;

use App\Infrastructure\Database;
use PDO;

class PhotoConsentService
{
    private string $path;
    private PDO $pdo;

    public function __construct(string $path)
    {
        $this->path = $path;
        $this->pdo = Database::connect();
    }

    public function add(string $team, int $time): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO photo_consents(team,time) VALUES(?,?)');
        $stmt->execute([$team, $time]);
    }
}
