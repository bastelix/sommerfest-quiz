<?php

declare(strict_types=1);

namespace App\Service;

use App\Infrastructure\Database;
use RuntimeException;

class NamespaceAppearanceService
{
    private DesignTokenService $designTokens;
    private ConfigService $configService;

    public function __construct(?DesignTokenService $designTokens = null, ?ConfigService $configService = null)
    {
        $pdo = Database::connectFromEnv();
        $this->configService = $configService ?? new ConfigService($pdo);
        $this->designTokens = $designTokens ?? new DesignTokenService($pdo, $this->configService);
    }

    /**
     * @return array<string, mixed>
     */
    public function load(string $namespace): array
    {
        $designPayload = $this->configService->resolveDesignConfig($namespace);
        $config = $designPayload['config'];
        $resolvedNamespace = $namespace;

        try {
            $tokens = $this->designTokens->getTokensForNamespace($resolvedNamespace);
        } catch (RuntimeException $exception) {
            $resolvedNamespace = PageService::DEFAULT_NAMESPACE;
            $tokens = $this->designTokens->getTokensForNamespace($resolvedNamespace);
        }

        $designColors = $this->resolveDesignColors($config);

        return [
            'tokens' => $tokens,
            'defaults' => $this->designTokens->getDefaults(),
            'colors' => array_filter([
                'primary' => $tokens['brand']['primary'] ?? null,
                'secondary' => $tokens['brand']['secondary'] ?? null,
                'accent' => $tokens['brand']['accent'] ?? null,
                ...$designColors['colors'],
            ], static fn (?string $value): bool => $value !== null && $value !== ''),
            'variables' => $designColors['variables'],
        ];
    }

    /**
     * @param array<string, mixed> $config
     * @return array{colors: array<string, string>, variables: array<string, string>}
     */
    private function resolveDesignColors(array $config): array
    {
        $colors = is_array($config['colors'] ?? null) ? $config['colors'] : [];

        $surface = $this->pickColor($colors, 'surface', 'background', 'backgroundColor');
        $muted = $this->pickColor($colors, 'muted', 'surfaceMuted');
        $topbarLight = $this->pickColor($colors, 'topbar_light', 'topbarLight');
        $topbarDark = $this->pickColor($colors, 'topbar_dark', 'topbarDark');
        $textOnSurface = $this->pickColor($colors, 'textOnSurface', 'text_on_surface');
        $textOnBackground = $this->pickColor($colors, 'textOnBackground', 'text_on_background');
        $textOnPrimary = $this->pickColor($colors, 'textOnPrimary', 'text_on_primary');
        $marketingScheme = $this->normalizeMarketingScheme(
            $this->pickColor($colors, 'marketingScheme', 'marketing_scheme')
        );

        $normalizedColors = array_filter([
            'surface' => $surface,
            'muted' => $muted,
            'background' => $this->pickColor($colors, 'background', 'backgroundColor'),
            'topbar_light' => $topbarLight,
            'topbarLight' => $topbarLight,
            'topbar_dark' => $topbarDark,
            'topbarDark' => $topbarDark,
        ], static fn (?string $value): bool => $value !== null && $value !== '');

        $variables = array_filter([
            'surface' => $surface,
            'surfaceMuted' => $muted,
            'textOnSurface' => $textOnSurface,
            'textOnBackground' => $textOnBackground,
            'textOnPrimary' => $textOnPrimary,
            'topbarLight' => $topbarLight,
            'topbarDark' => $topbarDark,
            'marketingScheme' => $marketingScheme,
        ], static fn (?string $value): bool => $value !== null && $value !== '');

        return [
            'colors' => $normalizedColors,
            'variables' => $variables,
        ];
    }

    private function normalizeMarketingScheme(?string $marketingScheme): ?string
    {
        if ($marketingScheme === null) {
            return null;
        }

        $normalized = strtolower(trim($marketingScheme));
        if ($normalized === '') {
            return null;
        }
        if ($normalized === 'monochrom') {
            return 'monochrome';
        }

        return $normalized;
    }

    private function pickColor(array $colors, string ...$keys): ?string
    {
        foreach ($keys as $key) {
            $value = $colors[$key] ?? null;
            if (is_string($value)) {
                $trimmed = trim($value);
                if ($trimmed !== '') {
                    return $trimmed;
                }
            }
        }

        return null;
    }
}
