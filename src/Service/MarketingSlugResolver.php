<?php

declare(strict_types=1);

namespace App\Service;

use PDO;

final class MarketingSlugResolver
{
    /**
     * Map of base marketing page slugs to their localized counterparts.
     *
     * @var array<string,array<string,string>>
     */
    public const LOCALIZED_SLUG_MAP = [
        'calserver' => [
            'en' => 'calserver-en',
        ],
        'calserver-maintenance' => [
            'en' => 'calserver-maintenance-en',
        ],
        'calserver-accessibility' => [
            'en' => 'calserver-accessibility-en',
        ],
    ];

    private function __construct()
    {
    }

    public static function resolveLocalizedSlug(string $baseSlug, string $locale): string
    {
        $locale = strtolower(trim($locale));
        if ($locale === '' || $locale === 'de') {
            return $baseSlug;
        }

        if (isset(self::LOCALIZED_SLUG_MAP[$baseSlug][$locale])) {
            return self::LOCALIZED_SLUG_MAP[$baseSlug][$locale];
        }

        return $baseSlug;
    }

    public static function resolveBaseSlug(string $slug): string
    {
        $normalized = strtolower(trim($slug));
        if ($normalized === '') {
            return $normalized;
        }

        foreach (self::LOCALIZED_SLUG_MAP as $baseSlug => $localizedSlugs) {
            if (in_array($normalized, $localizedSlugs, true)) {
                return $baseSlug;
            }
        }

        return $normalized;
    }

    /**
     * Resolve a localized slug dynamically from the database using the base_slug column.
     *
     * Falls back to the hardcoded map if no DB match is found.
     */
    public static function resolveFromDatabase(PDO $pdo, string $namespace, string $baseSlug, string $locale): ?string
    {
        $locale = strtolower(trim($locale));
        if ($locale === '' || $locale === 'de') {
            return null;
        }

        $stmt = $pdo->prepare(
            'SELECT slug FROM pages WHERE namespace = ? AND base_slug = ? AND language = ? LIMIT 1'
        );
        $stmt->execute([$namespace, $baseSlug, $locale]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row !== false && isset($row['slug'])) {
            return (string) $row['slug'];
        }

        return null;
    }

    /**
     * Resolve the base slug from the database using the base_slug column.
     */
    public static function resolveBaseSlugFromDatabase(PDO $pdo, string $slug): ?string
    {
        $normalized = strtolower(trim($slug));
        if ($normalized === '') {
            return null;
        }

        $stmt = $pdo->prepare('SELECT base_slug FROM pages WHERE slug = ? AND base_slug IS NOT NULL LIMIT 1');
        $stmt->execute([$normalized]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row !== false && isset($row['base_slug']) && $row['base_slug'] !== '') {
            return (string) $row['base_slug'];
        }

        return null;
    }
}
