<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\ColorContrastService;
use PHPUnit\Framework\TestCase;

class ColorContrastServiceTest extends TestCase
{
    private ColorContrastService $service;

    protected function setUp(): void
    {
        $this->service = new ColorContrastService();
    }

    // ── hexToRgb ──────────────────────────────────────────────────────

    public function testHexToRgbParsesThreeDigitHex(): void
    {
        $this->assertSame(['r' => 255, 'g' => 255, 'b' => 255], $this->service->hexToRgb('#fff'));
        $this->assertSame(['r' => 0, 'g' => 0, 'b' => 0], $this->service->hexToRgb('#000'));
    }

    public function testHexToRgbParsesSixDigitHex(): void
    {
        $this->assertSame(['r' => 30, 'g' => 135, 'b' => 240], $this->service->hexToRgb('#1e87f0'));
        $this->assertSame(['r' => 43, 'g' => 94, 'b' => 74], $this->service->hexToRgb('#2B5E4A'));
    }

    public function testHexToRgbWithoutHash(): void
    {
        $this->assertSame(['r' => 255, 'g' => 0, 'b' => 0], $this->service->hexToRgb('ff0000'));
    }

    public function testHexToRgbReturnsNullForInvalid(): void
    {
        $this->assertNull($this->service->hexToRgb(''));
        $this->assertNull($this->service->hexToRgb('nope'));
        $this->assertNull($this->service->hexToRgb('#gg0000'));
    }

    // ── rgbToHex ──────────────────────────────────────────────────────

    public function testRgbToHex(): void
    {
        $this->assertSame('#ff0000', $this->service->rgbToHex(['r' => 255, 'g' => 0, 'b' => 0]));
        $this->assertSame('#000000', $this->service->rgbToHex(['r' => 0, 'g' => 0, 'b' => 0]));
        $this->assertSame('#1e87f0', $this->service->rgbToHex(['r' => 30, 'g' => 135, 'b' => 240]));
    }

    public function testRgbToHexClampsOutOfRange(): void
    {
        $this->assertSame('#ff00ff', $this->service->rgbToHex(['r' => 300, 'g' => -10, 'b' => 256]));
    }

    // ── relativeLuminance ─────────────────────────────────────────────

    public function testRelativeLuminanceWhite(): void
    {
        $lum = $this->service->relativeLuminance(['r' => 255, 'g' => 255, 'b' => 255]);
        $this->assertEqualsWithDelta(1.0, $lum, 0.001);
    }

    public function testRelativeLuminanceBlack(): void
    {
        $lum = $this->service->relativeLuminance(['r' => 0, 'g' => 0, 'b' => 0]);
        $this->assertEqualsWithDelta(0.0, $lum, 0.001);
    }

    public function testRelativeLuminanceMidGrey(): void
    {
        $lum = $this->service->relativeLuminance(['r' => 128, 'g' => 128, 'b' => 128]);
        $this->assertGreaterThan(0.15, $lum);
        $this->assertLessThan(0.25, $lum);
    }

    // ── contrastRatio ─────────────────────────────────────────────────

    public function testContrastRatioBlackOnWhiteIs21(): void
    {
        $black = ['r' => 0, 'g' => 0, 'b' => 0];
        $white = ['r' => 255, 'g' => 255, 'b' => 255];
        $ratio = $this->service->contrastRatio($black, $white);
        $this->assertEqualsWithDelta(21.0, $ratio, 0.1);
    }

    public function testContrastRatioIsSymmetric(): void
    {
        $a = ['r' => 30, 'g' => 135, 'b' => 240];
        $b = ['r' => 255, 'g' => 255, 'b' => 255];
        $this->assertEqualsWithDelta(
            $this->service->contrastRatio($a, $b),
            $this->service->contrastRatio($b, $a),
            0.001,
        );
    }

    public function testContrastRatioHexConvenience(): void
    {
        $ratio = $this->service->contrastRatioHex('#000000', '#ffffff');
        $this->assertNotNull($ratio);
        $this->assertEqualsWithDelta(21.0, $ratio, 0.1);
    }

    public function testContrastRatioHexReturnsNullForInvalid(): void
    {
        $this->assertNull($this->service->contrastRatioHex('nope', '#ffffff'));
    }

    // ── meetsAA / meetsAAA ────────────────────────────────────────────

    public function testMeetsAAThresholds(): void
    {
        $this->assertTrue($this->service->meetsAA(4.5));
        $this->assertTrue($this->service->meetsAA(7.0));
        $this->assertFalse($this->service->meetsAA(4.49));
    }

