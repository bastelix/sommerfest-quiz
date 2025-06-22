<?php

declare(strict_types=1);

namespace App\Service;

use PDO;

/**
 * Stores and retrieves photo consent confirmations.
 */
class PhotoConsentService
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
     * Add a new photo consent entry.
     */
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

    /**
     * Replace all stored consents with the provided list.
     *
     * @param list<array<string, mixed>> $consents
     */
    public function saveAll(array $consents): void
    {
        $this->pdo->beginTransaction();
        $this->pdo->exec('DELETE FROM photo_consents');
        $stmt = $this->pdo->prepare('INSERT INTO photo_consents(team,time) VALUES(?,?)');
        foreach ($consents as $row) {
            $stmt->execute([
                (string)($row['team'] ?? ''),
                (int)($row['time'] ?? 0),
            ]);
        }
        $this->pdo->commit();
    }
}
