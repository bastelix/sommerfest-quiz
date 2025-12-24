<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\ProjectSettingsRepository;
use App\Support\MarketingWikiThemeResolver;
use DateTimeImmutable;
use DateTimeInterface;
use JsonException;
use PDO;
use RuntimeException;

/**
 * Loads marketing wiki theme overrides stored in namespace project settings.
 */
final class MarketingWikiThemeConfigService
{
    private ProjectSettingsRepository $repository;
    private NamespaceValidator $namespaceValidator;

    public function __construct(
        ?PDO $pdo = null,
        ?ProjectSettingsRepository $repository = null,
        ?NamespaceValidator $validator = null
    ) {
        $this->repository = $repository ?? new ProjectSettingsRepository($pdo);
        $this->namespaceValidator = $validator ?? new NamespaceValidator();
    }

    /**
     * @return array{
     *     bodyClasses?: list<string>,
     *     stylesheets?: list<string>,
     *     colors?: array<string, string>,
     *     logoUrl?: string|null,
     *     updatedAt?: string
     * }|null
     */
    public function getThemeForSlug(string $namespace, string $slug): ?array
    {
        $normalizedNamespace = $this->namespaceValidator->normalize($namespace);
        $normalizedSlug = strtolower(trim($slug));

        if ($normalizedSlug === '') {
            return null;
        }

        $theme = $this->resolveTheme($normalizedNamespace, $normalizedSlug);
        if ($theme !== null) {
            return $theme;
        }

        if ($normalizedNamespace === PageService::DEFAULT_NAMESPACE) {
            return null;
        }

        return $this->resolveTheme(PageService::DEFAULT_NAMESPACE, $normalizedSlug);
    }

    /**
     * @return array{
     *     bodyClasses?: list<string>,
     *     stylesheets?: list<string>,
     *     colors?: array<string, string>,
     *     logoUrl?: string|null,
     *     updatedAt?: string
     * }|null
     */
    private function resolveTheme(string $namespace, string $slug): ?array
    {
        $themeMap = $this->loadThemeMap($namespace);

        return $themeMap[$slug] ?? null;
    }

    /**
     * @return array<string, array{
     *     bodyClasses?: list<string>,
     *     stylesheets?: list<string>,
     *     colors?: array<string, string>,
     *     logoUrl?: string|null,
     *     updatedAt?: string
     * }>
     */
    private function loadThemeMap(string $namespace): array
    {
        if (!$this->repository->hasTable()) {
            return [];
        }

        $row = $this->repository->fetch($namespace);
        if ($row === null) {
            return [];
        }

        return $this->normalizeThemeMap($row['marketing_wiki_themes'] ?? null);
    }

    /**
     * @return array<string, array{
     *     bodyClasses?: list<string>,
     *     stylesheets?: list<string>,
     *     colors?: array<string, string>,
     *     logoUrl?: string|null,
     *     updatedAt?: string
     * }>
     */
    private function normalizeThemeMap(mixed $value): array
    {
        if (is_string($value)) {
            try {
                $value = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                return [];
            }
        }

        if (!is_array($value)) {
            return [];
        }

        $normalized = [];
        foreach ($value as $slug => $theme) {
            if (!is_string($slug)) {
                continue;
            }

            if (!is_array($theme)) {
                continue;
            }

            $normalizedSlug = strtolower(trim($slug));
            if ($normalizedSlug === '') {
                continue;
            }

            $normalized[$normalizedSlug] = $this->sanitizeTheme($theme);
        }

        return $normalized;
    }

    /**
     * @param array{
     *     bodyClasses?: list<string>,
     *     stylesheets?: list<string>,
     *     colors?: array<string, string>,
     *     logoUrl?: string|null,
     *     updatedAt?: string
     * } $theme
     * @return array{
     *     bodyClasses?: list<string>,
     *     stylesheets?: list<string>,
     *     colors?: array<string, string>,
     *     logoUrl?: string|null,
     *     updatedAt?: string
     * }
     */
    public function saveThemeForSlug(string $namespace, string $slug, array $theme): array
    {
        $normalizedNamespace = $this->namespaceValidator->normalize($namespace);
        $normalizedSlug = strtolower(trim($slug));

        if ($normalizedSlug === '') {
            throw new RuntimeException('Slug must not be empty.');
        }

        $themeMap = $this->loadThemeMap($normalizedNamespace);
        $theme['updatedAt'] = (new DateTimeImmutable())->format(DateTimeInterface::ATOM);
        $themeMap[$normalizedSlug] = $this->sanitizeTheme($theme);

        $this->repository->updateWikiThemes($normalizedNamespace, $themeMap);

        return $themeMap[$normalizedSlug];
    }

    /**
     * @param array<string, mixed> $theme
     * @return array{
     *     bodyClasses?: list<string>,
     *     stylesheets?: list<string>,
     *     colors?: array<string, string>,
     *     logoUrl?: string|null,
     *     updatedAt?: string
     * }
     */
    private function sanitizeTheme(array $theme): array
    {
        $bodyClasses = [];
        if (isset($theme['bodyClasses']) && is_array($theme['bodyClasses'])) {
            foreach ($theme['bodyClasses'] as $class) {
                $value = trim((string) $class);
                if ($value !== '' && !in_array($value, $bodyClasses, true)) {
                    $bodyClasses[] = $value;
                }
            }
        }

        $stylesheets = [];
        if (isset($theme['stylesheets']) && is_array($theme['stylesheets'])) {
            foreach ($theme['stylesheets'] as $stylesheet) {
                $value = trim((string) $stylesheet);
                if ($value !== '' && !in_array($value, $stylesheets, true)) {
                    $stylesheets[] = $value;
                }
            }
        }

        $colors = [];
        if (isset($theme['colors']) && is_array($theme['colors'])) {
            foreach ($theme['colors'] as $key => $value) {
                if (!is_string($key)) {
                    continue;
                }
                $colorKey = trim($key);
                $colorValue = trim((string) $value);
                if ($colorKey === '' || $colorValue === '') {
                    continue;
                }
                if (!preg_match('/^#([0-9a-fA-F]{6})$/', $colorValue)) {
                    continue;
                }
                $colors[$colorKey] = strtolower($colorValue);
            }
        }

        $logoUrl = null;
        if (array_key_exists('logoUrl', $theme)) {
            $candidate = $theme['logoUrl'];
            if (is_string($candidate)) {
                $trimmed = trim($candidate);
                if ($trimmed !== '' && $this->isValidUrl($trimmed)) {
                    $logoUrl = $trimmed;
                }
            }
        }

        $updatedAt = null;
        if (isset($theme['updatedAt']) && is_string($theme['updatedAt'])) {
            $updatedAt = $theme['updatedAt'];
        }

        $sanitized = [];
        if ($bodyClasses !== []) {
            $sanitized['bodyClasses'] = $bodyClasses;
        }
        if ($stylesheets !== []) {
            $sanitized['stylesheets'] = $stylesheets;
        }
        if ($colors !== []) {
            $sanitized['colors'] = $colors + MarketingWikiThemeResolver::defaultColors();
        }
        if ($logoUrl !== null) {
            $sanitized['logoUrl'] = $logoUrl;
        }
        if ($updatedAt !== null) {
            $sanitized['updatedAt'] = $updatedAt;
        }

        return $sanitized;
    }

    private function isValidUrl(string $value): bool
    {
        if (filter_var($value, FILTER_VALIDATE_URL) !== false) {
            return true;
        }

        return str_starts_with($value, '/');
    }
}