    public function testMeetsAAAThresholds(): void
    {
        $this->assertTrue($this->service->meetsAAA(7.0));
        $this->assertFalse($this->service->meetsAAA(6.99));
    }

    // ── optimalTextColor ──────────────────────────────────────────────

    public function testOptimalTextColorOnWhiteIsBlack(): void
    {
        $this->assertSame('#000000', $this->service->optimalTextColor('#ffffff'));
    }

    public function testOptimalTextColorOnBlackIsWhite(): void
    {
        $this->assertSame('#ffffff', $this->service->optimalTextColor('#000000'));
    }

    public function testOptimalTextColorOnBrightBlueIsBlack(): void
    {
        // #1e87f0 is a mid-bright blue; black has better contrast
        $this->assertSame('#000000', $this->service->optimalTextColor('#1e87f0'));
    }

    public function testOptimalTextColorOnDeepBlueIsWhite(): void
    {
        // #0d47a1 is a deep blue; white has better contrast
        $this->assertSame('#ffffff', $this->service->optimalTextColor('#0d47a1'));
    }

    public function testOptimalTextColorOnLightYellowIsBlack(): void
    {
        $this->assertSame('#000000', $this->service->optimalTextColor('#ffeb3b'));
    }

    public function testOptimalTextColorOnDarkGreenIsWhite(): void
    {
        // CalHelp primary #2B5E4A is dark enough for white text
        $this->assertSame('#ffffff', $this->service->optimalTextColor('#2B5E4A'));
    }

    public function testOptimalTextColorFallsBackToBlackForInvalid(): void
    {
        $this->assertSame('#000000', $this->service->optimalTextColor('invalid'));
    }

    // ── ensureContrast ────────────────────────────────────────────────

    public function testEnsureContrastKeepsPreferredWhenSufficient(): void
    {
        // Black on white: 21:1 → keep black
        $this->assertSame('#000000', $this->service->ensureContrast('#ffffff', '#000000'));
    }

    public function testEnsureContrastReplacesWhenInsufficient(): void
    {
        // Light grey on white has poor contrast → should be replaced
        $result = $this->service->ensureContrast('#ffffff', '#dddddd');
        $this->assertSame('#000000', $result);
    }

    // ── resolveContrastTokens ─────────────────────────────────────────

    public function testResolveContrastTokensReturnsAllKeys(): void
    {
        $tokens = $this->service->resolveContrastTokens([
            'primary' => '#1e87f0',
            'secondary' => '#f97316',
            'accent' => '#f97316',
        ]);

        $this->assertArrayHasKey('textOnPrimary', $tokens);
        $this->assertArrayHasKey('textOnSecondary', $tokens);
        $this->assertArrayHasKey('textOnAccent', $tokens);
        $this->assertArrayHasKey('textOnSurface', $tokens);
        $this->assertArrayHasKey('textOnSurfaceMuted', $tokens);
        $this->assertArrayHasKey('textOnPage', $tokens);
    }

    public function testResolveContrastTokensAllMeetAA(): void
    {
        $tokens = $this->service->resolveContrastTokens([
            'primary' => '#1e87f0',
            'secondary' => '#f97316',
            'accent' => '#f97316',
            'surface' => '#ffffff',
            'surfaceMuted' => '#eef2f7',
            'surfacePage' => '#f7f9fb',
        ]);

        // textOnSecondary is computed against the hero background
        // (color-mix(in srgb, secondary 85%, #0b1728)), not the raw secondary.
        $heroBg = $this->service->colorMixSrgb('#f97316', '#0b1728', 0.85);

        $pairs = [
            ['textOnPrimary', '#1e87f0'],
            ['textOnSecondary', $heroBg],
            ['textOnAccent', '#f97316'],
            ['textOnSurface', '#ffffff'],
            ['textOnSurfaceMuted', '#eef2f7'],
            ['textOnPage', '#f7f9fb'],
        ];

        foreach ($pairs as [$tokenKey, $bgHex]) {
            $ratio = $this->service->contrastRatioHex($tokens[$tokenKey], $bgHex);
            $this->assertNotNull($ratio, "Ratio for $tokenKey should not be null");
            $this->assertTrue(
                $this->service->meetsAA($ratio),
                "$tokenKey ({$tokens[$tokenKey]} on $bgHex) has ratio $ratio, expected >= 4.5",
            );
        }
    }

    // ── colorMixSrgb ───────────────────────────────────────────────────

    public function testColorMixSrgbInterpolation(): void
    {
        // 50/50 mix of black and white → mid grey
        $this->assertSame('#808080', $this->service->colorMixSrgb('#000000', '#ffffff', 0.5));

        // Full weight on first color
        $this->assertSame('#ff0000', $this->service->colorMixSrgb('#ff0000', '#0000ff', 1.0));

        // Full weight on second color
        $this->assertSame('#0000ff', $this->service->colorMixSrgb('#ff0000', '#0000ff', 0.0));
    }

