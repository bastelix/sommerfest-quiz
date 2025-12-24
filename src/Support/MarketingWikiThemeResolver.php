<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Provides consistent colour palettes for marketing wiki pages based on configuration data.
 */
final class MarketingWikiThemeResolver
{
    private const BASE_BODY_CLASS = 'marketing-wiki';

    /**
     * Default colour palette used when no explicit theme exists for a slug.
     *
     * @return array<string, string>
     */
    public static function defaultColors(): array
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
     * Resolve the styling configuration for a marketing wiki based on its theme configuration.
     *
     * @param array{
     *     bodyClasses?: list<string>,
     *     stylesheets?: list<string>,
     *     colors?: array<string, string>,
     *     logoUrl?: string|null
     * }|null $theme Theme overrides from configuration storage.
     *
     * @return array{
     *     bodyClasses: list<string>,
     *     stylesheets: list<string>,
     *     colors: array<string, string>,
     *     logoUrl: string|null
     * }
     */
    public static function resolve(?array $theme = null): array
    {
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

        $logoUrl = null;
        if ($theme !== null && array_key_exists('logoUrl', $theme)) {
            $logo = $theme['logoUrl'];
            if (is_string($logo)) {
                $trimmed = trim($logo);
                $logoUrl = $trimmed !== '' ? $trimmed : null;
            }
        }

        return [
            'bodyClasses' => $bodyClasses,
            'stylesheets' => $stylesheets,
            'colors' => $colors,
            'logoUrl' => $logoUrl,
        ];
    }
}
