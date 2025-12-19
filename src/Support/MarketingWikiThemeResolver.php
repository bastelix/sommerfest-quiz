<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Provides consistent colour palettes for marketing wiki pages based on their owner slug.
 */
final class MarketingWikiThemeResolver
{
    private const BASE_BODY_CLASS = 'marketing-wiki';

    /**
     * Default colour palette used when no explicit theme exists for a slug.
     *
     * @return array<string, string>
     */
    private static function defaultColors(): array
    {
        return [
            'headerFrom' => '#111827',
            'headerTo' => '#1f2937',
            'detailHeaderFrom' => '#0f172a',
            'detailHeaderTo' => '#1d4ed8',
            'headerText' => '#ffffff',
            'excerpt' => '#4b5563',
            'calloutBorder' => '#3b82f6',
            'calloutBackground' => '#f9fafb',
            'calloutText' => '#0f172a',
        ];
    }

    /**
     * Theme overrides per marketing page slug.
     *
     * @var array<string, array{
     *     bodyClasses?: list<string>,
     *     stylesheets?: list<string>,
     *     colors?: array<string, string>
     * }>
     */
    private const THEME_MAP = [
        'landing' => [
            'bodyClasses' => ['marketing-wiki--landing'],
            'colors' => [
                'headerFrom' => '#0d1117',
                'headerTo' => '#2563eb',
                'detailHeaderFrom' => '#0d1117',
                'detailHeaderTo' => '#3b82f6',
            ],
        ],
        'calserver' => [
            'bodyClasses' => ['marketing-wiki--calserver'],
            'colors' => [
                'headerFrom' => '#091126',
                'headerTo' => '#1f63e6',
                'detailHeaderFrom' => '#091a33',
                'detailHeaderTo' => '#1f63e6',
                'excerpt' => '#1e293b',
                'calloutBorder' => '#1f63e6',
                'calloutBackground' => 'rgba(31, 99, 230, 0.12)',
                'calloutText' => '#0b1733',
            ],
        ],
        'calhelp' => [
            'bodyClasses' => ['marketing-wiki--calhelp'],
            'colors' => [
                'headerFrom' => '#091126',
                'headerTo' => '#1f63e6',
                'detailHeaderFrom' => '#091a33',
                'detailHeaderTo' => '#1f63e6',
                'excerpt' => '#1e293b',
                'calloutBorder' => '#1f63e6',
                'calloutBackground' => 'rgba(31, 99, 230, 0.12)',
                'calloutText' => '#0b1733',
            ],
        ],
        'fluke-metcal' => [
            'bodyClasses' => ['marketing-wiki--fluke-metcal'],
            'colors' => [
                'headerFrom' => '#091126',
                'headerTo' => '#1f63e6',
                'detailHeaderFrom' => '#091a33',
                'detailHeaderTo' => '#1f63e6',
                'excerpt' => '#1e293b',
                'calloutBorder' => '#1f63e6',
                'calloutBackground' => 'rgba(31, 99, 230, 0.12)',
                'calloutText' => '#0b1733',
            ],
        ],
        'calserver-maintenance' => [
            'bodyClasses' => ['marketing-wiki--calserver-maintenance'],
            'colors' => [
                'headerFrom' => '#091126',
                'headerTo' => '#1f63e6',
                'detailHeaderFrom' => '#091a33',
                'detailHeaderTo' => '#1f63e6',
                'excerpt' => '#1e293b',
                'calloutBorder' => '#1f63e6',
                'calloutBackground' => 'rgba(31, 99, 230, 0.12)',
                'calloutText' => '#0b1733',
            ],
        ],
        'future-is-green' => [
            'bodyClasses' => ['marketing-wiki--future-is-green'],
            'colors' => [
                'headerFrom' => '#0c3a26',
                'headerTo' => '#138f52',
                'detailHeaderFrom' => '#0b2d20',
                'detailHeaderTo' => '#138f52',
                'excerpt' => '#2f5a44',
                'calloutBorder' => '#138f52',
                'calloutBackground' => 'rgba(19, 143, 82, 0.14)',
                'calloutText' => '#0c3a26',
            ],
        ],
    ];

    /**
     * Resolve the styling configuration for a marketing wiki based on its owning page slug.
     *
     * @param string $slug Marketing page slug or localized variant.
     *
     * @return array{
     *     bodyClasses: list<string>,
     *     stylesheets: list<string>,
     *     colors: array<string, string>
     * }
     */
    public static function resolve(string $slug): array
    {
        $normalized = strtolower(trim($slug));
        /** @var array<string, mixed>|null $theme */
        $theme = self::THEME_MAP[$normalized] ?? null;

        $bodyClasses = [self::BASE_BODY_CLASS];
        if ($theme !== null) {
            $themeBodyClasses = $theme['bodyClasses'] ?? null;
            if (is_array($themeBodyClasses)) {
                foreach ($themeBodyClasses as $class) {
                    $trimmed = trim((string) $class);
                    if ($trimmed !== '' && !in_array($trimmed, $bodyClasses, true)) {
                        $bodyClasses[] = $trimmed;
                    }
                }
            }
        }

        $stylesheets = [];
        if ($theme !== null) {
            $themeStylesheets = $theme['stylesheets'] ?? null;
            if (is_array($themeStylesheets)) {
                foreach ($themeStylesheets as $stylesheet) {
                    $trimmed = trim((string) $stylesheet);
                    if ($trimmed !== '' && !in_array($trimmed, $stylesheets, true)) {
                        $stylesheets[] = $trimmed;
                    }
                }
            }
        }

        $colors = self::defaultColors();
        if ($theme !== null) {
            $themeColors = $theme['colors'] ?? null;
            if (is_array($themeColors)) {
                foreach ($themeColors as $key => $value) {
                    $trimmed = trim((string) $value);
                    if ($trimmed !== '') {
                        $colors[$key] = $trimmed;
                    }
                }
            }
        }

        return [
            'bodyClasses' => $bodyClasses,
            'stylesheets' => $stylesheets,
            'colors' => $colors,
        ];
    }
}
