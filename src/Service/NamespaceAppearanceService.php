<?php

declare(strict_types=1);

namespace App\Service;

use App\Infrastructure\Database;

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
        $tokens = $this->designTokens->getTokensForNamespace($namespace);
        $config = $this->configService->getConfigForEvent($namespace);

        if ($config === [] && $namespace !== PageService::DEFAULT_NAMESPACE) {
            $config = $this->configService->getConfigForEvent(PageService::DEFAULT_NAMESPACE);
        }

        $designColors = $this->resolveDesignColors($config);

        return [
            'tokens' => $tokens,
            'defaults' => $this->designTokens->getDefaults(),
            'colors' => array_filter([
                'primary' => $tokens['brand']['primary'] ?? null,
                'secondary' => $tokens['brand']['accent'] ?? null,
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
            'topbarLight' => $topbarLight,
            'topbarDark' => $topbarDark,
        ], static fn (?string $value): bool => $value !== null && $value !== '');

        return [
            'colors' => $normalizedColors,
            'variables' => $variables,
        ];
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
