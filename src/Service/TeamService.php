<?php

declare(strict_types=1);

namespace App\Service;

use PDO;

/**
 * Service layer for managing quiz teams.
 */
class TeamService
{
    private PDO $pdo;

    /**
     * Inject database connection.
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Retrieve the ordered list of teams.
     */
    public function getAll(): array
    {
        $stmt = $this->pdo->query('SELECT name FROM teams ORDER BY sort_order');
        return array_map(fn($r) => $r['name'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * @param array<int, string> $teams
     */
    public function saveAll(array $teams): void
    {
        $this->pdo->beginTransaction();
        $this->pdo->exec('DELETE FROM teams');
        $stmt = $this->pdo->prepare('INSERT INTO teams(sort_order,name) VALUES(?,?)');
        foreach ($teams as $i => $name) {
            $stmt->execute([$i + 1, $name]);
        }
        $this->pdo->commit();
    }
}
