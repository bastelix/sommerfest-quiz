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

    /**
     * Inject PDO instance used for database operations.
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;

    }

    /**
     * Retrieve configuration as pretty printed JSON.
     */
    public function getJson(): ?string
    {
        $stmt = $this->pdo->query('SELECT * FROM config LIMIT 1');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
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
        $stmt = $this->pdo->query('SELECT * FROM config LIMIT 1');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
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
        $keys = ['displayErrorDetails','QRUser','logoPath','pageTitle','backgroundColor','buttonColor','CheckAnswerButton','adminUser','adminPass','QRRestrict','competitionMode','teamResults','photoUpload','puzzleWordEnabled','puzzleWord','puzzleFeedback','inviteText','event_uid'];
        $filtered = array_intersect_key($data, array_flip($keys));
        $this->pdo->beginTransaction();
        $this->pdo->exec('DELETE FROM config');
        if ($filtered) {
            $cols = array_keys($filtered);
            $params = ':' . implode(', :', $cols);
            $sql = 'INSERT INTO config(' . implode(',', $cols) . ') VALUES(' . $params . ')';
            $stmt = $this->pdo->prepare($sql);
            foreach ($filtered as $k => $v) {
                if (is_bool($v)) {
                    $stmt->bindValue(':' . $k, $v, PDO::PARAM_BOOL);
                } else {
                    $stmt->bindValue(':' . $k, $v);
                }
            }
            $stmt->execute();
        }
        $this->pdo->commit();
    }

    /**
     * Update the UID of the currently active event.
     */
    public function setActiveEventUid(string $uid): void
    {
        $cfg = $this->getConfig();
        $cfg['event_uid'] = $uid;
        $this->saveConfig($cfg);
    }

    /**
     * Return the UID of the currently active event or an empty string.
     */
    public function getActiveEventUid(): string
    {
        $cfg = $this->getConfig();
        return (string)($cfg['event_uid'] ?? '');
    }

    /**
     * Normalize database column names to expected camelCase keys.
     *
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function normalizeKeys(array $row): array
    {
        $keys = ['displayErrorDetails','QRUser','logoPath','pageTitle','backgroundColor','buttonColor','CheckAnswerButton','adminUser','adminPass','QRRestrict','competitionMode','teamResults','photoUpload','puzzleWordEnabled','puzzleWord','puzzleFeedback','inviteText','event_uid'];
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
