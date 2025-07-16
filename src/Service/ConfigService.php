<?php

declare(strict_types=1);

namespace App\Service;

use PDO;

/**
 * Handles reading and writing application configuration values.
 */
class ConfigService
{
    private PDO $pdo;
    private ?string $activeEvent = null;

    /**
     * Inject PDO instance used for database operations.
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS active_event(' .
            'event_uid TEXT PRIMARY KEY' .
            ')'
        );
    }

    /**
     * Retrieve configuration as pretty printed JSON.
     */
    public function getJson(): ?string
    {
        $uid = $this->getActiveEventUid();
        $row = null;
        if ($uid !== '') {
            $stmt = $this->pdo->prepare('SELECT * FROM config WHERE event_uid = ? LIMIT 1');
            $stmt->execute([$uid]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        if ($row === null) {
            $stmt = $this->pdo->query('SELECT * FROM config LIMIT 1');
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        if ($row === null) {
            return null;
        }
        $row = $this->normalizeKeys($row);
        return json_encode($row, JSON_PRETTY_PRINT);
    }

    /**
     * Retrieve configuration JSON for a specific event UID.
     */
    public function getJsonForEvent(string $uid): ?string
    {
        $stmt = $this->pdo->prepare('SELECT * FROM config WHERE event_uid = ? LIMIT 1');
        $stmt->execute([$uid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($row === null) {
            return null;
        }
        $row = $this->normalizeKeys($row);
        return json_encode($row, JSON_PRETTY_PRINT);
    }

    /**
     * Return configuration values as an associative array.
     */
    public function getConfig(): array
    {
        $uid = $this->getActiveEventUid();
        $row = null;
        if ($uid !== '') {
            $stmt = $this->pdo->prepare('SELECT * FROM config WHERE event_uid = ? LIMIT 1');
            $stmt->execute([$uid]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        if ($row === null) {
            $stmt = $this->pdo->query('SELECT * FROM config LIMIT 1');
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        if ($row !== null) {
            return $this->normalizeKeys($row);
        }

        $path = dirname(__DIR__, 2) . '/data/config.json';
        if (is_readable($path)) {
            $json = json_decode(file_get_contents($path), true);
            if (is_array($json)) {
                return $json;
            }
        }

        return [];
    }

    /**
     * Return configuration for the given event UID or an empty array if none exists.
     */
    public function getConfigForEvent(string $uid): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM config WHERE event_uid = ? LIMIT 1');
        $stmt->execute([$uid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($row !== null) {
            return $this->normalizeKeys($row);
        }
        return [];
    }

    /**
     * Remove puzzle word fields from the configuration array.
     *
     * @param array<string,mixed> $cfg
     * @return array<string,mixed>
     */
    public static function removePuzzleInfo(array $cfg): array
    {
        unset($cfg['puzzleWord'], $cfg['puzzleFeedback']);
        return $cfg;
    }

    /**
     * Replace stored configuration with new values.
     */
    public function saveConfig(array $data): void
    {
        $keys = [
            'displayErrorDetails',
            'QRUser',
            'QRRemember',
            'logoPath',
            'pageTitle',
            'backgroundColor',
            'buttonColor',
            'CheckAnswerButton',
            'QRRestrict',
            'competitionMode',
            'teamResults',
            'photoUpload',
            'puzzleWordEnabled',
            'puzzleWord',
            'puzzleFeedback',
            'inviteText',
            'event_uid',
        ];
        $filtered = array_intersect_key($data, array_flip($keys));
        $uid = (string)($filtered['event_uid'] ?? $this->getActiveEventUid());
        $filtered['event_uid'] = $uid;

        $this->pdo->beginTransaction();
        $check = $this->pdo->prepare('SELECT 1 FROM config WHERE event_uid=?');
        $check->execute([$uid]);

        if ($check->fetchColumn()) {
            $sets = [];
            foreach (array_keys($filtered) as $k) {
                $sets[] = "$k=:$k";
            }
            $sql = 'UPDATE config SET ' . implode(',', $sets) . ' WHERE event_uid=:event_uid';
        } else {
            $cols = array_keys($filtered);
            $params = ':' . implode(', :', $cols);
            $sql = 'INSERT INTO config(' . implode(',', $cols) . ') VALUES(' . $params . ')';
        }
        $stmt = $this->pdo->prepare($sql);
        foreach ($filtered as $k => $v) {
            if (is_bool($v)) {
                $stmt->bindValue(':' . $k, $v, PDO::PARAM_BOOL);
            } else {
                $stmt->bindValue(':' . $k, $v);
            }
        }
        $stmt->execute();
        $this->pdo->commit();

        $this->setActiveEventUid($uid);
    }

    /**
     * Update the UID of the currently active event.
     */
    public function setActiveEventUid(string $uid): void
    {
        $this->pdo->beginTransaction();
        $this->pdo->exec('DELETE FROM active_event');
        if ($uid !== '') {
            $stmt = $this->pdo->prepare('INSERT INTO active_event(event_uid) VALUES(?)');
            $stmt->execute([$uid]);
        }
        $this->pdo->commit();
        $this->ensureConfigForEvent($uid);
        $this->activeEvent = $uid;
    }

    /**
     * Ensure a configuration row exists for the given event UID.
     */
    public function ensureConfigForEvent(string $uid): void
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM config WHERE event_uid = ? LIMIT 1');
        $stmt->execute([$uid]);
        if ($stmt->fetchColumn() === false) {
            $insert = $this->pdo->prepare('INSERT INTO config(event_uid) VALUES(?)');
            $insert->execute([$uid]);
        }
    }

    /**
     * Return the UID of the currently active event or an empty string.
     */
    public function getActiveEventUid(): string
    {
        if ($this->activeEvent !== null) {
            return $this->activeEvent;
        }
        $stmt = $this->pdo->query('SELECT event_uid FROM active_event LIMIT 1');
        $uid = $stmt->fetchColumn();
        if ($uid === false || $uid === null || $uid === '') {
            $stmt = $this->pdo->query('SELECT event_uid FROM config LIMIT 1');
            $uid = $stmt->fetchColumn();
            if ($uid === false || $uid === null || $uid === '') {
                $path = dirname(__DIR__, 2) . '/data/config.json';
                if (is_readable($path)) {
                    $json = json_decode(file_get_contents($path), true);
                    if (is_array($json) && isset($json['event_uid'])) {
                        $uid = $json['event_uid'];
                    }
                }
            }
        }
        $this->activeEvent = $uid !== false && $uid !== null ? (string)$uid : '';
        return $this->activeEvent;
    }

    /**
     * Normalize database column names to expected camelCase keys.
     *
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function normalizeKeys(array $row): array
    {
        $keys = [
            'displayErrorDetails',
            'QRUser',
            'QRRemember',
            'logoPath',
            'pageTitle',
            'backgroundColor',
            'buttonColor',
            'CheckAnswerButton',
            'QRRestrict',
            'competitionMode',
            'teamResults',
            'photoUpload',
            'puzzleWordEnabled',
            'puzzleWord',
            'puzzleFeedback',
            'inviteText',
            'event_uid',
        ];
        $map = [];
        foreach ($keys as $k) {
            $map[strtolower($k)] = $k;
        }
        $normalized = [];
        foreach ($row as $k => $v) {
            $normalized[$map[strtolower($k)] ?? $k] = $v;
        }
        return $normalized;
    }
}
