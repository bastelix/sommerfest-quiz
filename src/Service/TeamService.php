<?php

declare(strict_types=1);

namespace App\Service;

use PDO;
use PDOException;
use App\Service\ConfigService;
use App\Service\TenantService;

/**
 * Service layer for managing quiz teams.
 */
class TeamService
{
    private PDO $pdo;
    private ConfigService $config;
    private ?TenantService $tenants;
    private string $subdomain;

    /**
     * Inject database connection.
     */
    public function __construct(
        PDO $pdo,
        ConfigService $config,
        ?TenantService $tenants = null,
        string $subdomain = ''
    ) {
        $this->pdo = $pdo;
        $this->config = $config;
        $this->tenants = $tenants;
        $this->subdomain = $subdomain;
    }


    /**
     * Retrieve the ordered list of teams.
     */
    public function getAll(): array
    {
        $uid = $this->config->getActiveEventUid();
        if ($uid === '') {
            return [];
        }
        $sql = 'SELECT name FROM teams WHERE event_uid=? ORDER BY sort_order';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$uid]);
        return array_map(static fn(array $row): string => (string) $row['name'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Retrieve the ordered list of teams for the given event UID.
     *
     * @return list<string>
     */
    public function getAllForEvent(string $eventUid): array
    {
        if ($eventUid === '') {
            return $this->getAll();
        }

        $sql = 'SELECT name FROM teams WHERE event_uid=? ORDER BY sort_order';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$eventUid]);

        return array_map(static fn(array $row): string => (string) $row['name'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Look up the owning event for the provided team name.
     */
    public function getEventUidByName(string $teamName): ?string
    {
        $normalized = trim($teamName);
        if ($normalized === '') {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT event_uid FROM teams WHERE name = ? LIMIT 1');
        $stmt->execute([$normalized]);
        $eventUid = $stmt->fetchColumn();

        if ($eventUid === false || $eventUid === null) {
            return null;
        }

        $eventUid = (string) $eventUid;
        return $eventUid !== '' ? $eventUid : null;
    }

    /**
     * Create a team with the given name if it does not exist yet.
     */
    public function addIfMissing(string $name): void {
        $uid = $this->config->getActiveEventUid();
        $sql = 'SELECT uid FROM teams WHERE name=?';
        $params = [$name];
        if ($uid !== '') {
            $sql .= ' AND event_uid=?';
            $params[] = $uid;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        if ($stmt->fetchColumn() !== false) {
            return;
        }

        if ($uid !== '') {
            $stmt = $this->pdo->prepare('SELECT COALESCE(MAX(sort_order),0) FROM teams WHERE event_uid=?');
            $stmt->execute([$uid]);
        } else {
            $stmt = $this->pdo->query('SELECT COALESCE(MAX(sort_order),0) FROM teams');
        }
        $sort = ((int) $stmt->fetchColumn()) + 1;
        $teamUid = bin2hex(random_bytes(16));

        if ($uid !== '') {
            $stmt = $this->pdo->prepare('INSERT INTO teams(uid,event_uid,sort_order,name) VALUES(?,?,?,?)');
            $stmt->execute([$teamUid, $uid, $sort, $name]);
        } else {
            $stmt = $this->pdo->prepare('INSERT INTO teams(uid,sort_order,name) VALUES(?,?,?)');
            $stmt->execute([$teamUid, $sort, $name]);
        }
    }

    /**
     * @param array<int, string> $teams
     */
    public function saveAll(array $teams): void {
        $uid = $this->config->getActiveEventUid();

        $teamCount = count($teams);
        if ($this->tenants !== null && $this->subdomain !== '') {
            $limits = $this->tenants->getLimitsBySubdomain($this->subdomain);
            $max = $limits['maxTeamsPerEvent'] ?? null;
            if ($max !== null && $teamCount > $max) {
                throw new \RuntimeException('max-teams-exceeded');
            }
        }

        $this->pdo->beginTransaction();
        if ($uid !== '') {
            $del = $this->pdo->prepare('DELETE FROM teams WHERE event_uid=?');
            $del->execute([$uid]);
            $stmt = $this->pdo->prepare(
                'INSERT INTO teams(uid,event_uid,sort_order,name) VALUES(?,?,?,?)'
            );
            foreach ($teams as $i => $name) {
                $teamUid = bin2hex(random_bytes(16));
                $stmt->execute([$teamUid, $uid, $i + 1, $name]);
            }
        } else {
            $this->pdo->exec('DELETE FROM teams');
            $stmt = $this->pdo->prepare('INSERT INTO teams(uid,sort_order,name) VALUES(?,?,?)');
            foreach ($teams as $i => $name) {
                $teamUid = bin2hex(random_bytes(16));
                $stmt->execute([$teamUid, $i + 1, $name]);
            }
        }
        $this->pdo->commit();
    }
}
