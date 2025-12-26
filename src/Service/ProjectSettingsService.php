<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\ProjectSettingsRepository;
use PDO;
use RuntimeException;

/**
 * @phpstan-type CookieConsentSettings array{
 *     namespace:string,
 *     cookie_consent_enabled:bool,
 *     cookie_storage_key:string,
 *     cookie_banner_text_de:string,
 *     cookie_banner_text_en:string,
 *     cookie_vendor_flags:array<array-key, mixed>,
 *     privacy_url:string,
 *     privacy_url_de:string,
 *     privacy_url_en:string,
 *     show_language_toggle:bool,
 *     show_theme_toggle:bool,
 *     show_contrast_toggle:bool,
 *     updated_at:?string
 * }
 */
final class ProjectSettingsService
{
    private const DEFAULT_STORAGE_KEY = 'calserverCookieChoices';
    private const DEFAULT_BANNER_TEXT_DE = 'Wir verwenden notwendige Cookies und laden Inhalte von YouTube erst, wenn du zustimmst. '
        . 'Du kannst deine Auswahl jederzeit in deinem Browser anpassen.';
    private const DEFAULT_BANNER_TEXT_EN = 'We use essential cookies and only load YouTube content after you consent. '
        . 'You can adjust your selection in your browser at any time.';
    private const MAX_STORAGE_KEY_LENGTH = 120;
    private const MAX_BANNER_TEXT_LENGTH = 2000;
    private const MAX_PRIVACY_URL_LENGTH = 500;
    private const MAX_VENDOR_FLAGS_LENGTH = 4000;

    private ProjectSettingsRepository $repository;

    public function __construct(?PDO $pdo = null, ?ProjectSettingsRepository $repository = null)
    {
        $this->repository = $repository ?? new ProjectSettingsRepository($pdo);
    }

    /**
     * @phpstan-return CookieConsentSettings
     */
    public function getCookieConsentSettings(string $namespace): array
    {
        $normalized = $this->normalizeNamespace($namespace);
        /** @var CookieConsentSettings $defaults */
        $defaults = $this->getDefaultSettings($normalized);

        if (!$this->repository->hasTable()) {
            return $defaults;
        }

        $row = $this->repository->fetch($normalized);
        if ($row === null && $normalized !== PageService::DEFAULT_NAMESPACE) {
            $row = $this->repository->fetch(PageService::DEFAULT_NAMESPACE);
        }

        if ($row === null) {
            return $defaults;
        }

        $storageKey = isset($row['cookie_storage_key']) ? trim((string) $row['cookie_storage_key']) : '';
        $legacyBannerText = isset($row['cookie_banner_text']) ? trim((string) $row['cookie_banner_text']) : '';
        $bannerTextDe = isset($row['cookie_banner_text_de']) ? trim((string) $row['cookie_banner_text_de']) : '';
        $bannerTextEn = isset($row['cookie_banner_text_en']) ? trim((string) $row['cookie_banner_text_en']) : '';
        $vendorFlags = $this->parseVendorFlags($row['cookie_vendor_flags'] ?? null);
        $privacyUrl = isset($row['privacy_url']) ? trim((string) $row['privacy_url']) : '';
        $privacyUrlDe = isset($row['privacy_url_de']) ? trim((string) $row['privacy_url_de']) : '';
        $privacyUrlEn = isset($row['privacy_url_en']) ? trim((string) $row['privacy_url_en']) : '';
        $showLanguageToggle = $this->normalizeBoolean($row['show_language_toggle'] ?? null, $defaults['show_language_toggle']);
        $showThemeToggle = $this->normalizeBoolean($row['show_theme_toggle'] ?? null, $defaults['show_theme_toggle']);
        $showContrastToggle = $this->normalizeBoolean($row['show_contrast_toggle'] ?? null, $defaults['show_contrast_toggle']);

        return [
            'namespace' => $normalized,
            'cookie_consent_enabled' => $this->normalizeBoolean($row['cookie_consent_enabled'] ?? null, $defaults['cookie_consent_enabled']),
            'cookie_storage_key' => $storageKey !== '' ? $storageKey : $defaults['cookie_storage_key'],
            'cookie_banner_text_de' => $bannerTextDe !== '' ? $bannerTextDe : ($legacyBannerText !== '' ? $legacyBannerText : $defaults['cookie_banner_text_de']),
            'cookie_banner_text_en' => $bannerTextEn !== '' ? $bannerTextEn : $defaults['cookie_banner_text_en'],
            'cookie_vendor_flags' => $vendorFlags,
            'privacy_url' => $privacyUrl !== '' ? $privacyUrl : $defaults['privacy_url'],
            'privacy_url_de' => $privacyUrlDe !== '' ? $privacyUrlDe : ($privacyUrl !== '' ? $privacyUrl : $defaults['privacy_url_de']),
            'privacy_url_en' => $privacyUrlEn !== '' ? $privacyUrlEn : ($privacyUrl !== '' ? $privacyUrl : $defaults['privacy_url_en']),
            'show_language_toggle' => $showLanguageToggle,
            'show_theme_toggle' => $showThemeToggle,
            'show_contrast_toggle' => $showContrastToggle,
            'updated_at' => isset($row['updated_at']) ? (string) $row['updated_at'] : null,
        ];
    }

