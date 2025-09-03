<?php

declare(strict_types=1);

namespace App\Service;

use JsonException;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Handles reading and writing application configuration values.
 */
class ConfigService
{
    private PDO $pdo;
    private ?string $activeEvent = null;

    /**
     * List of configuration keys that should be treated as booleans.
     *
     * @var array<int,string>
     */
    private const BOOL_KEYS = [
        'displayErrorDetails',
        'QRUser',
        'QRRemember',
        'QRRestrict',
        'randomNames',
        'competitionMode',
        'teamResults',
        'photoUpload',
        'puzzleWordEnabled',
        'collectPlayerUid',
        'qrLogoPunchout',
        'qrRounded',
    ];

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
        try {
            return json_encode($row, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('Unable to encode config: ' . $e->getMessage(), 0, $e);
        }
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
        try {
            return json_encode($row, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('Unable to encode config: ' . $e->getMessage(), 0, $e);
        }
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
     * Allowed HTML tags for invitation texts.
     */
    private const ALLOWED_HTML_TAGS = '<p><br><strong><b><em><i><h2><h3><h4><h5>';

    /**
     * Remove unwanted HTML tags from user provided text.
     */
    public static function sanitizeHtml(string $html): string
    {
        return strip_tags($html, self::ALLOWED_HTML_TAGS);
    }

    /**
     * Return the available columns of the config table.
     *
     * @return list<string>
     */
    private function getConfigColumns(): array
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $stmt = $this->pdo->query('PRAGMA table_info(config)');
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            return array_map(static fn(array $r): string => (string) $r['name'], $rows);
        }

        $stmt = $this->pdo->prepare(
            "SELECT column_name FROM information_schema.columns WHERE table_name = 'config'"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
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
            'ogImagePath',
            'pageTitle',
            'backgroundColor',
            'buttonColor',
            'CheckAnswerButton',
            'QRRestrict',
            'randomNames',
            'competitionMode',
            'teamResults',
        'photoUpload',
        'puzzleWordEnabled',
        'puzzleWord',
        'puzzleFeedback',
        'collectPlayerUid',
        'inviteText',
        'colors',
        'event_uid',
            'qrLabelLine1',
            'qrLabelLine2',
            'qrLogoPath',
            'qrLogoWidth',
            'qrRoundMode',
            'qrLogoPunchout',
            'qrRounded',
            'qrColorTeam',
            'qrColorCatalog',
            'qrColorEvent',
        ];
        $existing = array_map('strtolower', $this->getConfigColumns());
        $filtered = array_intersect_key($data, array_flip($keys));
        $filtered = array_filter(
            $filtered,
            fn ($v, $k) => in_array(strtolower((string) $k), $existing, true),
            ARRAY_FILTER_USE_BOTH
        );
        $uid = (string)($filtered['event_uid'] ?? $this->getActiveEventUid());
        $filtered['event_uid'] = $uid;

        $this->pdo->beginTransaction();
        try {
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
                } elseif ($k === 'colors') {
                    $stmt->bindValue(':' . $k, json_encode($v, JSON_THROW_ON_ERROR));
                } else {
                    $stmt->bindValue(':' . $k, $v);
                }
            }
            $stmt->execute();
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        $this->setActiveEventUid($uid);
    }

    /**
     * Update the UID of the currently active event.
     */
    public function setActiveEventUid(string $uid): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec('DELETE FROM active_event');
            if ($uid !== '') {
                $stmt = $this->pdo->prepare('INSERT INTO active_event(event_uid) VALUES(?)');
                $stmt->execute([$uid]);
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
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
            'ogImagePath',
            'pageTitle',
            'backgroundColor',
            'buttonColor',
            'CheckAnswerButton',
            'QRRestrict',
            'randomNames',
            'competitionMode',
            'teamResults',
            'photoUpload',
            'puzzleWordEnabled',
            'puzzleWord',
            'puzzleFeedback',
            'inviteText',
            'colors',
            'event_uid',
            'qrLabelLine1',
            'qrLabelLine2',
            'qrLogoPath',
            'qrLogoWidth',
            'qrRoundMode',
            'qrLogoPunchout',
            'qrRounded',
            'qrColorTeam',
            'qrColorCatalog',
            'qrColorEvent',
        ];
        $map = [];
        foreach ($keys as $k) {
            $map[strtolower($k)] = $k;
        }
        $normalized = [];
        foreach ($row as $k => $v) {
            $key = $map[strtolower($k)] ?? $k;
            if ($key === 'colors') {
                if (is_string($v)) {
                    try {
                        $decoded = json_decode($v, true, 512, JSON_THROW_ON_ERROR);
                        $normalized[$key] = is_array($decoded) ? $decoded : [];
                    } catch (JsonException $e) {
                        $normalized[$key] = [];
                    }
                } else {
                    $normalized[$key] = is_array($v) ? $v : [];
                }
            } elseif (in_array($key, self::BOOL_KEYS, true)) {
                $normalized[$key] = $v === null ? null : filter_var(
                    $v,
                    FILTER_VALIDATE_BOOL,
                    FILTER_NULL_ON_FAILURE
                );
            } else {
                $normalized[$key] = $v;
            }
        }
        if (!isset($normalized['colors'])) {
            $colorCfg = [];
            if (isset($normalized['backgroundColor'])) {
                $colorCfg['primary'] = $normalized['backgroundColor'];
            }
            if (isset($normalized['buttonColor'])) {
                $colorCfg['accent'] = $normalized['buttonColor'];
            }
            if ($colorCfg !== []) {
                $normalized['colors'] = $colorCfg;
            }
        }
        return $normalized;
    }
}
