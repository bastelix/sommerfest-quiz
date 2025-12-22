<?php

declare(strict_types=1);

namespace App\Repository;

use App\Infrastructure\Database;
use PDO;

final class ProjectSettingsRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connectFromEnv();
    }

    public function hasTable(): bool
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $stmt = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name = ?");
            $stmt->execute(['project_settings']);
            return $stmt->fetchColumn() !== false;
        }

        $stmt = $this->pdo->prepare('SELECT to_regclass(?)');
        $stmt->execute(['project_settings']);
        return $stmt->fetchColumn() !== null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fetch(string $namespace): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT namespace, cookie_consent_enabled, cookie_storage_key, cookie_banner_text, '
            . 'cookie_banner_text_de, cookie_banner_text_en, cookie_vendor_flags, privacy_url, updated_at '
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

    public function upsert(
        string $namespace,
        bool $cookieConsentEnabled,
        ?string $cookieStorageKey,
        ?string $cookieBannerText,
        ?string $cookieBannerTextDe,
        ?string $cookieBannerTextEn,
        ?string $cookieVendorFlags,
        ?string $privacyUrl
    ): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO project_settings ('
            . 'namespace, cookie_consent_enabled, cookie_storage_key, cookie_banner_text, '
            . 'cookie_banner_text_de, cookie_banner_text_en, cookie_vendor_flags, privacy_url, updated_at'
            . ') VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP) '
            . 'ON CONFLICT (namespace) DO UPDATE SET '
            . 'cookie_consent_enabled = EXCLUDED.cookie_consent_enabled, '
            . 'cookie_storage_key = EXCLUDED.cookie_storage_key, '
            . 'cookie_banner_text = EXCLUDED.cookie_banner_text, '
            . 'cookie_banner_text_de = EXCLUDED.cookie_banner_text_de, '
            . 'cookie_banner_text_en = EXCLUDED.cookie_banner_text_en, '
            . 'cookie_vendor_flags = EXCLUDED.cookie_vendor_flags, '
            . 'privacy_url = EXCLUDED.privacy_url, '
            . 'updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([
            $namespace,
            $cookieConsentEnabled ? 1 : 0,
            $cookieStorageKey,
            $cookieBannerText,
            $cookieBannerTextDe,
            $cookieBannerTextEn,
            $cookieVendorFlags,
            $privacyUrl,
        ]);
        $stmt->closeCursor();
    }
}
