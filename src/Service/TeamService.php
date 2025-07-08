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

    /**
     * Inject database connection.
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Retrieve the active event UID.
     */
    private function activeEventUid(): string
    {
        try {
            $stmt = $this->pdo->query('SELECT activeEventUid FROM config LIMIT 1');
            $uid = $stmt->fetchColumn();
            return $uid === false ? '' : (string)$uid;
        } catch (PDOException $e) {
            return '';
        }
    }

    /**
     * Retrieve the ordered list of teams.
     */
    public function getAll(): array
    {
        $uid = $this->activeEventUid();
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
        $uid = $this->activeEventUid();
        $this->pdo->beginTransaction();
        if ($uid !== '') {
            $del = $this->pdo->prepare('DELETE FROM teams WHERE event_uid=?');
            $del->execute([$uid]);
            $stmt = $this->pdo->prepare('INSERT INTO teams(event_uid,sort_order,name) VALUES(?,?,?)');
            foreach ($teams as $i => $name) {
                $stmt->execute([$uid, $i + 1, $name]);
            }
        } else {
            $this->pdo->exec('DELETE FROM teams');
            $stmt = $this->pdo->prepare('INSERT INTO teams(sort_order,name) VALUES(?,?)');
            foreach ($teams as $i => $name) {
                $stmt->execute([$i + 1, $name]);
            }
        }
        $this->pdo->commit();
    }
}
