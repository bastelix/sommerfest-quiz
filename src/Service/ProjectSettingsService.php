<?php

declare(strict_types=1);

namespace App\Service;

use App\Infrastructure\Database;
use PDO;
use RuntimeException;

final class ProjectSettingsService
{
    private const DEFAULT_STORAGE_KEY = 'calserverCookieChoices';
    private const DEFAULT_BANNER_TEXT = 'Wir verwenden notwendige Cookies und laden Inhalte von YouTube erst, wenn du zustimmst. '
        . 'Du kannst deine Auswahl jederzeit in deinem Browser anpassen.';
    private const MAX_STORAGE_KEY_LENGTH = 120;
    private const MAX_BANNER_TEXT_LENGTH = 2000;
    private const MAX_PRIVACY_URL_LENGTH = 500;

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connectFromEnv();
    }

    /**
     * @return array{
     *     namespace:string,
     *     cookie_consent_enabled:bool,
     *     cookie_storage_key:string,
     *     cookie_banner_text:string,
     *     privacy_url:string,
     *     updated_at:?string
     * }
     */
    public function getCookieConsentSettings(string $namespace): array
    {
        $normalized = $this->normalizeNamespace($namespace);
        $defaults = $this->getDefaultSettings($normalized);

        if (!$this->hasTable('project_settings')) {
            return $defaults;
        }

        $row = $this->fetchSettings($normalized);
        if ($row === null && $normalized !== PageService::DEFAULT_NAMESPACE) {
            $row = $this->fetchSettings(PageService::DEFAULT_NAMESPACE);
        }

        if ($row === null) {
            return $defaults;
        }

        $storageKey = isset($row['cookie_storage_key']) ? trim((string) $row['cookie_storage_key']) : '';
        $bannerText = isset($row['cookie_banner_text']) ? trim((string) $row['cookie_banner_text']) : '';
        $privacyUrl = isset($row['privacy_url']) ? trim((string) $row['privacy_url']) : '';

        return [
            'namespace' => $normalized,
            'cookie_consent_enabled' => $this->normalizeBoolean($row['cookie_consent_enabled'] ?? null, $defaults['cookie_consent_enabled']),
            'cookie_storage_key' => $storageKey !== '' ? $storageKey : $defaults['cookie_storage_key'],
            'cookie_banner_text' => $bannerText !== '' ? $bannerText : $defaults['cookie_banner_text'],
            'privacy_url' => $privacyUrl !== '' ? $privacyUrl : $defaults['privacy_url'],
            'updated_at' => isset($row['updated_at']) ? (string) $row['updated_at'] : null,
        ];
    }

    /**
     * @return array{
     *     namespace:string,
     *     cookie_consent_enabled:bool,
     *     cookie_storage_key:string,
     *     cookie_banner_text:string,
     *     privacy_url:string,
     *     updated_at:?string
     * }
     */
    public function saveCookieConsentSettings(
        string $namespace,
        bool $enabled,
        ?string $storageKey,
        ?string $bannerText,
        ?string $privacyUrl
    ): array {
        $normalized = $this->normalizeNamespace($namespace);
        $this->assertTableExists();

        $normalizedStorageKey = $storageKey !== null ? trim($storageKey) : '';
        if ($normalizedStorageKey !== '' && mb_strlen($normalizedStorageKey) > self::MAX_STORAGE_KEY_LENGTH) {
            throw new RuntimeException('Cookie storage key is too long.');
        }

        $normalizedBannerText = $bannerText !== null ? trim($bannerText) : '';
        if ($normalizedBannerText !== '' && mb_strlen($normalizedBannerText) > self::MAX_BANNER_TEXT_LENGTH) {
            throw new RuntimeException('Cookie banner text is too long.');
        }

        $normalizedPrivacyUrl = $privacyUrl !== null ? trim($privacyUrl) : '';
        if ($normalizedPrivacyUrl !== '' && mb_strlen($normalizedPrivacyUrl) > self::MAX_PRIVACY_URL_LENGTH) {
            throw new RuntimeException('Privacy URL is too long.');
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO project_settings (namespace, cookie_consent_enabled, cookie_storage_key, cookie_banner_text, privacy_url, updated_at) '
            . 'VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP) '
            . 'ON CONFLICT (namespace) DO UPDATE SET '
            . 'cookie_consent_enabled = EXCLUDED.cookie_consent_enabled, '
            . 'cookie_storage_key = EXCLUDED.cookie_storage_key, '
            . 'cookie_banner_text = EXCLUDED.cookie_banner_text, '
            . 'privacy_url = EXCLUDED.privacy_url, '
            . 'updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([
            $normalized,
            $enabled ? 1 : 0,
            $normalizedStorageKey !== '' ? $normalizedStorageKey : null,
            $normalizedBannerText !== '' ? $normalizedBannerText : null,
            $normalizedPrivacyUrl !== '' ? $normalizedPrivacyUrl : null,
        ]);
        $stmt->closeCursor();

        return $this->getCookieConsentSettings($normalized);
    }

    /**
     * @return array{
     *     namespace:string,
     *     cookie_consent_enabled:bool,
     *     cookie_storage_key:string,
     *     cookie_banner_text:string,
     *     privacy_url:string,
     *     updated_at:?string
     * }
     */
    private function getDefaultSettings(string $namespace): array
    {
        return [
            'namespace' => $namespace,
            'cookie_consent_enabled' => true,
            'cookie_storage_key' => self::DEFAULT_STORAGE_KEY,
            'cookie_banner_text' => self::DEFAULT_BANNER_TEXT,
            'privacy_url' => '',
            'updated_at' => null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchSettings(string $namespace): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT namespace, cookie_consent_enabled, cookie_storage_key, cookie_banner_text, privacy_url, updated_at '
            . 'FROM project_settings WHERE namespace = ?'
        );
        $stmt->execute([$namespace]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if ($row === false) {
            return null;
        }

        return $row;
    }

    private function normalizeNamespace(string $namespace): string
    {
        $validator = new NamespaceValidator();

        return $validator->normalize($namespace);
    }

    private function normalizeBoolean(mixed $value, bool $fallback): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }
            if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
                return false;
            }
        }

        return $fallback;
    }

    private function assertTableExists(): void
    {
        if (!$this->hasTable('project_settings')) {
            throw new RuntimeException('Project settings table is not available.');
        }
    }

    private function hasTable(string $name): bool
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $stmt = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name = ?");
            $stmt->execute([$name]);
            return $stmt->fetchColumn() !== false;
        }

        $stmt = $this->pdo->prepare('SELECT to_regclass(?)');
        $stmt->execute([$name]);
        return $stmt->fetchColumn() !== null;
    }
}
