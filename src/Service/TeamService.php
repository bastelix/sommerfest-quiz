<?php

declare(strict_types=1);

namespace App\Service;

use PDO;

class TeamService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getAll(): array
    {
        $stmt = $this->pdo->query('SELECT name FROM teams ORDER BY id');
        return array_map(static fn($r) => $r['name'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * @param array<int, string> $teams
     */
    public function saveAll(array $teams): void
    {
        $this->pdo->beginTransaction();
        $this->pdo->exec('DELETE FROM teams');
        $stmt = $this->pdo->prepare('INSERT INTO teams(name) VALUES(?)');
        foreach ($teams as $name) {
            $stmt->execute([(string)$name]);
        }
        $this->pdo->commit();
    }
}
