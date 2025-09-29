<?php

declare(strict_types=1);

namespace App\Service;

use PDO;
use App\Service\ConfigService;

/**
 * Stores and retrieves photo consent confirmations.
 */
class PhotoConsentService
{
    private PDO $pdo;
    private ConfigService $config;

    /**
     * Inject database connection.
     */
    public function __construct(PDO $pdo, ConfigService $config) {
        $this->pdo = $pdo;
        $this->config = $config;
    }

    /**
     * Add a new photo consent entry.
     */
    public function add(string $team, int $time): void {
        $uid = $this->config->getActiveEventUid();
        $stmt = $this->pdo->prepare('INSERT INTO photo_consents(team,time,event_uid) VALUES(?,?,?)');
        $stmt->execute([$team, $time, $uid]);
    }

    /**
     * Retrieve all stored photo consents.
     *
     * @return array<int, array{team:string,time:int}>
     */
    public function getAll(): array {
        $uid = $this->config->getActiveEventUid();
        $sql = 'SELECT team,time FROM photo_consents';
        $params = [];
        if ($uid !== '') {
            $sql .= ' WHERE event_uid=?';
            $params[] = $uid;
        }
        $sql .= ' ORDER BY id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Replace all stored consents with the provided list.
     *
     * @param list<array<string, mixed>> $consents
     */
    public function saveAll(array $consents): void {
        $uid = $this->config->getActiveEventUid();
        $this->pdo->beginTransaction();
        if ($uid !== '') {
            $del = $this->pdo->prepare('DELETE FROM photo_consents WHERE event_uid=?');
            $del->execute([$uid]);
            $stmt = $this->pdo->prepare('INSERT INTO photo_consents(team,time,event_uid) VALUES(?,?,?)');
        } else {
            $this->pdo->exec('DELETE FROM photo_consents');
            $stmt = $this->pdo->prepare('INSERT INTO photo_consents(team,time) VALUES(?,?)');
        }
        foreach ($consents as $row) {
            $params = [
                (string)($row['team'] ?? ''),
                (int)($row['time'] ?? 0),
            ];
            if ($uid !== '') {
                $params[] = $uid;
            }
            $stmt->execute($params);
        }
        $this->pdo->commit();
    }
}
