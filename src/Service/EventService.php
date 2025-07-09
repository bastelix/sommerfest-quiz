<?php

declare(strict_types=1);

namespace App\Service;

use PDO;
use App\Service\ConfigService;

/**
 * Service for managing quiz events.
 */
class EventService
{
    private PDO $pdo;
    private ConfigService $config;

    public function __construct(PDO $pdo, ?ConfigService $config = null)
    {
        $this->pdo = $pdo;
        $this->config = $config ?? new ConfigService($pdo);
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
                (string) $event['name'],
                $event['date'] ?? null,
                $event['description'] ?? null,
            ]);
            $this->config->ensureConfigForEvent($uid);
        }
        $this->pdo->commit();
    }

    /**
     * Return the first event or null if none exist.
     *
     * @return array{uid:string,name:string,date:?string,description:?string}|null
     */
    public function getFirst(): ?array
    {
        $stmt = $this->pdo->query('SELECT uid,name,date,description FROM events ORDER BY name LIMIT 1');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * Retrieve a specific event by its UID.
     */
    public function getByUid(string $uid): ?array
    {
        $stmt = $this->pdo->prepare('SELECT uid,name,date,description FROM events WHERE uid = ?');
        $stmt->execute([$uid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }
}
