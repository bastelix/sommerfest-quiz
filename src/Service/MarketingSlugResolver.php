<?php

declare(strict_types=1);

namespace App\Service;

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
}
