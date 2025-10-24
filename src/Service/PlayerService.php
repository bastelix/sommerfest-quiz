<?php

declare(strict_types=1);

namespace App\Service;

use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use PDO;

/**
 * Service for persisting player information.
 */
class PlayerService
{
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Store a player's data in the database.
     */
    public function save(
        string $eventUid,
        string $playerName,
        string $playerUid,
        ?string $contactEmail = null,
        ?DateTimeImmutable $consentGrantedAt = null,
        bool $updateContact = false
    ): void {
        if ($eventUid === '' || $playerName === '' || $playerUid === '') {
            return;
        }

        if ($updateContact) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO players(event_uid, player_name, player_uid, contact_email, consent_granted_at)
                VALUES(?,?,?,?,?)
                ON CONFLICT (event_uid, player_uid) DO UPDATE SET
                    player_name = EXCLUDED.player_name,
                    contact_email = EXCLUDED.contact_email,
                    consent_granted_at = EXCLUDED.consent_granted_at'
            );
            $stmt->execute([
                $eventUid,
                $playerName,
                $playerUid,
                $contactEmail,
                $consentGrantedAt?->format(DateTimeInterface::ATOM),
            ]);

            return;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO players(event_uid, player_name, player_uid) VALUES(?,?,?)
            ON CONFLICT (event_uid, player_uid) DO UPDATE SET player_name = EXCLUDED.player_name'
        );
        $stmt->execute([$eventUid, $playerName, $playerUid]);
    }

    /**
     * Retrieve player information by event and UID.
     *
     * @return array{player_name: string, contact_email: ?string, consent_granted_at: ?string}|null
     */
    public function find(string $eventUid, string $playerUid): ?array {
        if ($eventUid === '' || $playerUid === '') {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT player_name, contact_email, consent_granted_at FROM players WHERE event_uid = ? AND player_uid = ?'
        );
        $stmt->execute([$eventUid, $playerUid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        $consentAt = $row['consent_granted_at'];
        if ($consentAt !== null) {
            try {
                $consentAt = (new DateTimeImmutable((string) $consentAt))->format(DateTimeInterface::ATOM);
            } catch (Exception $exception) {
                // keep original representation when parsing fails
                $consentAt = (string) $row['consent_granted_at'];
            }
        }

        return [
            'player_name' => (string) $row['player_name'],
            'contact_email' => $row['contact_email'] !== null ? (string) $row['contact_email'] : null,
            'consent_granted_at' => $consentAt !== null ? (string) $consentAt : null,
        ];
    }

    /**
     * Retrieve a player's name by event and UID.
     */
    public function findName(string $eventUid, string $playerUid): ?string {
        $player = $this->find($eventUid, $playerUid);

        return $player['player_name'] ?? null;
    }
}
