<?php

declare(strict_types=1);

namespace App\Service;

use PDO;

/**
 * Service for persisting player information.
 */
class PlayerService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Store a player's data in the database.
     */
    public function save(string $eventUid, string $playerName, string $playerUid): void
    {
        if ($eventUid === '' || $playerName === '' || $playerUid === '') {
            return;
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO players(event_uid, player_name, player_uid) VALUES(?,?,?) ON CONFLICT DO NOTHING'
        );
        $stmt->execute([$eventUid, $playerName, $playerUid]);
    }
}
