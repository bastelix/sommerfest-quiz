<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\NamespaceRepository;
use InvalidArgumentException;
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
            'secondary' => '#f97316',
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

    private const DEFAULT_MARKETING_SURFACE = 'var(--surface-card, #ffffff)';

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

    private NamespaceValidator $namespaceValidator;

    private NamespaceDesignFileRepository $designFiles;
    private NamespaceRepository $namespaceRepository;

    private ColorContrastService $contrastService;

    public function __construct(
        PDO $pdo,
        ?ConfigService $configService = null,
        ?string $cssPath = null,
        ?NamespaceDesignFileRepository $designFiles = null,
        ?NamespaceRepository $namespaceRepository = null,
        ?ColorContrastService $contrastService = null
    ) {
        $this->pdo = $pdo;
        $this->configService = $configService ?? new ConfigService($pdo);
        $this->cssPath = $cssPath ?? dirname(__DIR__, 2) . '/public/css/namespace-tokens.css';
        $this->namespaceValidator = new NamespaceValidator();
        $this->designFiles = $designFiles ?? new NamespaceDesignFileRepository();
        $this->namespaceRepository = $namespaceRepository ?? new NamespaceRepository($pdo);
        $this->contrastService = $contrastService ?? new ColorContrastService();
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

        if ($normalized === PageService::DEFAULT_NAMESPACE) {
            $storedDefault = $this->fetchStoredTokens($normalized);

            if ($storedDefault === []) {
                $fileTokens = $this->designFiles->loadTokens($normalized);
                if ($fileTokens !== []) {
                    return $this->mergeWithDefaults($this->validateTokens($fileTokens));
                }
            }

            return $storedDefault !== []
                ? $this->mergeWithDefaults($storedDefault)
                : self::DEFAULT_TOKENS;
        }

        $stored = $this->fetchStoredTokens($normalized);

        if ($stored === []) {
            $fileTokens = $this->designFiles->loadTokens($normalized);
            if ($fileTokens !== []) {
                $defaultTokens = $this->fetchStoredTokens(PageService::DEFAULT_NAMESPACE);
                $baseTokens = $defaultTokens !== []
                    ? $this->mergeWithDefaults($defaultTokens)
                    : self::DEFAULT_TOKENS;

                return $this->mergeTokens($baseTokens, $this->validateTokens($fileTokens));
            }

            $defaultTokens = $this->fetchStoredTokens(PageService::DEFAULT_NAMESPACE);
            return $defaultTokens !== []
                ? $this->mergeWithDefaults($defaultTokens)
                : self::DEFAULT_TOKENS;
        }

        $defaultTokens = $this->fetchStoredTokens(PageService::DEFAULT_NAMESPACE);
        $baseTokens = $defaultTokens !== []
            ? $this->mergeWithDefaults($defaultTokens)
            : self::DEFAULT_TOKENS;

        return $this->mergeTokens($baseTokens, $stored);
    }

    /**
     * @param array<string, mixed> $tokens
     */
    public function persistTokens(string $namespace, array $tokens): array
    {
        $normalizedNamespace = $this->normalizeNamespace($namespace);
        $validated = $this->validateTokens($tokens);

        $existingMeta = $this->fetchImportMeta($normalizedNamespace);
        if ($existingMeta !== null) {
            $validated['_meta'] = $existingMeta;
        }

        $payload = ['event_uid' => $normalizedNamespace, 'designTokens' => $validated];
        $this->configService->ensureConfigForEvent($normalizedNamespace);
        $this->configService->saveConfig($payload);
        $this->rebuildStylesheet();

        return $validated;
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

    /**
     * Import a design preset into a namespace.
     *
     * Loads the preset from the content/design directory and persists
     * both the design tokens and the color configuration for the
     * target namespace.
     *
     * @return array{tokens: array<string, mixed>, colors: array<string, mixed>, effects: array<string, mixed>}
     */
    public function importDesign(string $namespace, string $preset): array
    {
        $normalizedNamespace = $this->normalizeNamespace($namespace);

        $presetData = $this->designFiles->loadFile($preset);
        if ($presetData === []) {
            throw new InvalidArgumentException('design-preset-not-found');
        }

        $tokens = $presetData['tokens'] ?? $presetData['designTokens'] ?? [];
        if (!is_array($tokens)) {
            $tokens = [];
        }

        $config = $presetData['config'] ?? [];
        if (!is_array($config)) {
            $config = [];
        }

        $configTokens = $config['designTokens'] ?? [];
        if (is_array($configTokens) && $configTokens !== []) {
            $tokens = $this->mergeTokens($tokens, $configTokens);
        }

        $validated = $this->validateTokens($tokens);
        $merged = $this->mergeWithDefaults($validated);
        $merged['_meta'] = [
            'sourcePreset' => $preset,
            'importedAt' => date('c'),
        ];

        $this->configService->ensureConfigForEvent($normalizedNamespace);
        $this->configService->saveConfig([
            'event_uid' => $normalizedNamespace,
            'designTokens' => $merged,
        ]);

        $colors = $config['colors'] ?? [];
        if (is_array($colors) && $colors !== []) {
            $existingConfig = $this->configService->getConfigForEvent($normalizedNamespace);
            $existingColors = is_array($existingConfig['colors'] ?? null) ? $existingConfig['colors'] : [];
            $mergedColors = array_merge($existingColors, $colors);
            $this->configService->saveConfig([
                'event_uid' => $normalizedNamespace,
                'colors' => $mergedColors,
            ]);
        }

        $effects = $presetData['effects'] ?? [];
        if (is_array($effects) && $effects !== []) {
            $effectsService = new EffectsPolicyService($this->configService);
            $effectsService->persist($normalizedNamespace, $effects);
        }

        $this->rebuildStylesheet();

        return [
            'tokens' => $merged,
            'colors' => is_array($colors) ? $colors : [],
            'effects' => is_array($effects) ? $effects : [],
        ];
    }

    /**
     * @return list<array{name: string, label: string, description: string}>
     */
    public function listAvailablePresets(): array
    {
        $presets = [];

        foreach ($this->designFiles->listNamespaces() as $name) {
            $data = $this->designFiles->loadFile($name);
            $meta = $data['meta'] ?? [];
            if (!is_array($meta)) {
                $meta = [];
            }

            $presets[] = [
                'name' => $name,
                'label' => is_string($meta['name'] ?? null) ? $meta['name'] : ucfirst($name),
                'description' => is_string($meta['description'] ?? null) ? $meta['description'] : '',
            ];
        }

        return $presets;
    }

    /**
     * Remove the namespace-specific CSS directory and rebuild the global stylesheet.
     */
    public function cleanupNamespaceCss(string $namespace): void
    {
        $normalized = $this->normalizeNamespace($namespace);
        $baseDirectory = dirname($this->cssPath);
        $namespaceDirectory = $baseDirectory . '/' . $normalized;

        if (is_dir($namespaceDirectory)) {
            $tokenFile = $namespaceDirectory . '/namespace-tokens.css';
            if (is_file($tokenFile)) {
                unlink($tokenFile);
            }
            // Only remove the directory if it is empty after deleting the token file.
            if (is_dir($namespaceDirectory) && count((array) scandir($namespaceDirectory)) <= 2) {
                rmdir($namespaceDirectory);
            }
        }

        $this->rebuildStylesheet();
    }

    public function rebuildStylesheet(): void
    {
        $namespaces = $this->fetchAllNamespaceTokens();
        $namespaceList = $this->listNamespacesForStyles(array_map('strval', array_keys($namespaces)));

        // Include all known namespaces in the global CSS so the fallback
        // provides correct token values even when a namespace-specific file
        // does not exist (e.g. namespace was created but design was never saved).
        foreach ($namespaceList as $ns) {
            if (!array_key_exists($ns, $namespaces)) {
                $namespaces[$ns] = $this->getTokensForNamespace($ns);
            }
        }

        $css = $this->buildCss($namespaces);
        $this->writeCssFile($this->cssPath, $css);
        $this->mirrorCssToNamespacePaths($namespaceList);
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
            $validated = $this->validateTokens($tokens);
            if (!$this->hasTokenOverrides($validated)) {
                continue;
            }
            $namespaces[$namespace] = $this->mergeWithDefaults($validated);
        }

        $fileNamespaces = $this->designFiles->listNamespaces();
        foreach ($fileNamespaces as $namespace) {
            if (array_key_exists($namespace, $namespaces)) {
                continue;
            }

            $tokens = $this->designFiles->loadTokens($namespace);
            if ($tokens === []) {
                continue;
            }
            $validated = $this->validateTokens($tokens);
            if (!$this->hasTokenOverrides($validated)) {
                continue;
            }

            $namespaces[$namespace] = $this->mergeWithDefaults($validated);
        }

        if (!array_key_exists(PageService::DEFAULT_NAMESPACE, $namespaces)) {
            $namespaces[PageService::DEFAULT_NAMESPACE] = self::DEFAULT_TOKENS;
        }

        ksort($namespaces);

        return $namespaces;
    }

    /**
     * @param array<string, mixed> $tokens
     */
    private function hasTokenOverrides(array $tokens): bool
    {
        foreach ($tokens as $group) {
            if (is_array($group) && $group !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return the import metadata for a namespace, or null if none exists.
     *
     * @return array{sourcePreset: string, importedAt: string}|null
     */
    public function getImportMeta(string $namespace): ?array
    {
        return $this->fetchImportMeta($this->normalizeNamespace($namespace));
    }

    /**
     * @return array{sourcePreset: string, importedAt: string}|null
     */
    private function fetchImportMeta(string $namespace): ?array
    {
        $config = $this->configService->getConfigForEvent($namespace);
        $stored = $config['designTokens'] ?? [];

        if (!is_array($stored)) {
            return null;
        }

        $meta = $stored['_meta'] ?? null;
        if (!is_array($meta) || !isset($meta['sourcePreset'], $meta['importedAt'])) {
            return null;
        }

        return [
            'sourcePreset' => (string) $meta['sourcePreset'],
            'importedAt' => (string) $meta['importedAt'],
        ];
    }

    /**
     * @param string $namespace
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
        return $this->mergeTokens(self::DEFAULT_TOKENS, $tokens);
    }

    /**
     * @param array<string, mixed> $baseTokens
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function mergeTokens(array $baseTokens, array $overrides): array
    {
        $merged = $baseTokens;
        foreach ($overrides as $group => $values) {
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
            $secondary = $this->normalizeColor($brand['secondary'] ?? null);
            if ($primary !== null) {
                $validated['brand']['primary'] = $primary;
            }
            if ($accent !== null) {
                $validated['brand']['accent'] = $accent;
            }
            if ($secondary !== null) {
                $validated['brand']['secondary'] = $secondary;
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
        $normalized = $this->namespaceValidator->normalize($namespace);

        if ($normalized === '') {
            throw new InvalidArgumentException('namespace-empty');
        }

        $this->namespaceValidator->assertValid($normalized);

        return $normalized;
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

        $defaultTokens = $this->mergeWithDefaults($namespaces[PageService::DEFAULT_NAMESPACE] ?? []);
        $blocks[] = $this->renderTokenCssBlock(':root', $defaultTokens);
        $blocks[] = $this->renderTokenCssBlock('[data-namespace="' . PageService::DEFAULT_NAMESPACE . '"]', $defaultTokens);

        foreach ($namespaces as $namespace => $tokens) {
            if ($namespace === PageService::DEFAULT_NAMESPACE) {
                continue;
            }

            $mergedTokens = $this->mergeTokens($defaultTokens, $this->mergeWithDefaults($tokens));
            $blocks[] = $this->renderTokenCssBlock('[data-namespace="' . $namespace . '"]', $mergedTokens);
        }

        $blocks[] = $this->renderBaseTokenBlock(':root');

        return implode("\n\n", $blocks) . "\n";
    }

    /**
     * @param array<string, array<string, string>> $tokens
     * @param list<string> $extraLines
     */
    private function renderTokenCssBlock(string $selector, array $tokens, array $extraLines = []): string
    {
        $brandPrimary = $tokens['brand']['primary'] ?? self::DEFAULT_TOKENS['brand']['primary'];
        $brandAccent = $tokens['brand']['accent'] ?? self::DEFAULT_TOKENS['brand']['accent'];
        $brandSecondary = $tokens['brand']['secondary'] ?? self::DEFAULT_TOKENS['brand']['secondary'];

        $contrastTokens = $this->contrastService->resolveContrastTokens([
            'primary' => $brandPrimary,
            'secondary' => $brandSecondary,
            'accent' => $brandAccent,
            'surface' => '#ffffff',
            'surfaceMuted' => '#eef2f7',
            'surfacePage' => '#f7f9fb',
        ]);

        $lines = [
            $selector . ' {',
            '  --brand-primary: ' . $this->escapeCssValue($brandPrimary) . ';',
            '  --brand-accent: ' . $this->escapeCssValue($brandAccent) . ';',
            '  --brand-secondary: ' . $this->escapeCssValue($brandSecondary) . ';',
            '  --contrast-text-on-primary: ' . $this->escapeCssValue($contrastTokens['textOnPrimary'] ?? '#ffffff') . ';',
            '  --contrast-text-on-secondary: ' . $this->escapeCssValue($contrastTokens['textOnSecondary'] ?? '#ffffff') . ';',
            '  --contrast-text-on-accent: ' . $this->escapeCssValue($contrastTokens['textOnAccent'] ?? '#ffffff') . ';',
            '  --contrast-text-on-surface: ' . $this->escapeCssValue($contrastTokens['textOnSurface'] ?? '#000000') . ';',
            '  --contrast-text-on-surface-muted: ' . $this->escapeCssValue($contrastTokens['textOnSurfaceMuted'] ?? '#000000') . ';',
            '  --contrast-text-on-page: ' . $this->escapeCssValue($contrastTokens['textOnPage'] ?? '#000000') . ';',
            '  --marketing-primary: ' . $this->escapeCssValue($brandPrimary) . ';',
            '  --marketing-accent: ' . $this->escapeCssValue($brandAccent) . ';',
            '  --marketing-secondary: ' . $this->escapeCssValue($brandSecondary) . ';',
            '  --marketing-link: ' . $this->escapeCssValue($brandPrimary) . ';',
            '  --marketing-surface: ' . $this->escapeCssValue(self::DEFAULT_MARKETING_SURFACE) . ';',
            '  --marketing-white: #ffffff;',
            '  --marketing-black: #000000;',
            '  --marketing-black-rgb: 0 0 0;',
            '  --marketing-ink: #0f172a;',
            '  --layout-profile: ' . $this->escapeCssValue($tokens['layout']['profile'] ?? self::DEFAULT_TOKENS['layout']['profile']) . ';',
            '  --typography-preset: ' . $this->escapeCssValue($tokens['typography']['preset'] ?? self::DEFAULT_TOKENS['typography']['preset']) . ';',
            '  --components-card-style: ' . $this->escapeCssValue($tokens['components']['cardStyle'] ?? self::DEFAULT_TOKENS['components']['cardStyle']) . ';',
            '  --components-button-style: ' . $this->escapeCssValue($tokens['components']['buttonStyle'] ?? self::DEFAULT_TOKENS['components']['buttonStyle']) . ';',
        ];
        if ($extraLines !== []) {
            $lines = array_merge($lines, $extraLines);
        }
        $lines[] = '}';

        return implode("\n", $lines);
    }

    private function renderBaseTokenBlock(string $selector): string
    {
        return implode("\n", array_merge([$selector . ' {'], $this->getBaseTokenLines(), ['}']));
    }

    /**
     * @return list<string>
     */
    private function getBaseTokenLines(): array
    {
        return [
            '  --brand-surface: #0f172a;',
            '  --brand-on-surface: #ffffff;',
            '  --section-gap: 2.25rem;',
            '  --card-radius: 10px;',
            '  --font-heading-weight: 700;',
        ];
    }

    private function escapeCssValue(string $value): string
    {
        return str_replace(['\n', '\r'], '', $value);
    }

    private function writeCssFile(string $path, string $contents): void
    {
        $previousMtime = is_file($path) ? filemtime($path) : false;
        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException('Unable to write namespace token stylesheet');
        }
        clearstatcache(false, $path);
        $currentMtime = filemtime($path);
        if ($previousMtime !== false && $currentMtime !== false && $currentMtime <= $previousMtime) {
            $forcedTime = $previousMtime + 1;
            if (!touch($path, $forcedTime)) {
                throw new RuntimeException('Unable to update namespace token stylesheet timestamp');
            }
            clearstatcache(false, $path);
        }
    }

    /**
     * @param list<string> $namespaces
     */
    private function mirrorCssToNamespacePaths(array $namespaces): void
    {
        $baseDirectory = dirname($this->cssPath);

        foreach ($namespaces as $namespace) {
            $namespace = strtolower(trim($namespace));
            if ($namespace === '' || $namespace === PageService::DEFAULT_NAMESPACE) {
                continue;
            }

            $namespaceDirectory = $baseDirectory . '/' . $namespace;
            if (!is_dir($namespaceDirectory) && !mkdir($namespaceDirectory, 0777, true) && !is_dir($namespaceDirectory)) {
                throw new RuntimeException('Unable to create namespace directory for tokens');
            }

            $namespacedPath = $namespaceDirectory . '/namespace-tokens.css';
            $this->writeCssFile($namespacedPath, $this->buildNamespacedTokenCss($namespace));
        }
    }

    private function buildNamespacedTokenCss(string $namespace): string
    {
        $tokens = $this->getTokensForNamespace($namespace);
        $selector = 'html[data-namespace="' . $namespace . '"]';

        return implode("\n\n", [
            "/**\n * Auto-generated. Do not edit manually.\n */",
            $this->renderTokenCssBlock($selector, $tokens, $this->getBaseTokenLines()),
        ]) . "\n";
    }

    /**
     * @param list<string> $tokenNamespaces
     * @return list<string>
     */
    private function listNamespacesForStyles(array $tokenNamespaces): array
    {
        $namespaces = [];
        $addNamespace = function (?string $namespace) use (&$namespaces): void {
            if ($namespace === null || $namespace === '') {
                return;
            }
            $namespaces['ns:' . $namespace] = $namespace;
        };

        foreach ($tokenNamespaces as $namespace) {
            $addNamespace($this->namespaceValidator->normalizeCandidate($namespace));
        }

        try {
            foreach ($this->namespaceRepository->list() as $entry) {
                $addNamespace($this->namespaceValidator->normalizeCandidate((string) $entry['namespace']));
            }
        } catch (RuntimeException $exception) {
            // Namespace table might not exist in minimal setups; ignore.
        }

        foreach ($this->designFiles->listNamespaces() as $namespace) {
            $addNamespace($this->namespaceValidator->normalizeCandidate($namespace));
        }

        $addNamespace(PageService::DEFAULT_NAMESPACE);

        $sorted = array_values($namespaces);
        sort($sorted);

        return $sorted;
    }
}