    public function testColorMixSrgbHeroBackground(): void
    {
        // Simulates sections.css: color-mix(in srgb, #f97316 85%, #0b1728)
        $result = $this->service->colorMixSrgb('#f97316', '#0b1728', 0.85);

        $rgb = $this->service->hexToRgb($result);
        $this->assertNotNull($rgb);
        // 249*0.85 + 11*0.15 ≈ 213, 115*0.85 + 23*0.15 ≈ 101, 22*0.85 + 40*0.15 ≈ 25
        $this->assertEqualsWithDelta(213, $rgb['r'], 1);
        $this->assertEqualsWithDelta(101, $rgb['g'], 1);
        $this->assertEqualsWithDelta(25, $rgb['b'], 1);
    }

    public function testColorMixSrgbReturnsFirstOnInvalidInput(): void
    {
        $this->assertSame('#ff0000', $this->service->colorMixSrgb('#ff0000', 'invalid', 0.5));
        $this->assertSame('invalid', $this->service->colorMixSrgb('invalid', '#ff0000', 0.5));
    }

    // ── textOnSecondary uses hero background ──────────────────────────

    /**
     * @dataProvider namespaceSecondaryColorsProvider
     */
    public function testTextOnSecondaryMeetsAAOnHeroBackground(string $secondary): void
    {
        $tokens = $this->service->resolveContrastTokens([
            'primary' => '#1e87f0',
            'secondary' => $secondary,
            'accent' => $secondary,
        ]);

        $heroBg = $this->service->colorMixSrgb($secondary, '#0b1728', 0.85);
        $ratio = $this->service->contrastRatioHex($tokens['textOnSecondary'], $heroBg);

        $this->assertNotNull($ratio);
        $this->assertTrue(
            $this->service->meetsAA($ratio),
            "textOnSecondary ({$tokens['textOnSecondary']}) on hero bg ($heroBg) from secondary $secondary has ratio $ratio, expected >= 4.5",
        );
    }

    public static function namespaceSecondaryColorsProvider(): array
    {
        return [
            'default (#f97316)' => ['#f97316'],
            'aurora (#475569)' => ['#475569'],
            'calhelp (#1a3a50)' => ['#1a3a50'],
            'calserver (#f97316)' => ['#f97316'],
            'new-namespace (#f97316)' => ['#f97316'],
            'uikit-default (#222222)' => ['#222222'],
        ];
    }

    // ── resolveContrastTokensForThemes ─────────────────────────────────

    public function testResolveContrastTokensForThemesReturnsBothModes(): void
    {
        $result = $this->service->resolveContrastTokensForThemes([
            'primary' => '#1e87f0',
            'secondary' => '#f97316',
            'accent' => '#f97316',
        ]);

        $this->assertArrayHasKey('light', $result);
        $this->assertArrayHasKey('dark', $result);
        $this->assertArrayHasKey('textOnPrimary', $result['light']);
        $this->assertArrayHasKey('textOnPrimary', $result['dark']);
    }

    public function testResolveContrastTokensForThemesAllMeetAA(): void
    {
        $brand = [
            'primary' => '#2B5E4A',
            'secondary' => '#1a1a2e',
            'accent' => '#C4A35A',
        ];

        $result = $this->service->resolveContrastTokensForThemes($brand);

        // Light theme: text on brand colors (textOnSecondary uses hero background)
        $heroBg = $this->service->colorMixSrgb($brand['secondary'], '#0b1728', 0.85);
        foreach (['textOnPrimary' => $brand['primary'], 'textOnSecondary' => $heroBg, 'textOnAccent' => $brand['accent']] as $key => $bg) {
            $ratio = $this->service->contrastRatioHex($result['light'][$key], $bg);
            $this->assertNotNull($ratio);
            $this->assertTrue($this->service->meetsAA($ratio), "Light $key fails AA: ratio $ratio");
        }

        // Dark theme: text on dark surfaces
        $darkSurfaces = ['textOnSurface' => '#1a2636', 'textOnSurfaceMuted' => '#0b111a', 'textOnPage' => '#0a0d12'];
        foreach ($darkSurfaces as $key => $bg) {
            $ratio = $this->service->contrastRatioHex($result['dark'][$key], $bg);
            $this->assertNotNull($ratio);
            $this->assertTrue($this->service->meetsAA($ratio), "Dark $key fails AA: ratio $ratio");
        }
    }
}
