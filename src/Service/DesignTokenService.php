<?php

declare(strict_types=1);

namespace App\Service;

use PDO;
use RuntimeException;

class DesignTokenService
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private const DEFAULT_TOKENS = [
        'brand' => [
            'primary' => '#1e87f0',
            'accent' => '#f97316',
        ],
        'layout' => [
            'profile' => 'standard',
        ],
        'typography' => [
            'preset' => 'modern',
        ],
        'components' => [
            'cardStyle' => 'rounded',
            'buttonStyle' => 'filled',
        ],
    ];

    /** @var list<string> */
    private const LAYOUT_PROFILES = ['narrow', 'standard', 'wide'];

    /** @var list<string> */
    private const TYPOGRAPHY_PRESETS = ['modern', 'classic', 'tech'];

    /** @var list<string> */
    private const CARD_STYLES = ['rounded', 'square', 'pill'];

    /** @var list<string> */
    private const BUTTON_STYLES = ['filled', 'outline', 'ghost'];

    private ConfigService $configService;

    private PDO $pdo;

    private string $cssPath;

    public function __construct(PDO $pdo, ?ConfigService $configService = null, ?string $cssPath = null)
    {
        $this->pdo = $pdo;
        $this->configService = $configService ?? new ConfigService($pdo);
        $this->cssPath = $cssPath ?? dirname(__DIR__, 2) . '/public/css/namespace-tokens.css';
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function getDefaults(): array
    {
        return self::DEFAULT_TOKENS;
    }

    /**
     * @return list<string>
     */
    public function getLayoutProfiles(): array
    {
        return self::LAYOUT_PROFILES;
    }

    /**
     * @return list<string>
     */
    public function getTypographyPresets(): array
    {
        return self::TYPOGRAPHY_PRESETS;
    }

    /**
     * @return list<string>
     */
    public function getCardStyles(): array
    {
        return self::CARD_STYLES;
    }

    /**
     * @return list<string>
     */
    public function getButtonStyles(): array
    {
        return self::BUTTON_STYLES;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTokensForNamespace(string $namespace): array
    {
        $normalized = $this->normalizeNamespace($namespace);
        $stored = $this->fetchStoredTokens($normalized);

        return $this->mergeWithDefaults($stored);
    }

    /**
     * @param array<string, mixed> $tokens
     */
    public function persistTokens(string $namespace, array $tokens): array
    {
        $normalizedNamespace = $this->normalizeNamespace($namespace);
        $validated = $this->validateTokens($tokens);
        $payload = ['event_uid' => $normalizedNamespace, 'designTokens' => $validated];
        $this->configService->ensureConfigForEvent($normalizedNamespace);
        $this->configService->saveConfig($payload);
        $this->rebuildStylesheet();

        return $this->mergeWithDefaults($validated);
    }

    public function resetToDefaults(string $namespace): array
    {
        $normalizedNamespace = $this->normalizeNamespace($namespace);
        $this->configService->ensureConfigForEvent($normalizedNamespace);
        $this->configService->saveConfig([
            'event_uid' => $normalizedNamespace,
            'designTokens' => self::DEFAULT_TOKENS,
        ]);
        $this->rebuildStylesheet();

        return self::DEFAULT_TOKENS;
    }

    public function rebuildStylesheet(): void
    {
        $namespaces = $this->fetchAllNamespaceTokens();
        $css = $this->buildCss($namespaces);

        if (file_put_contents($this->cssPath, $css) === false) {
            throw new RuntimeException('Unable to write namespace token stylesheet');
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function fetchAllNamespaceTokens(): array
    {
        $stmt = $this->pdo->query('SELECT event_uid, design_tokens FROM config WHERE event_uid IS NOT NULL');
        $rows = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        $namespaces = [];
        foreach ($rows as $row) {
            $namespace = $this->normalizeNamespace((string) ($row['event_uid'] ?? ''));
            if ($namespace === '') {
                continue;
            }
            $tokens = $row['design_tokens'] ?? [];
            if (is_string($tokens) && $tokens !== '') {
                $decoded = json_decode($tokens, true);
                $tokens = is_array($decoded) ? $decoded : [];
            }
            if (!is_array($tokens)) {
                $tokens = [];
            }
            $namespaces[$namespace] = $this->mergeWithDefaults($this->validateTokens($tokens));
        }

        if (!array_key_exists(PageService::DEFAULT_NAMESPACE, $namespaces)) {
            $namespaces[PageService::DEFAULT_NAMESPACE] = self::DEFAULT_TOKENS;
        }

        ksort($namespaces);

        return $namespaces;
    }

    /**
     * @param array<string, mixed> $tokens
     * @return array<string, mixed>
     */
    private function fetchStoredTokens(string $namespace): array
    {
        $config = $this->configService->getConfigForEvent($namespace);
        $stored = $config['designTokens'] ?? [];

        if (!is_array($stored)) {
            return [];
        }

        return $this->validateTokens($stored);
    }

    /**
     * @param array<string, mixed> $tokens
     * @return array<string, mixed>
     */
    private function mergeWithDefaults(array $tokens): array
    {
        $merged = self::DEFAULT_TOKENS;
        foreach ($tokens as $group => $values) {
            if (!is_array($values) || !array_key_exists($group, $merged)) {
                continue;
            }
            foreach ($values as $key => $value) {
                if (array_key_exists($key, $merged[$group]) && $value !== null && $value !== '') {
                    $merged[$group][$key] = $value;
                }
            }
        }

        return $merged;
    }

    /**
     * @param array<string, mixed> $tokens
     * @return array<string, mixed>
     */
    private function validateTokens(array $tokens): array
    {
        $validated = ['brand' => [], 'layout' => [], 'typography' => [], 'components' => []];

        $brand = $tokens['brand'] ?? [];
        if (is_array($brand)) {
            $primary = $this->normalizeColor($brand['primary'] ?? null);
            $accent = $this->normalizeColor($brand['accent'] ?? null);
            if ($primary !== null) {
                $validated['brand']['primary'] = $primary;
            }
            if ($accent !== null) {
                $validated['brand']['accent'] = $accent;
            }
        }

        $layout = $tokens['layout'] ?? [];
        if (is_array($layout)) {
            $profile = $this->normalizeChoice($layout['profile'] ?? null, self::LAYOUT_PROFILES);
            if ($profile !== null) {
                $validated['layout']['profile'] = $profile;
            }
        }

        $typography = $tokens['typography'] ?? [];
        if (is_array($typography)) {
            $preset = $this->normalizeChoice($typography['preset'] ?? null, self::TYPOGRAPHY_PRESETS);
            if ($preset !== null) {
                $validated['typography']['preset'] = $preset;
            }
        }

        $components = $tokens['components'] ?? [];
        if (is_array($components)) {
            $cardStyle = $this->normalizeChoice($components['cardStyle'] ?? null, self::CARD_STYLES);
            $buttonStyle = $this->normalizeChoice($components['buttonStyle'] ?? null, self::BUTTON_STYLES);
            if ($cardStyle !== null) {
                $validated['components']['cardStyle'] = $cardStyle;
            }
            if ($buttonStyle !== null) {
                $validated['components']['buttonStyle'] = $buttonStyle;
            }
        }

        return $validated;
    }

    private function normalizeNamespace(string $namespace): string
    {
        return trim(strtolower($namespace ?: PageService::DEFAULT_NAMESPACE));
    }

    /**
     * @param list<string> $choices
     */
    private function normalizeChoice(mixed $value, array $choices): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $normalized = strtolower(trim($value));

        return in_array($normalized, $choices, true) ? $normalized : null;
    }

    private function normalizeColor(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $normalized = strtolower(trim($value));
        if ($normalized === '') {
            return null;
        }
        if (!str_starts_with($normalized, '#')) {
            $normalized = '#' . $normalized;
        }
        $pattern = '/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i';

        return preg_match($pattern, $normalized) === 1 ? $normalized : null;
    }

    /**
     * @param array<string, array<string, mixed>> $namespaces
     */
    private function buildCss(array $namespaces): string
    {
        $blocks = [];
        $blocks[] = "/**\n * Auto-generated. Do not edit manually.\n */";
        foreach ($namespaces as $namespace => $tokens) {
            $selector = $namespace === PageService::DEFAULT_NAMESPACE ? ':root' : '[data-namespace="' . $namespace . '"]';
            $lines = [
                $selector . ' {',
                '  --brand-primary: ' . $this->escapeCssValue($tokens['brand']['primary'] ?? self::DEFAULT_TOKENS['brand']['primary']) . ';',
                '  --brand-accent: ' . $this->escapeCssValue($tokens['brand']['accent'] ?? self::DEFAULT_TOKENS['brand']['accent']) . ';',
                '  --layout-profile: ' . $this->escapeCssValue($tokens['layout']['profile'] ?? self::DEFAULT_TOKENS['layout']['profile']) . ';',
                '  --typography-preset: ' . $this->escapeCssValue($tokens['typography']['preset'] ?? self::DEFAULT_TOKENS['typography']['preset']) . ';',
                '  --components-card-style: ' . $this->escapeCssValue($tokens['components']['cardStyle'] ?? self::DEFAULT_TOKENS['components']['cardStyle']) . ';',
                '  --components-button-style: ' . $this->escapeCssValue($tokens['components']['buttonStyle'] ?? self::DEFAULT_TOKENS['components']['buttonStyle']) . ';',
                '}',
            ];
            $blocks[] = implode("\n", $lines);
        }

        $blocks[] = ':root {';
        $blocks[] = '  --brand-surface: #0f172a;';
        $blocks[] = '  --brand-on-surface: #ffffff;';
        $blocks[] = '  --section-gap: 2.25rem;';
        $blocks[] = '  --card-radius: 10px;';
        $blocks[] = '  --font-heading-weight: 700;';
        $blocks[] = '}';

        return implode("\n\n", $blocks) . "\n";
    }

    private function escapeCssValue(string $value): string
    {
        return str_replace(['\n', '\r'], '', $value);
    }
}
