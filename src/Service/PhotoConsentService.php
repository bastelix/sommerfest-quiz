<?php

declare(strict_types=1);

namespace App\Service;

use PDO;

class PhotoConsentService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function add(string $team, int $time): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO photo_consents(team,time) VALUES(?,?)');
        $stmt->execute([$team, $time]);
    }

    /**
     * Retrieve all stored photo consents.
     *
     * @return array<int, array{team:string,time:int}>
     */
    public function getAll(): array
    {
        $stmt = $this->pdo->query('SELECT team,time FROM photo_consents ORDER BY id');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
