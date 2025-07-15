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
     * @return list<array{uid:string,name:string,start_date:?string,end_date:?string,description:?string}>
     */
    public function getAll(): array
    {
        $stmt = $this->pdo->query('SELECT uid,name,start_date,end_date,description FROM events ORDER BY name');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Replace all events with the provided list.
     *
     * @param list<array{uid?:string,name:string,start_date?:string,end_date?:string,description?:string}> $events
     */
    public function saveAll(array $events): void
    {
        $this->pdo->beginTransaction();

        $existingStmt = $this->pdo->query('SELECT uid FROM events');
        $existing = $existingStmt->fetchAll(PDO::FETCH_COLUMN);

        $updateStmt = $this->pdo->prepare(
            'UPDATE events SET name = ?, start_date = ?, end_date = ?, description = ? WHERE uid = ?'
        );
        $insertStmt = $this->pdo->prepare(
            'INSERT INTO events(uid,name,start_date,end_date,description) VALUES(?,?,?,?,?)'
        );
        $uids = [];
        foreach ($events as $event) {
            $uid = $event['uid'] ?? bin2hex(random_bytes(16));
            $uids[] = $uid;
            $name = (string) $event['name'];
            $start = $event['start_date'] ?? '';
            if ($start === '') {
                $start = date('Y-m-d\TH:i');
            }
            $end = $event['end_date'] ?? '';
            if ($end === '') {
                $end = date('Y-m-d\TH:i');
            }
            $desc = $event['description'] ?? null;

            if (in_array($uid, $existing, true)) {
                $updateStmt->execute([$name, $start, $end, $desc, $uid]);
            } else {
                $insertStmt->execute([$uid, $name, $start, $end, $desc]);
                $this->config->ensureConfigForEvent($uid);
            }
        }

        if ($uids) {
            $placeholders = implode(',', array_fill(0, count($uids), '?'));
            $delStmt = $this->pdo->prepare("DELETE FROM events WHERE uid NOT IN ($placeholders)");
            $delStmt->execute($uids);
        } else {
            $this->pdo->exec('DELETE FROM events');
        }

        $this->pdo->commit();
    }

    /**
     * Return the first event or null if none exist.
     *
     * @return array{uid:string,name:string,start_date:?string,end_date:?string,description:?string}|null
     */
    public function getFirst(): ?array
    {
        $stmt = $this->pdo->query('SELECT uid,name,start_date,end_date,description FROM events ORDER BY name LIMIT 1');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * Retrieve a specific event by its UID.
     */
    public function getByUid(string $uid): ?array
    {
        $stmt = $this->pdo->prepare('SELECT uid,name,start_date,end_date,description FROM events WHERE uid = ?');
        $stmt->execute([$uid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }
}
