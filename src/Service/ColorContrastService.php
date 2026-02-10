<?php

declare(strict_types=1);

namespace App\Service;

/**
 * WCAG 2.1 color contrast utilities.
 *
 * Computes relative luminance, contrast ratios, and optimal foreground
 * colors so that every background/text combination meets at least
 * WCAG AA (4.5:1 for normal text).
 */
class ColorContrastService
{
    /** WCAG AA minimum contrast ratio for normal text. */
    public const AA_THRESHOLD = 4.5;

    /** WCAG AAA minimum contrast ratio for normal text. */
    public const AAA_THRESHOLD = 7.0;

    /**
     * Parse a CSS hex color string into [r, g, b] (0-255 each).
     *
     * Accepts #RGB, #RRGGBB (case-insensitive, leading # optional).
     *
     * @return array{r: int, g: int, b: int}|null
     */
    public function hexToRgb(string $hex): ?array
    {
        $hex = ltrim(trim($hex), '#');

        if (preg_match('/^[0-9a-f]{3}$/i', $hex)) {
            return [
                'r' => intval($hex[0] . $hex[0], 16),
                'g' => intval($hex[1] . $hex[1], 16),
                'b' => intval($hex[2] . $hex[2], 16),
            ];
        }

        if (preg_match('/^[0-9a-f]{6}$/i', $hex)) {
            return [
                'r' => intval(substr($hex, 0, 2), 16),
                'g' => intval(substr($hex, 2, 2), 16),
                'b' => intval(substr($hex, 4, 2), 16),
            ];
        }

        return null;
    }

    /**
     * Convert an RGB array back to a 6-digit hex string.
     *
     * @param array{r: int, g: int, b: int} $rgb
     */
    public function rgbToHex(array $rgb): string
    {
        return sprintf(
            '#%02x%02x%02x',
            max(0, min(255, $rgb['r'])),
            max(0, min(255, $rgb['g'])),
            max(0, min(255, $rgb['b'])),
        );
    }