    /**
     * @return array{
     *     namespace:string,
     *     cookie_consent_enabled:bool,
     *     cookie_storage_key:string,
     *     cookie_banner_text_de:string,
     *     cookie_banner_text_en:string,
     *     cookie_vendor_flags:array<array-key, mixed>,
     *     privacy_url:string,
     *     privacy_url_de:string,
     *     privacy_url_en:string,
     *     show_language_toggle:bool,
     *     show_theme_toggle:bool,
     *     show_contrast_toggle:bool,
     *     updated_at:?string
     * }
     */
    public function saveCookieConsentSettings(
        string $namespace,
        bool $enabled,
        ?string $storageKey,
        ?string $bannerTextDe,
        ?string $bannerTextEn,
        ?string $vendorFlags,
        ?string $privacyUrl,
        ?string $privacyUrlDe,
        ?string $privacyUrlEn,
        bool $showLanguageToggle,
        bool $showThemeToggle,
        bool $showContrastToggle
    ): array {
        $normalized = $this->normalizeNamespace($namespace);
        $this->assertTableExists();

        $normalizedStorageKey = $storageKey !== null ? trim($storageKey) : '';
        if ($normalizedStorageKey !== '' && mb_strlen($normalizedStorageKey) > self::MAX_STORAGE_KEY_LENGTH) {
            throw new RuntimeException('Cookie storage key is too long.');
        }

        $normalizedBannerTextDe = $bannerTextDe !== null ? trim($bannerTextDe) : '';
        if ($normalizedBannerTextDe !== '' && mb_strlen($normalizedBannerTextDe) > self::MAX_BANNER_TEXT_LENGTH) {
            throw new RuntimeException('Cookie banner text (DE) is too long.');
        }

        $normalizedBannerTextEn = $bannerTextEn !== null ? trim($bannerTextEn) : '';
        if ($normalizedBannerTextEn !== '' && mb_strlen($normalizedBannerTextEn) > self::MAX_BANNER_TEXT_LENGTH) {
            throw new RuntimeException('Cookie banner text (EN) is too long.');
        }

        $normalizedVendorFlags = $this->normalizeVendorFlags($vendorFlags);
        if ($normalizedVendorFlags !== null && mb_strlen($normalizedVendorFlags) > self::MAX_VENDOR_FLAGS_LENGTH) {
            throw new RuntimeException('Cookie vendor flags are too long.');
        }

        $normalizedPrivacyUrl = $this->normalizePrivacyUrl($privacyUrl);
        $normalizedPrivacyUrlDe = $this->normalizePrivacyUrl($privacyUrlDe);
        $normalizedPrivacyUrlEn = $this->normalizePrivacyUrl($privacyUrlEn);

        $legacyBannerText = $normalizedBannerTextDe !== '' ? $normalizedBannerTextDe : null;

        $this->repository->upsert(
            $normalized,
            $enabled,
            $normalizedStorageKey !== '' ? $normalizedStorageKey : null,
            $legacyBannerText,
            $normalizedBannerTextDe !== '' ? $normalizedBannerTextDe : null,
            $normalizedBannerTextEn !== '' ? $normalizedBannerTextEn : null,
            $normalizedVendorFlags,
            $normalizedPrivacyUrl !== '' ? $normalizedPrivacyUrl : null,
            $normalizedPrivacyUrlDe !== '' ? $normalizedPrivacyUrlDe : null,
            $normalizedPrivacyUrlEn !== '' ? $normalizedPrivacyUrlEn : null,
            $showLanguageToggle,
            $showThemeToggle,
            $showContrastToggle
        );

        return $this->getCookieConsentSettings($normalized);
    }

    /**
     * @phpstan-return CookieConsentSettings
     */
    private function getDefaultSettings(string $namespace): array
    {
        return [
            'namespace' => $namespace,
            'cookie_consent_enabled' => true,
            'cookie_storage_key' => self::DEFAULT_STORAGE_KEY,
            'cookie_banner_text_de' => self::DEFAULT_BANNER_TEXT_DE,
            'cookie_banner_text_en' => self::DEFAULT_BANNER_TEXT_EN,
            'cookie_vendor_flags' => [],
            'privacy_url' => '',
            'privacy_url_de' => '',
            'privacy_url_en' => '',
            'show_language_toggle' => true,
            'show_theme_toggle' => true,
            'show_contrast_toggle' => true,
            'updated_at' => null,
        ];
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

    public function resolvePrivacyUrlForSettings(array $settings, string $locale, string $basePath): string
    {
        $normalizedLocale = strtolower(trim($locale));
        $privacyUrl = '';
        if (str_starts_with($normalizedLocale, 'en')) {
            $privacyUrl = trim((string) ($settings['privacy_url_en'] ?? ''));
        } else {
            $privacyUrl = trim((string) ($settings['privacy_url_de'] ?? ''));
        }

        if ($privacyUrl === '') {
            $privacyUrl = trim((string) ($settings['privacy_url'] ?? ''));
        }

        if ($privacyUrl !== '') {
            return $privacyUrl;
        }

        $normalizedBasePath = rtrim($basePath, '/');

        return $normalizedBasePath . '/datenschutz';
    }

    private function assertTableExists(): void
    {
        if (!$this->repository->hasTable()) {
            throw new RuntimeException('Project settings table is not available.');
        }
    }

    private function normalizeVendorFlags(?string $vendorFlags): ?string
    {
        $normalized = $vendorFlags !== null ? trim($vendorFlags) : '';
        if ($normalized === '') {
            return null;
        }

        $decoded = json_decode($normalized, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Cookie vendor flags must be valid JSON.');
        }

        $encoded = json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            throw new RuntimeException('Cookie vendor flags could not be serialized.');
        }

        return $encoded;
    }

    private function normalizePrivacyUrl(?string $privacyUrl): string
    {
        $normalizedPrivacyUrl = $privacyUrl !== null ? trim($privacyUrl) : '';
        if ($normalizedPrivacyUrl !== '' && mb_strlen($normalizedPrivacyUrl) > self::MAX_PRIVACY_URL_LENGTH) {
            throw new RuntimeException('Privacy URL is too long.');
        }

        return $normalizedPrivacyUrl;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function parseVendorFlags(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }
}
