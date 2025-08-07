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
        $sql = 'SELECT name FROM teams';
        $params = [];
        if ($uid !== '') {
            $sql .= ' WHERE event_uid=?';
            $params[] = $uid;
        }
        $sql .= ' ORDER BY sort_order';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return array_map(fn($r) => $r['name'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * @param array<int, string> $teams
     */
    public function saveAll(array $teams): void
    {
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