    /**
     * WCAG 2.1 relative luminance.
     *
     * @see https://www.w3.org/TR/WCAG21/#dfn-relative-luminance
     *
     * @param array{r: int, g: int, b: int} $rgb
     */
    public function relativeLuminance(array $rgb): float
    {
        $channels = [];
        foreach (['r', 'g', 'b'] as $key) {
            $normalized = $rgb[$key] / 255;
            $channels[] = $normalized <= 0.03928
                ? $normalized / 12.92
                : pow(($normalized + 0.055) / 1.055, 2.4);
        }

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    /**
     * WCAG 2.1 contrast ratio between two colors.
     *
     * @param array{r: int, g: int, b: int} $fg
     * @param array{r: int, g: int, b: int} $bg
     */
    public function contrastRatio(array $fg, array $bg): float
    {
        $lumFg = $this->relativeLuminance($fg);
        $lumBg = $this->relativeLuminance($bg);
        $lighter = max($lumFg, $lumBg);
        $darker = min($lumFg, $lumBg);

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    /**
     * Contrast ratio between two hex colors.
     */
    public function contrastRatioHex(string $fgHex, string $bgHex): ?float
    {
        $fg = $this->hexToRgb($fgHex);
        $bg = $this->hexToRgb($bgHex);

        if ($fg === null || $bg === null) {
            return null;
        }

        return $this->contrastRatio($fg, $bg);
    }

    public function meetsAA(float $ratio): bool
    {
        return $ratio >= self::AA_THRESHOLD;
    }

    public function meetsAAA(float $ratio): bool
    {
        return $ratio >= self::AAA_THRESHOLD;
    }

    /**
     * Return the best simple foreground (#000000 or #ffffff) for the given
     * background so that the contrast ratio is maximised.
     */
    public function optimalTextColor(string $backgroundHex): string
    {
        $bg = $this->hexToRgb($backgroundHex);

        if ($bg === null) {
            return '#000000';
        }

        $black = ['r' => 0, 'g' => 0, 'b' => 0];
        $white = ['r' => 255, 'g' => 255, 'b' => 255];

        $blackRatio = $this->contrastRatio($black, $bg);
        $whiteRatio = $this->contrastRatio($white, $bg);

        return $blackRatio >= $whiteRatio ? '#000000' : '#ffffff';
    }

    /**
     * Return $preferredHex if it meets WCAG AA against $backgroundHex,
     * otherwise return the optimal black/white alternative.
     */
    public function ensureContrast(string $backgroundHex, string $preferredHex): string
    {
        $bg = $this->hexToRgb($backgroundHex);
        $fg = $this->hexToRgb($preferredHex);

        if ($bg === null || $fg === null) {
            return $preferredHex;
        }

        $ratio = $this->contrastRatio($fg, $bg);

        if ($this->meetsAA($ratio)) {
            return $preferredHex;
        }

        return $this->optimalTextColor($backgroundHex);
    }

    /**
     * Compute a full set of contrast-safe foreground tokens for a theme.
     *
     * Given the brand colors and surface colors, this returns the optimal
     * text color for each combination so that every block on the page
     * automatically has readable text.
     *
     * @param array{
     *     primary: string,
     *     secondary: string,
     *     accent: string,
     *     surface?: string,
     *     surfaceMuted?: string,
     *     surfacePage?: string,
     * } $colors
     *
     * @return array<string, string> Map of token name => hex color.
     */
    public function resolveContrastTokens(array $colors): array
    {
        $primary = $colors['primary'] ?? '';
        $secondary = $colors['secondary'] ?? '';
        $accent = $colors['accent'] ?? '';
        $surface = $colors['surface'] ?? '#ffffff';
        $surfaceMuted = $colors['surfaceMuted'] ?? $surface;
        $surfacePage = $colors['surfacePage'] ?? '#f7f9fb';

        $tokens = [];

        // Text on primary brand color (buttons, hero sections, highlights)
        if ($primary !== '') {
            $tokens['textOnPrimary'] = $this->optimalTextColor($primary);
        }

        // Text on secondary brand color
        if ($secondary !== '') {
            $tokens['textOnSecondary'] = $this->optimalTextColor($secondary);
        }

        // Text on accent brand color
        if ($accent !== '') {
            $tokens['textOnAccent'] = $this->optimalTextColor($accent);
        }

        // Text on surface (cards, content sections)
        if ($surface !== '') {
            $tokens['textOnSurface'] = $this->optimalTextColor($surface);
        }

        // Text on muted surface (feature sections)
        if ($surfaceMuted !== '') {
            $tokens['textOnSurfaceMuted'] = $this->optimalTextColor($surfaceMuted);
        }

        // Text on page background
        if ($surfacePage !== '') {
            $tokens['textOnPage'] = $this->optimalTextColor($surfacePage);
        }

        return $tokens;
    }

    /**
     * Resolve contrast tokens for both light and dark theme variants.
     *
     * @param array{primary: string, secondary: string, accent: string} $brand
     *
     * @return array{light: array<string, string>, dark: array<string, string>}
     */
    public function resolveContrastTokensForThemes(array $brand): array
    {
        $light = $this->resolveContrastTokens([
            'primary' => $brand['primary'] ?? '',
            'secondary' => $brand['secondary'] ?? '',
            'accent' => $brand['accent'] ?? '',
            'surface' => '#ffffff',
            'surfaceMuted' => '#eef2f7',
            'surfacePage' => '#f7f9fb',
        ]);

        $dark = $this->resolveContrastTokens([
            'primary' => $brand['primary'] ?? '',
            'secondary' => $brand['secondary'] ?? '',
            'accent' => $brand['accent'] ?? '',
            'surface' => '#1a2636',
            'surfaceMuted' => '#0b111a',
            'surfacePage' => '#0a0d12',
        ]);

        return ['light' => $light, 'dark' => $dark];
    }
}
