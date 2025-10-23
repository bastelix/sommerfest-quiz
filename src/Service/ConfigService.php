<?php

declare(strict_types=1);

namespace App\Service;

use JsonException;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;
use App\Support\TokenCipher;

/**
 * Handles reading and writing application configuration values.
 */
class ConfigService
{
    private PDO $pdo;
    private ?string $activeEvent = null;
    private TokenCipher $tokenCipher;

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
        'shuffleQuestions',
        'competitionMode',
        'teamResults',
        'photoUpload',
        'puzzleWordEnabled',
        'collectPlayerUid',
        'countdownEnabled',
        'loginRequired',
        'qrLogoPunchout',
        'qrRounded',
        'stickerPrintDesc',
        'stickerPrintHeader',
        'stickerPrintSubheader',
        'stickerPrintCatalog',
        'dashboardShareEnabled',
        'dashboardSponsorEnabled',
    ];

    /**
     * Columns that store JSON payloads and should be encoded/decoded automatically.
     *
     * @var array<int,string>
     */
    private const JSON_COLUMNS = [
        'colors',
        'dashboardModules',
    ];

    /**
     * Mapping between camelCase configuration keys and their snake_case columns.
     *
     * @var array<string,string>
     */
    private const COLUMN_ALIASES = [
        'dashboardModules' => 'dashboard_modules',
        'dashboardTheme' => 'dashboard_theme',
        'dashboardRefreshInterval' => 'dashboard_refresh_interval',
        'dashboardShareEnabled' => 'dashboard_share_enabled',
        'dashboardSponsorEnabled' => 'dashboard_sponsor_enabled',
        'dashboardInfoText' => 'dashboard_info_text',
        'dashboardMediaEmbed' => 'dashboard_media_embed',
        'dashboardVisibilityStart' => 'dashboard_visibility_start',
        'dashboardVisibilityEnd' => 'dashboard_visibility_end',
        'dashboardShareToken' => 'dashboard_share_token',
        'dashboardSponsorToken' => 'dashboard_sponsor_token',
    ];

    /**
     * Inject PDO instance used for database operations.
     */
    public function __construct(PDO $pdo, ?TokenCipher $tokenCipher = null) {
        $this->pdo = $pdo;
        $this->tokenCipher = $tokenCipher ?? new TokenCipher();
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS active_event(' .
            'event_uid TEXT PRIMARY KEY' .
            ')'
        );
    }

    /**
     * Retrieve configuration as pretty printed JSON.
     */
    public function getJson(): ?string {
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
    public function getJsonForEvent(string $uid): ?string {
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
    public function getConfig(): array {
        $uid = $this->getActiveEventUid();
        if ($uid === '') {
            return [];
        }

        $stmt = $this->pdo->prepare('SELECT * FROM config WHERE event_uid = ? LIMIT 1');
        $stmt->execute([$uid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false && $row !== null
            ? $this->normalizeKeys($row)
            : [];
    }

    /**
     * Return configuration for the given event UID or an empty array if none exists.
     */
    public function getConfigForEvent(string $uid): array {
        $stmt = $this->pdo->prepare('SELECT * FROM config WHERE event_uid = ? LIMIT 1');
        $stmt->execute([$uid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($row !== null) {
            return $this->normalizeKeys($row);
        }
        return [];
    }

    /**
     * Create a new random dashboard token consisting of URL-safe characters.
     */
    public function generateDashboardToken(): string {
        return rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
    }

    /**
     * Decrypt a stored dashboard token while supporting legacy plaintext values.
     */
    private function resolveDashboardToken(?string $value): ?string {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $plain = $this->tokenCipher->decrypt($trimmed);
        if (is_string($plain) && $plain !== '') {
            return $plain;
        }

        if (preg_match('/^[A-Za-z0-9_-]{16,}$/', $trimmed) === 1) {
            return $trimmed;
        }

        return null;
    }

    /**
     * Persist a dashboard token for the given event.
     */
    public function setDashboardToken(string $uid, string $variant, ?string $token): void {
        if ($uid === '') {
            return;
        }
        $this->ensureConfigForEvent($uid);
        $column = $variant === 'sponsor' ? 'dashboard_sponsor_token' : 'dashboard_share_token';
        $sql = "UPDATE config SET {$column} = :token WHERE event_uid = :uid";
        $stmt = $this->pdo->prepare($sql);
        $normalized = $token !== null ? trim($token) : '';
        if ($normalized === '') {
            $stmt->bindValue(':token', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':token', $this->tokenCipher->encrypt($normalized));
        }
        $stmt->bindValue(':uid', $uid);
        $stmt->execute();
    }

    /**
     * Retrieve decrypted dashboard tokens for the event.
     *
     * @return array{public:?string,sponsor:?string}
     */
    public function getDashboardTokens(string $uid): array {
        if ($uid === '') {
            return ['public' => null, 'sponsor' => null];
        }
        $stmt = $this->pdo->prepare(
            'SELECT dashboard_share_token, dashboard_sponsor_token FROM config WHERE event_uid = ? LIMIT 1'
        );
        $stmt->execute([$uid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $public = $row['dashboard_share_token'] ?? null;
        $sponsor = $row['dashboard_sponsor_token'] ?? null;
        return [
            'public' => $this->resolveDashboardToken(is_string($public) ? $public : null),
            'sponsor' => $this->resolveDashboardToken(is_string($sponsor) ? $sponsor : null),
        ];
    }

    /**
     * Validate a share token for the specified event and return the matched variant.
     */
    public function verifyDashboardToken(string $uid, string $token, ?string $variant = null): ?string {
        $token = trim($token);
        if ($uid === '' || $token === '') {
            return null;
        }
        $tokens = $this->getDashboardTokens($uid);
        if (($variant === null || $variant === 'public')
            && $tokens['public'] !== null
            && hash_equals($tokens['public'], $token)
        ) {
            return 'public';
        }
        if (($variant === null || $variant === 'sponsor')
            && $tokens['sponsor'] !== null
            && hash_equals($tokens['sponsor'], $token)
        ) {
            return 'sponsor';
        }
        return null;
    }

    /**
     * Remove puzzle word fields from the configuration array.
     *
     * @param array<string,mixed> $cfg
     * @return array<string,mixed>
     */
    public static function removePuzzleInfo(array $cfg): array {
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
    public static function sanitizeHtml(string $html): string {
        return strip_tags($html, self::ALLOWED_HTML_TAGS);
    }

    /**
     * Return the available columns of the config table.
     *
     * @return list<string>
     */
    private function getConfigColumns(): array {
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
    public function saveConfig(array $data): void {
        if (isset($data['pageTitle']) && !isset($data['title'])) {
            $data['title'] = $data['pageTitle'];
        }
        if (isset($data['QRUser']) && !isset($data['loginRequired'])) {
            $data['loginRequired'] = $data['QRUser'];
        }
        if (array_key_exists('dashboardTheme', $data)) {
            $data['dashboardTheme'] = $this->normalizeDashboardTheme($data['dashboardTheme']);
        }
        $norm = fn ($v) => (float) str_replace(',', '.', (string) $v);
        $stickerKeys = [
            'stickerDescTop',
            'stickerDescLeft',
            'stickerDescWidth',
            'stickerDescHeight',
            'stickerQrTop',
            'stickerQrLeft',
            'stickerQrSizePct',
        ];
        foreach ($stickerKeys as $k) {
            if (isset($data[$k])) {
                $data[$k] = $norm($data[$k]);
            }
        }
        $keys = [
            'displayErrorDetails',
            'title',
            'loginRequired',
            'QRRemember',
            'logoPath',
            'ogImagePath',
            'backgroundColor',
            'buttonColor',
            'startTheme',
            'CheckAnswerButton',
            'QRRestrict',
            'randomNames',
            'shuffleQuestions',
            'competitionMode',
            'teamResults',
            'photoUpload',
            'puzzleWordEnabled',
            'puzzleWord',
            'puzzleFeedback',
            'collectPlayerUid',
            'countdownEnabled',
            'inviteText',
            'whitelist',
            'countdown',
            'webhookUrl',
            'analyticsId',
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
            'stickerTemplate',
            'stickerPrintHeader',
            'stickerPrintSubheader',
            'stickerPrintCatalog',
            'stickerPrintDesc',
            'stickerQrColor',
            'stickerQrSizePct',
            'stickerDescTop',
            'stickerDescLeft',
            'stickerQrTop',
            'stickerQrLeft',
            'stickerHeaderFontSize',
            'stickerSubheaderFontSize',
            'stickerCatalogFontSize',
            'stickerDescFontSize',
            'stickerTextColor',
            'stickerDescWidth',
            'stickerDescHeight',
            'stickerBgPath',
            'dashboardModules',
            'dashboardTheme',
            'dashboardRefreshInterval',
            'dashboardShareEnabled',
            'dashboardSponsorEnabled',
            'dashboardInfoText',
            'dashboardMediaEmbed',
            'dashboardVisibilityStart',
            'dashboardVisibilityEnd',
        ];
        $existing = array_map('strtolower', $this->getConfigColumns());
        $allowed = array_flip($keys);
        $filtered = [];
        foreach ($data as $key => $value) {
            if (!array_key_exists($key, $allowed)) {
                continue;
            }
            $column = self::COLUMN_ALIASES[$key] ?? $key;
            if (!in_array(strtolower((string) $column), $existing, true)) {
                continue;
            }
            $filtered[$column] = ['key' => $key, 'value' => $value];
        }

        $uid = (string)($filtered['event_uid']['value'] ?? $this->getActiveEventUid());
        $filtered['event_uid'] = ['key' => 'event_uid', 'value' => $uid];

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
            foreach ($filtered as $column => $item) {
                $value = $item['value'];
                $sourceKey = $item['key'];
                if (is_bool($value)) {
                    $stmt->bindValue(':' . $column, $value, PDO::PARAM_BOOL);
                    continue;
                }
                if (in_array($sourceKey, self::JSON_COLUMNS, true)) {
                    if ($value === null) {
                        $stmt->bindValue(':' . $column, null, PDO::PARAM_NULL);
                    } else {
                        $stmt->bindValue(':' . $column, json_encode($value, JSON_THROW_ON_ERROR));
                    }
                    continue;
                }
                if (in_array($sourceKey, self::BOOL_KEYS, true)) {
                    if ($value === null) {
                        $stmt->bindValue(':' . $column, null, PDO::PARAM_NULL);
                    } else {
                        $stmt->bindValue(':' . $column, (bool) $value, PDO::PARAM_BOOL);
                    }
                    continue;
                }
                if ($value === null) {
                    $stmt->bindValue(':' . $column, null, PDO::PARAM_NULL);
                    continue;
                }
                $stmt->bindValue(':' . $column, $value);
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
    public function setActiveEventUid(string $uid): void {
        if ($uid !== '') {
            try {
                $check = $this->pdo->prepare('SELECT 1 FROM events WHERE uid = ? LIMIT 1');
                $check->execute([$uid]);
                if ($check->fetchColumn() === false) {
                    return;
                }
            } catch (PDOException) {
                return;
            }
        }

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
        if ($uid !== '') {
            $this->ensureConfigForEvent($uid);
        }
        $this->activeEvent = $uid;
    }

    /**
     * Ensure a configuration row exists for the given event UID.
     */
    public function ensureConfigForEvent(string $uid): void {
        if ($uid === '') {
            return;
        }
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
    public function getActiveEventUid(): string {
        if ($this->activeEvent !== null) {
            return $this->activeEvent;
        }

        $stmt = $this->pdo->query('SELECT event_uid FROM active_event LIMIT 1');
        $uid = $stmt->fetchColumn();

        if ($uid === false || $uid === null || $uid === '') {
            return $this->activeEvent = '';
        }

        return $this->activeEvent = (string) $uid;
    }

    /**
     * Return the relative path to the image directory of an event.
     */
    public function getEventImagesPath(?string $uid = null): string {
        $uid = $uid ?? $this->getActiveEventUid();
        return '/events/' . $uid . '/images';
    }

    /**
     * Return the relative path to the shared uploads directory.
     */
    public function getGlobalUploadsPath(): string {
        return '/uploads';
    }

    /**
     * Return the absolute path to the image directory of an event and ensure it exists.
     */
    public function getEventImagesDir(?string $uid = null): string {
        $uid = $uid ?? $this->getActiveEventUid();
        $dir = dirname(__DIR__, 2) . '/data' . $this->getEventImagesPath($uid);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('unable to create image directory');
        }
        return $dir;
    }

    /**
     * Return the absolute path to the shared uploads directory and ensure it exists.
     */
    public function getGlobalUploadsDir(): string {
        $dir = dirname(__DIR__, 2) . '/data' . $this->getGlobalUploadsPath();
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('unable to create uploads directory');
        }
        return $dir;
    }

    /**
     * Move legacy image files into the new event image directory structure.
     */
    public function migrateEventImages(?string $uid = null): void {
        $uid = $uid ?? $this->getActiveEventUid();
        if ($uid === '') {
            return;
        }
        $dataDir = dirname(__DIR__, 2) . '/data';
        $target = $this->getEventImagesDir($uid);

        foreach (['png', 'webp', 'svg'] as $ext) {
            $oldLogo = $dataDir . '/logo-' . $uid . '.' . $ext;
            $newLogo = $target . '/logo.' . $ext;
            if (is_file($oldLogo) && !is_file($newLogo)) {
                @rename($oldLogo, $newLogo);
            }

            $oldQr = $dataDir . '/qrlogo-' . $uid . '.' . $ext;
            $newQr = $target . '/qrlogo.' . $ext;
            if (is_file($oldQr) && !is_file($newQr)) {
                @rename($oldQr, $newQr);
            }
        }

        $oldSticker = $dataDir . '/events/' . $uid . '/sticker-bg.png';
        $globalSticker = $dataDir . '/uploads/sticker-bg.png';
        $newSticker = $target . '/sticker-bg.png';
        if (is_file($oldSticker) && !is_file($newSticker)) {
            @rename($oldSticker, $newSticker);
        } elseif (is_file($globalSticker) && !is_file($newSticker)) {
            @rename($globalSticker, $newSticker);
        }

        if (is_file($newSticker)) {
            $this->saveConfig([
                'event_uid' => $uid,
                'stickerBgPath' => $this->getEventImagesPath($uid) . '/sticker-bg.png',
            ]);
        }

        $oldPhotos = $dataDir . '/photos';
        if (is_dir($oldPhotos)) {
            $newPhotos = $target . '/photos';
            if (!is_dir($newPhotos)) {
                mkdir($newPhotos, 0775, true);
            }
            foreach (glob($oldPhotos . '/*') as $p) {
                @rename($p, $newPhotos . '/' . basename($p));
            }
        }
    }

    /**
     * Normalize database column names to expected camelCase keys.
     *
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function normalizeDashboardTheme($value): string
    {
        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if ($normalized === 'dark' || $normalized === 'light') {
                return $normalized;
            }
        }

        return 'light';
    }

    /**
     * Normalize database column names to expected camelCase keys.
     *
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function normalizeKeys(array $row): array {
        $keys = [
            'displayErrorDetails',
            'QRUser',
            'QRRemember',
            'logoPath',
            'ogImagePath',
            'pageTitle',
            'backgroundColor',
            'buttonColor',
            'startTheme',
            'CheckAnswerButton',
            'QRRestrict',
            'randomNames',
            'shuffleQuestions',
            'competitionMode',
            'teamResults',
            'photoUpload',
            'puzzleWordEnabled',
            'puzzleWord',
            'puzzleFeedback',
            'countdownEnabled',
            'inviteText',
            'whitelist',
            'countdown',
            'webhookUrl',
            'analyticsId',
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
            'stickerTemplate',
            'stickerPrintHeader',
            'stickerPrintSubheader',
            'stickerPrintCatalog',
            'stickerPrintDesc',
            'stickerQrColor',
            'stickerQrSizePct',
            'stickerDescTop',
            'stickerDescLeft',
            'stickerQrTop',
            'stickerQrLeft',
            'stickerTextColor',
            'stickerDescWidth',
            'stickerDescHeight',
            'stickerBgPath',
            'dashboardModules',
            'dashboardTheme',
            'dashboardRefreshInterval',
            'dashboardShareEnabled',
            'dashboardSponsorEnabled',
            'dashboardInfoText',
            'dashboardMediaEmbed',
            'dashboardVisibilityStart',
            'dashboardVisibilityEnd',
            'dashboardShareToken',
            'dashboardSponsorToken',
        ];
        $map = [];
        foreach ($keys as $k) {
            $lower = strtolower($k);
            $map[$lower] = $k;
            $snake = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $k));
            if ($snake !== $lower) {
                $map[$snake] = $k;
            }
        }
        foreach (self::COLUMN_ALIASES as $camel => $column) {
            $map[strtolower($column)] = $camel;
        }
        $map['title'] = 'pageTitle';
        $map['loginrequired'] = 'QRUser';
        $normalized = [];
        foreach ($row as $k => $v) {
            $key = $map[strtolower($k)] ?? $k;
            if (in_array($key, self::JSON_COLUMNS, true)) {
                if (is_string($v) && $v !== '') {
                    try {
                        $decoded = json_decode($v, true, 512, JSON_THROW_ON_ERROR);
                        $normalized[$key] = is_array($decoded) ? $decoded : [];
                    } catch (JsonException $e) {
                        $normalized[$key] = [];
                    }
                } elseif (is_array($v)) {
                    $normalized[$key] = $v;
                } else {
                    $normalized[$key] = [];
                }
            } elseif (in_array($key, self::BOOL_KEYS, true)) {
                $normalized[$key] = $v === null ? null : filter_var(
                    $v,
                    FILTER_VALIDATE_BOOL,
                    FILTER_NULL_ON_FAILURE
                );
            } elseif ($key === 'dashboardShareToken' || $key === 'dashboardSponsorToken') {
                $normalized[$key] = $this->resolveDashboardToken(is_string($v) ? $v : null);
            } elseif ($key === 'dashboardRefreshInterval') {
                $normalized[$key] = $v !== null ? (int) $v : null;
            } else {
                $normalized[$key] = $v;
            }
        }
        $normalized['dashboardTheme'] = $this->normalizeDashboardTheme($normalized['dashboardTheme'] ?? null);

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
