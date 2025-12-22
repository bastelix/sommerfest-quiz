<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\ProjectSettingsRepository;
use JsonException;
use PDO;

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
     *     colors?: array<string, string>
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
     *     colors?: array<string, string>
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
     *     colors?: array<string, string>
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
     *     colors?: array<string, string>
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

            $normalized[$normalizedSlug] = $theme;
        }

        return $normalized;
    }
}
