<?php

declare(strict_types=1);

namespace App\Service;

use PDO;
use PDOException;
use App\Service\ConfigService;

/**
 * Service layer for managing quiz teams.
 */
class TeamService
{
    private PDO $pdo;
    private ConfigService $config;

    /**
     * Inject database connection.
     */
    public function __construct(PDO $pdo, ConfigService $config)
    {
        $this->pdo = $pdo;
        $this->config = $config;
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
