<?php

declare(strict_types=1);

namespace App\Service;

use PDO;

/**
 * Service for managing quiz events.
 */
class EventService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Retrieve all events ordered by name.
     *
     * @return list<array{uid:string,name:string,date:?string,description:?string}>
     */
    public function getAll(): array
    {
        $stmt = $this->pdo->query('SELECT uid,name,date,description FROM events ORDER BY name');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Replace all events with the provided list.
     *
     * @param list<array{uid?:string,name:string,date?:string,description?:string}> $events
     */
    public function saveAll(array $events): void
    {
        $this->pdo->beginTransaction();
        $this->pdo->exec('DELETE FROM events');
        $stmt = $this->pdo->prepare('INSERT INTO events(uid,name,date,description) VALUES(?,?,?,?)');
        foreach ($events as $event) {
            $uid = $event['uid'] ?? bin2hex(random_bytes(16));
            $stmt->execute([
                $uid,
                (string)$event['name'],
                $event['date'] ?? null,
                $event['description'] ?? null,
            ]);
        }
        $this->pdo->commit();
    }
}
