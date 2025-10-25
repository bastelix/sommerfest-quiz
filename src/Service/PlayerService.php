<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\PlayerNameConflictException;
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
        $normalizedName = $this->normalizeName($playerName);
        if ($eventUid === '' || $playerUid === '' || $normalizedName === '') {
            return;
        }

        $existingName = $this->findName($eventUid, $playerUid);
        $previousCanonical = $existingName !== null ? $this->canonicalizeName($existingName) : '';
        $nextCanonical = $this->canonicalizeName($normalizedName);

        if ($previousCanonical === '' || $previousCanonical !== $nextCanonical) {
            if ($this->isNameTaken($eventUid, $normalizedName, $playerUid)) {
                throw new PlayerNameConflictException('Player name already in use.');
            }
        }

        $this->pdo->beginTransaction();

        try {
            if ($updateContact) {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO players(event_uid, player_name, player_uid, contact_email, consent_granted_at)'
                    . ' VALUES(?,?,?,?,?)'
                    . ' ON CONFLICT (event_uid, player_uid) DO UPDATE SET'
                    . '     player_name = EXCLUDED.player_name,'
                    . '     contact_email = EXCLUDED.contact_email,'
                    . '     consent_granted_at = EXCLUDED.consent_granted_at'
                );
                $stmt->execute([
                    $eventUid,
                    $normalizedName,
                    $playerUid,
                    $contactEmail,
                    $consentGrantedAt?->format(DateTimeInterface::ATOM),
                ]);
            } else {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO players(event_uid, player_name, player_uid) VALUES(?,?,?)'
                    . ' ON CONFLICT (event_uid, player_uid) DO UPDATE SET player_name = EXCLUDED.player_name'
                );
                $stmt->execute([$eventUid, $normalizedName, $playerUid]);
            }

            if ($existingName !== null && $existingName !== $normalizedName) {
                $this->renameResults($eventUid, $existingName, $normalizedName);
            }

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();

            throw $exception;
        }
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

    private function normalizeName(string $name): string {
        $trimmed = trim($name);
        if ($trimmed === '') {
            return '';
        }

        $fallback = mb_substr($trimmed, 0, 100, 'UTF-8');

        $sanitized = preg_replace('/[\x00-\x1F<>]/u', '', $trimmed);
        if ($sanitized === null) {
            $sanitized = $trimmed;
        }

        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($sanitized, \Normalizer::FORM_KC);
            if ($normalized !== false) {
                $sanitized = $normalized;
            }
        }

        $filtered = preg_replace('/[^\p{L}\p{N}\p{M}\p{Zs}\p{P}]/u', '', $sanitized);
        if ($filtered !== null && $filtered !== '') {
            $sanitized = $filtered;
        }

        $sanitized = trim($sanitized);
        if ($sanitized === '') {
            return $fallback;
        }

        if (mb_strlen($sanitized, 'UTF-8') > 100) {
            return mb_substr($sanitized, 0, 100, 'UTF-8');
        }

        return $sanitized;
    }

    private function canonicalizeName(string $name): string {
        $normalized = $this->normalizeName($name);
        if ($normalized === '') {
            return '';
        }

        return mb_strtolower($normalized, 'UTF-8');
    }

    private function isNameTaken(string $eventUid, string $playerName, string $excludeUid): bool {
        $stmt = $this->pdo->prepare(
            'SELECT player_uid FROM players WHERE event_uid = ? AND LOWER(player_name) = LOWER(?) LIMIT 1'
        );
        $stmt->execute([$eventUid, $playerName]);
        $existing = $stmt->fetchColumn();
        if ($existing !== false && (string) $existing !== $excludeUid) {
            return true;
        }

        $resultStmt = $this->pdo->prepare(
            'SELECT 1 FROM results WHERE event_uid = ? AND LOWER(name) = LOWER(?) LIMIT 1'
        );
        $resultStmt->execute([$eventUid, $playerName]);
        if ($resultStmt->fetchColumn() !== false) {
            return true;
        }

        $questionStmt = $this->pdo->prepare(
            'SELECT 1 FROM question_results WHERE event_uid = ? AND LOWER(name) = LOWER(?) LIMIT 1'
        );
        $questionStmt->execute([$eventUid, $playerName]);

        return $questionStmt->fetchColumn() !== false;
    }

    private function renameResults(string $eventUid, string $oldName, string $newName): void {
        if ($oldName === '' || $newName === '') {
            return;
        }

        $oldCanonical = $this->canonicalizeName($oldName);
        $newCanonical = $this->canonicalizeName($newName);

        if ($oldCanonical === '' || $newCanonical === '') {
            return;
        }

        $this->updateStoredNames('results', $eventUid, $oldCanonical, $newName);
        $this->updateStoredNames('question_results', $eventUid, $oldCanonical, $newName);
    }

    private function updateStoredNames(string $table, string $eventUid, string $oldCanonical, string $newName): void {
        $query = sprintf('SELECT DISTINCT name, event_uid FROM %s', $table);
        $params = [];
        if ($eventUid !== '') {
            $query .= ' WHERE event_uid = ? OR event_uid IS NULL';
            $params[] = $eventUid;
        } else {
            $query .= ' WHERE event_uid IS NULL';
        }

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        /** @var list<array{name:mixed, event_uid:mixed}> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($rows === []) {
            return;
        }

        $updateWithEvent = null;
        if ($eventUid !== '') {
            $updateWithEvent = $this->pdo->prepare(
                sprintf('UPDATE %s SET name = ? WHERE name = ? AND event_uid = ?', $table)
            );
        }
        $updateWithoutEvent = $this->pdo->prepare(
            sprintf('UPDATE %s SET name = ? WHERE name = ? AND event_uid IS NULL', $table)
        );

        foreach ($rows as $row) {
            $storedName = isset($row['name']) ? (string) $row['name'] : '';
            if ($storedName === '') {
                continue;
            }

            if ($this->canonicalizeName($storedName) !== $oldCanonical) {
                continue;
            }

            $storedEventUid = $row['event_uid'] !== null ? (string) $row['event_uid'] : null;
            if ($storedEventUid === null || $storedEventUid === '') {
                $updateWithoutEvent->execute([$newName, $storedName]);
                continue;
            }

            if ($updateWithEvent !== null) {
                $updateWithEvent->execute([$newName, $storedName, $storedEventUid]);
            }
        }
    }
}
