<?php

declare(strict_types=1);

namespace App\Service;

use PDO;
use App\Service\ConfigService;
use App\Service\TenantService;
use App\Domain\Plan;

/**
 * Service for managing quiz events.
 */
class EventService
{
    private PDO $pdo;
    private ConfigService $config;
    private ?TenantService $tenants;
    private string $subdomain;

    public function __construct(
        PDO $pdo,
        ?ConfigService $config = null,
        ?TenantService $tenants = null,
        string $subdomain = ''
    ) {
        $this->pdo = $pdo;
        $this->config = $config ?? new ConfigService($pdo);
        $this->tenants = $tenants;
        $this->subdomain = $subdomain;
    }

    /**
     * Retrieve all events ordered by name.
     *
     * @param bool $publishedOnly When true, only published events are returned.
     * @return list<array{uid:string,name:string,start_date:?string,end_date:?string,description:?string,published:bool}>
     */
    public function getAll(bool $publishedOnly = false): array
    {
        $sql = 'SELECT uid,name,start_date,end_date,description,published FROM events';
        if ($publishedOnly) {
            $sql .= ' WHERE published = TRUE';
        }
        $sql .= ' ORDER BY name';
        $stmt = $this->pdo->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(function (array $row) {
            $row['start_date'] = $this->formatDate($row['start_date']);
            $row['end_date'] = $this->formatDate($row['end_date']);
            $row['published'] = (bool)($row['published'] ?? false);
            return $row;
        }, $rows);
    }

    /**
     * Replace all events with the provided list.
     *
     * @param list<array{
     *     uid?:string,
     *     name:string,
     *     start_date?:string,
     *     end_date?:string,
     *     description?:string,
     *     published?:bool
     * }> $events
     */
    public function saveAll(array $events): void
    {
        $existingStmt = $this->pdo->query('SELECT uid FROM events');
        $existing = $existingStmt->fetchAll(PDO::FETCH_COLUMN);

        if ($this->tenants !== null && $this->subdomain !== '') {
            $plan = $this->tenants->getPlanBySubdomain($this->subdomain);
            if ($plan !== null) {
                $limits = Plan::limits($plan);
                $max = $limits['maxEvents'] ?? null;
                if ($max !== null) {
                    $currentCount = count($existing);
                    $newCount = 0;
                    foreach ($events as $event) {
                        $uid = $event['uid'] ?? null;
                        if ($uid === null || !in_array($uid, $existing, true)) {
                            $newCount++;
                        }
                    }
                    if ($currentCount + $newCount > $max) {
                        throw new \RuntimeException('max-events-exceeded');
                    }
                }
            }
        }

        $this->pdo->beginTransaction();

        $updateStmt = $this->pdo->prepare(
            'UPDATE events SET name = ?, start_date = ?, end_date = ?, description = ?, published = ? WHERE uid = ?'
        );
        $insertStmt = $this->pdo->prepare(
            'INSERT INTO events(uid,name,start_date,end_date,description,published) VALUES(?,?,?,?,?,?)'
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
            $published = (bool)($event['published'] ?? false);

            if (in_array($uid, $existing, true)) {
                $updateStmt->execute([$name, $start, $end, $desc, $published, $uid]);
            } else {
                $insertStmt->execute([$uid, $name, $start, $end, $desc, $published]);
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

        $countStmt = $this->pdo->query('SELECT uid FROM events LIMIT 2');
        $eventUids = $countStmt->fetchAll(PDO::FETCH_COLUMN);
        if (count($eventUids) === 1) {
            $this->config->setActiveEventUid((string) $eventUids[0]);
        }
    }

    /**
     * Update the published state of an event.
     */
    public function setPublished(string $uid, bool $published): void
    {
        $stmt = $this->pdo->prepare('UPDATE events SET published = ? WHERE uid = ?');
        $stmt->execute([$published, $uid]);
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
        if ($row === false) {
            return null;
        }
        $row['start_date'] = $this->formatDate($row['start_date']);
        $row['end_date'] = $this->formatDate($row['end_date']);
        return $row;
    }

    /**
     * Retrieve a specific event by its UID.
     */
    public function getByUid(string $uid): ?array
    {
        $stmt = $this->pdo->prepare('SELECT uid,name,start_date,end_date,description FROM events WHERE uid = ?');
        $stmt->execute([$uid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row !== false) {
            $row['start_date'] = $this->formatDate($row['start_date']);
            $row['end_date'] = $this->formatDate($row['end_date']);
            return $row;
        }

        $path = dirname(__DIR__, 2) . '/data/events.json';
        if (is_readable($path)) {
            $json = json_decode(file_get_contents($path), true);
            if (is_array($json)) {
                foreach ($json as $event) {
                    if ((string)($event['uid'] ?? '') === $uid) {
                        return [
                            'uid' => (string) $event['uid'],
                            'name' => (string) $event['name'],
                            'start_date' => $event['start_date'] ?? null,
                            'end_date' => $event['end_date'] ?? null,
                            'description' => $event['description'] ?? null,
                        ];
                    }
                }
            }
        }

        return null;
    }

    private function formatDate(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }
        try {
            $dt = new \DateTime($value);
            return $dt->format('Y-m-d\TH:i');
        } catch (\Exception $e) {
            return $value;
        }
    }
}
