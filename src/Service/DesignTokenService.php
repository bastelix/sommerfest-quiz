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

    private const DEFAULT_MARKETING_SURFACE_DARK = 'var(--surface-card, #1a2636)';

    /**
     * Default dark-mode surface colors used by variables.css.
     *
     * @var array<string, string>
     */
    private const DARK_SURFACES = [
        'surface' => '#1a2636',
        'surfaceMuted' => '#0b111a',
        'surfacePage' => '#0a0d12',
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

        foreach ($namespaces as $namespace => $tokens) {
            if ($namespace === PageService::DEFAULT_NAMESPACE) {
                continue;
            }

            $mergedTokens = $this->mergeTokens($defaultTokens, $this->mergeWithDefaults($tokens));
            $blocks[] = $this->renderTokenCssBlock('[data-namespace="' . $namespace . '"]', $mergedTokens);
            $blocks[] = $this->renderDarkTokenCssBlock(
                '[data-namespace="' . $namespace . '"][data-theme="dark"]',
                $mergedTokens,
            );

            $customCss = $this->getCustomCssForNamespace($namespace);
            if ($customCss !== '') {
                $blocks[] = "/* Custom CSS: {$namespace} */\n"
                    . $this->autoScopeCustomCss($namespace, $customCss);
            }
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
            '  --contrast-text-on-primary: '
                . $this->escapeCssValue($contrastTokens['textOnPrimary'] ?? '#ffffff') . ';',
            '  --contrast-text-on-secondary: '
                . $this->escapeCssValue($contrastTokens['textOnSecondary'] ?? '#ffffff') . ';',
            '  --contrast-text-on-accent: '
                . $this->escapeCssValue($contrastTokens['textOnAccent'] ?? '#ffffff') . ';',
            '  --contrast-text-on-surface: '
                . $this->escapeCssValue($contrastTokens['textOnSurface'] ?? '#000000') . ';',
            '  --contrast-text-on-surface-muted: '
                . $this->escapeCssValue($contrastTokens['textOnSurfaceMuted'] ?? '#000000') . ';',
            '  --contrast-text-on-page: '
                . $this->escapeCssValue($contrastTokens['textOnPage'] ?? '#000000') . ';',
            '  --marketing-primary: ' . $this->escapeCssValue($brandPrimary) . ';',
            '  --marketing-accent: ' . $this->escapeCssValue($brandAccent) . ';',
            '  --marketing-secondary: ' . $this->escapeCssValue($brandSecondary) . ';',
            '  --marketing-link: ' . $this->escapeCssValue($brandPrimary) . ';',
            '  --marketing-surface: ' . $this->escapeCssValue(self::DEFAULT_MARKETING_SURFACE) . ';',
            '  --marketing-white: #ffffff;',
            '  --marketing-black: #000000;',
            '  --marketing-black-rgb: 0 0 0;',
            '  --marketing-ink: #0f172a;',
            '  --layout-profile: '
                . $this->escapeCssValue(
                    $tokens['layout']['profile'] ?? self::DEFAULT_TOKENS['layout']['profile']
                ) . ';',
            '  --typography-preset: '
                . $this->escapeCssValue(
                    $tokens['typography']['preset'] ?? self::DEFAULT_TOKENS['typography']['preset']
                ) . ';',
            '  --components-card-style: '
                . $this->escapeCssValue(
                    $tokens['components']['cardStyle'] ?? self::DEFAULT_TOKENS['components']['cardStyle']
                ) . ';',
            '  --components-button-style: '
                . $this->escapeCssValue(
                    $tokens['components']['buttonStyle'] ?? self::DEFAULT_TOKENS['components']['buttonStyle']
                ) . ';',
        ];
        if ($extraLines !== []) {
            $lines = array_merge($lines, $extraLines);
        }
        $lines[] = '}';

        return implode("\n", $lines);
    }

    /**
     * Render a dark-mode CSS block for a namespace.
     *
     * Uses a compound selector that combines the namespace attribute with
     * [data-theme="dark"] so it beats the generic dark-mode definitions in
     * variables.css (:root[data-theme="dark"] has specificity 0,2,0; the
     * compound selector html[data-namespace="x"][data-theme="dark"] yields
     * 0,2,1).
     *
     * @param array<string, array<string, string>> $tokens
     * @param list<string> $extraLines
     */
    private function renderDarkTokenCssBlock(string $selector, array $tokens, array $extraLines = []): string
    {
        $brandPrimary = $tokens['brand']['primary'] ?? self::DEFAULT_TOKENS['brand']['primary'];
        $brandAccent = $tokens['brand']['accent'] ?? self::DEFAULT_TOKENS['brand']['accent'];
        $brandSecondary = $tokens['brand']['secondary'] ?? self::DEFAULT_TOKENS['brand']['secondary'];

        $contrastTokens = $this->contrastService->resolveContrastTokens([
            'primary' => $brandPrimary,
            'secondary' => $brandSecondary,
            'accent' => $brandAccent,
            'surface' => self::DARK_SURFACES['surface'],
            'surfaceMuted' => self::DARK_SURFACES['surfaceMuted'],
            'surfacePage' => self::DARK_SURFACES['surfacePage'],
        ]);

        $lines = [
            $selector . ' {',
            '  --brand-primary: ' . $this->escapeCssValue($brandPrimary) . ';',
            '  --brand-accent: ' . $this->escapeCssValue($brandAccent) . ';',
            '  --brand-secondary: ' . $this->escapeCssValue($brandSecondary) . ';',
            '  --contrast-text-on-primary: '
                . $this->escapeCssValue($contrastTokens['textOnPrimary'] ?? '#ffffff') . ';',
            '  --contrast-text-on-secondary: '
                . $this->escapeCssValue($contrastTokens['textOnSecondary'] ?? '#ffffff') . ';',
            '  --contrast-text-on-accent: '
                . $this->escapeCssValue($contrastTokens['textOnAccent'] ?? '#ffffff') . ';',
            '  --contrast-text-on-surface: '
                . $this->escapeCssValue($contrastTokens['textOnSurface'] ?? '#f2f5fa') . ';',
            '  --contrast-text-on-surface-muted: '
                . $this->escapeCssValue($contrastTokens['textOnSurfaceMuted'] ?? '#f2f5fa') . ';',
            '  --contrast-text-on-page: ' . $this->escapeCssValue($contrastTokens['textOnPage'] ?? '#f2f5fa') . ';',
            '  --marketing-primary: ' . $this->escapeCssValue($brandPrimary) . ';',
            '  --marketing-accent: ' . $this->escapeCssValue($brandAccent) . ';',
            '  --marketing-secondary: ' . $this->escapeCssValue($brandSecondary) . ';',
            '  --marketing-link: ' . $this->escapeCssValue($brandPrimary) . ';',
            '  --marketing-surface: ' . $this->escapeCssValue(self::DEFAULT_MARKETING_SURFACE_DARK) . ';',
            '  --marketing-white: #ffffff;',
            '  --marketing-black: #000000;',
            '  --marketing-black-rgb: 0 0 0;',
            '  --marketing-ink: #f2f5fa;',
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
            if (
                !is_dir($namespaceDirectory)
                && !mkdir($namespaceDirectory, 0777, true)
                && !is_dir($namespaceDirectory)
            ) {
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
        $darkSelector = 'html[data-namespace="' . $namespace . '"][data-theme="dark"]';

        $blocks = [
            "/**\n * Auto-generated. Do not edit manually.\n */",
            $this->renderTokenCssBlock($selector, $tokens, $this->getBaseTokenLines()),
            $this->renderDarkTokenCssBlock($darkSelector, $tokens),
        ];

        $customCss = $this->getCustomCssForNamespace($namespace);
        if ($customCss !== '') {
            $blocks[] = "/* Custom CSS overrides */\n" . $customCss;
        }

        return implode("\n\n", $blocks) . "\n";
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

    public function getCustomCssForNamespace(string $namespace): string
    {
        $normalized = $this->normalizeNamespace($namespace);
        $config = $this->configService->getConfigForEvent($normalized);
        $customCss = $config['customCss'] ?? $config['custom_css'] ?? '';
        if (!is_string($customCss) || trim($customCss) === '') {
            return '';
        }

        return trim($customCss);
    }

    public function persistCustomCss(string $namespace, string $css): void
    {
        $normalizedNamespace = $this->normalizeNamespace($namespace);
        $sanitizer = new CssSanitizer();
        $sanitized = $sanitizer->sanitize($css);

        $this->configService->ensureConfigForEvent($normalizedNamespace);
        $this->configService->saveConfig([
            'event_uid' => $normalizedNamespace,
            'customCss' => $sanitized,
        ]);
        $this->rebuildStylesheet();
    }

    private function autoScopeCustomCss(string $namespace, string $css): string
    {
        $selector = '[data-namespace="' . $namespace . '"]';
        if (str_contains($css, $selector)) {
            return $css;
        }

        return $selector . " {\n" . $css . "\n}";
    }

    /**
     * Return the full design manifest for a namespace.
     *
     * Provides a complete picture of all design tokens, their resolved values,
     * the token hierarchy, block-level token options, section intents, and
     * legacy aliases. Designed for external API consumers.
     *
     * @return array<string, mixed>
     */
    public function getDesignManifest(string $namespace): array
    {
        $ns = $this->normalizeNamespace($namespace);
        $tokens = $this->getTokensForNamespace($ns);
        $importMeta = $this->getImportMeta($ns);

        $brandPrimary = $tokens['brand']['primary'] ?? self::DEFAULT_TOKENS['brand']['primary'];
        $brandAccent = $tokens['brand']['accent'] ?? self::DEFAULT_TOKENS['brand']['accent'];
        $brandSecondary = $tokens['brand']['secondary'] ?? self::DEFAULT_TOKENS['brand']['secondary'];

        return [
            'namespace' => $ns,
            'tokenHierarchy' => [
                'semantic' => [
                    'description' => 'Core tokens defined in variables.css. Apply to all namespaces unless overridden.',
                    'tokens' => [
                        '--surface-page', '--surface-section', '--surface-card', '--surface-muted', '--surface-subtle',
                        '--text-body', '--text-heading', '--text-muted',
                        '--text-on-primary', '--text-on-secondary', '--text-on-accent',
                        '--border-muted', '--border-strong',
                        '--space-section', '--card-radius',
                        '--danger-500', '--danger-600',
                    ],
                ],
                'namespace' => [
                    'description' => 'Brand-specific overrides applied via [data-namespace] attribute.',
                    'tokens' => [
                        '--brand-primary', '--brand-accent', '--brand-secondary',
                        '--contrast-text-on-primary', '--contrast-text-on-secondary', '--contrast-text-on-accent',
                        '--contrast-text-on-surface', '--contrast-text-on-surface-muted', '--contrast-text-on-page',
                        '--marketing-primary', '--marketing-accent', '--marketing-secondary', '--marketing-link',
                        '--marketing-surface', '--marketing-ink',
                        '--layout-profile', '--typography-preset',
                        '--components-card-style', '--components-button-style',
                    ],
                ],
                'component' => [
                    'description' => 'Component-level tokens scoped to specific blocks or sections.',
                    'tokens' => [
                        '--dt-hero-bg-start', '--dt-hero-bg-end',
                        '--dt-cta-color', '--dt-cta-hover',
                        '--dt-accent-light', '--dt-success-accent', '--dt-star-fill',
                    ],
                ],
            ],
            'resolvedValues' => [
                '--brand-primary' => [
                    'value' => $brandPrimary,
                    'category' => 'brand',
                    'description' => 'Primary brand color',
                ],
                '--brand-accent' => [
                    'value' => $brandAccent,
                    'category' => 'brand',
                    'description' => 'Accent color for highlights and CTAs',
                ],
                '--brand-secondary' => [
                    'value' => $brandSecondary,
                    'category' => 'brand',
                    'description' => 'Secondary brand color',
                ],
                '--layout-profile' => [
                    'value' => $tokens['layout']['profile'] ?? 'standard',
                    'category' => 'layout',
                    'description' => 'Layout width profile',
                    'options' => self::LAYOUT_PROFILES,
                ],
                '--typography-preset' => [
                    'value' => $tokens['typography']['preset'] ?? 'modern',
                    'category' => 'typography',
                    'description' => 'Typography font family preset',
                    'options' => self::TYPOGRAPHY_PRESETS,
                ],
                '--components-card-style' => [
                    'value' => $tokens['components']['cardStyle'] ?? 'rounded',
                    'category' => 'components',
                    'description' => 'Card border-radius style',
                    'options' => self::CARD_STYLES,
                ],
                '--components-button-style' => [
                    'value' => $tokens['components']['buttonStyle'] ?? 'filled',
                    'category' => 'components',
                    'description' => 'Button visual style',
                    'options' => self::BUTTON_STYLES,
                ],
            ],
            'tokens' => $tokens,
            'importMeta' => $importMeta,
            'blockTokens' => [
                'background' => ['primary', 'secondary', 'muted', 'accent', 'surface'],
                'spacing' => ['small', 'normal', 'large'],
                'width' => ['narrow', 'normal', 'wide'],
                'columns' => ['single', 'two', 'three', 'four'],
                'accent' => ['brandA', 'brandB', 'brandC'],
            ],
            'sectionAppearances' => [
                'contained', 'full', 'card', 'default',
                'surface', 'contrast', 'image', 'image-fixed',
            ],
            'sectionIntents' => ['plain', 'content', 'feature', 'highlight', 'hero'],
            'legacyAliases' => [
                '--bg-page' => '--surface-page',
                '--bg-section' => '--surface-section',
                '--bg-card' => '--surface-card',
                '--bg-surface' => '--surface-card',
                '--bg-muted' => '--surface-muted',
                '--bg-subtle' => '--surface-subtle',
                '--text-default' => '--text-body',
                '--text-primary' => '--text-body',
                '--text-secondary' => '--text-muted',
                '--text-link' => '--brand-primary',
                '--accent-primary' => '--brand-primary',
                '--accent-secondary' => '--brand-secondary',
                '--accent-color' => '--brand-primary',
                '--section-gap' => '--space-section',
            ],
            'renderingGuide' => self::buildRenderingGuide(),
        ];
    }

    /**
     * Build the rendering guide that documents how block JSON maps to visual output.
     *
     * This data helps external editors (MCP clients, AI agents) make informed
     * decisions about section styling without trial-and-error.
     *
     * @return array<string, mixed>
     */
    private static function buildRenderingGuide(): array
    {
        return [
            'sectionHtmlStructure' => [
                'description' => 'Every block is wrapped in a <section> element. '
                    . 'Visual styling is driven by data-attributes and CSS custom properties on this wrapper.',
                'dataAttributes' => [
                    'data-block-id' => 'Block ID from the block contract',
                    'data-block-type' => 'Block type (e.g. feature_list, stat_strip)',
                    'data-block-variant' => 'Block variant (e.g. icon_grid, trust_band)',
                    'data-section-intent' => 'Resolved intent (content, plain, feature, highlight, hero)',
                    'data-section-layout' => 'Layout mode when card layout is used',
                    'data-section-background-mode' => 'Background mode (none, color, image)',
                    'data-section-background-color-token' => 'Color token when background mode is color',
                    'data-section-surface-token' => 'Surface token controlling the section surface',
                    'data-section-text-token' => 'Text token controlling text colour',
                    'data-viewport-height' => 'Viewport height setting (full, reduced, minus-next)',
                    'data-effect' => 'Animation effect (reveal, heroIntro)',
                ],
                'cssVariables' => [
                    '--section-surface' => 'Base surface colour of the section',
                    '--section-bg-color' => 'Background colour (defaults to --section-surface)',
                    '--section-text-color' => 'Text colour for the section',
                    '--section-bg-image' => 'Background image URL (when mode is image)',
                    '--section-bg-attachment' => 'scroll or fixed (parallax)',
                    '--section-bg-overlay' => 'Dark overlay opacity (0–1)',
                    '--section-padding-outer' => 'Vertical padding of the section',
                ],
                'customCssSelectors' => [
                    '.section[data-block-type="feature_list"]' => 'Target all feature_list blocks',
                    '.section[data-block-variant="trust_band"]' => 'Target a specific variant',
                    '.section[data-section-intent="highlight"]' => 'Target all highlight sections',
                    '.section[data-block-id="my-block"]' => 'Target a block by its ID',
                    '.section__inner' => 'Inner content wrapper',
                    '.section__inner--panel' => 'Panel-style inner wrapper',
                    '.section__inner--card' => 'Card-style inner wrapper',
                ],
            ],
            'intentPresets' => [
                'content' => [
                    'description' => 'Standard white/transparent section for prose and structured content',
                    'surface' => '--surface (white/transparent)',
                    'textColor' => 'dark',
                    'padding' => 'normal',
                    'containerWidth' => 'normal (1200px)',
                    'useFor' => ['rich_text', 'process_steps', 'info_media with detailed content'],
                ],
                'plain' => [
                    'description' => 'Minimal section with no visual weight, transparent background',
                    'surface' => '--surface (white/transparent)',
                    'textColor' => 'dark',
                    'padding' => 'normal',
                    'containerWidth' => 'normal (1200px)',
                    'useFor' => ['faq', 'trust_bar', 'trust_band', 'minimally styled sections'],
                ],
                'feature' => [
                    'description' => 'Light grey background section for feature showcases',
                    'surface' => '--surface-muted (light grey)',
                    'textColor' => 'dark',
                    'padding' => 'generous',
                    'containerWidth' => 'wide',
                    'useFor' => ['feature_list', 'testimonial', 'info_media', 'package_summary'],
                ],
                'highlight' => [
                    'description' => 'Brand-coloured dark section for emphasis and calls to action',
                    'surface' => '--accent-primary (brand primary colour)',
                    'textColor' => 'white',
                    'padding' => 'generous',
                    'containerWidth' => 'wide',
                    'useFor' => ['stat_strip', 'cta', 'proof', 'key metrics'],
                ],
                'hero' => [
                    'description' => 'Full-width dark section for page heroes with maximum visual impact',
                    'surface' => '--accent-secondary (dark brand blend)',
                    'textColor' => 'white',
                    'padding' => 'extra-large',
                    'containerWidth' => 'full-width',
                    'useFor' => ['hero blocks exclusively'],
                ],
            ],
            'defaultIntentByBlockType' => [
                'hero' => 'hero',
                'feature_list' => 'feature',
                'info_media' => 'feature',
                'audience_spotlight' => 'feature',
                'testimonial' => 'feature',
                'package_summary' => 'feature',
                'contact_form' => 'feature',
                'content_slider' => 'feature',
                'stat_strip' => 'highlight',
                'proof' => 'highlight',
                'cta' => 'highlight',
                'process_steps' => 'content',
                'rich_text' => 'content',
                'faq' => 'plain',
                'latest_news' => 'feature',
            ],
            'backgroundColorTokens' => [
                'surface' => [
                    'visual' => 'white / transparent',
                    'darkToken' => false,
                    'textColor' => 'dark',
                    'description' => 'Standard surface, no visual emphasis',
                ],
                'muted' => [
                    'visual' => 'light grey',
                    'darkToken' => false,
                    'textColor' => 'dark',
                    'description' => 'Subtle background distinction from white sections',
                ],
                'primary' => [
                    'visual' => 'brand primary colour',
                    'darkToken' => true,
                    'textColor' => 'white (automatic)',
                    'description' => 'Strong brand emphasis, automatically switches text to white',
                ],
                'secondary' => [
                    'visual' => 'dark brand blend',
                    'darkToken' => true,
                    'textColor' => 'white (automatic)',
                    'description' => 'Dark atmospheric background, used for heroes',
                ],
                'accent' => [
                    'visual' => 'accent colour',
                    'darkToken' => true,
                    'textColor' => 'white (automatic)',
                    'description' => 'Accent emphasis, automatically switches text to white',
                ],
            ],
            'layoutModes' => [
                'normal' => 'Standard contained layout with max-width container',
                'full' => 'Full-bleed background extending to viewport edges, content still contained',
                'card' => 'Content wrapped in a card panel with rounded corners and shadow',
                'full-card' => 'Full-bleed background with card panel for inner content',
            ],
            'containerOptions' => [
                'width' => [
                    'normal' => 'Standard width (max 1200px)',
                    'wide' => 'Wide container (uk-container-large)',
                    'full' => 'Full viewport width (uk-container-expand)',
                ],
                'frame' => [
                    'none' => 'No frame around content',
                    'card' => 'Card panel wrapping inner content',
                ],
                'spacing' => [
                    'compact' => 'Reduced vertical padding',
                    'normal' => 'Standard vertical padding',
                    'generous' => 'Large vertical padding for spacious sections',
                ],
            ],
            'viewportHeight' => [
                'auto' => 'Normal document flow (default)',
                'full' => 'Section fills the entire viewport height',
                'reduced' => 'Section has a minimum height of 80vh',
                'minus-next' => 'Viewport height minus the height of the next section',
            ],
            'variantBehaviour' => [
                'feature_list' => [
                    'detailed-cards' => '3-column card grid with icon + title + description',
                    'grid-bullets' => '3-column grid with compact items and optional bullets',
                    'icon_grid' => 'Alias for grid-bullets — identical output',
                    'slider' => 'Horizontal card carousel (swipeable)',
                    'text-columns' => 'Multi-column text layout without icons',
                    'card-stack' => 'Vertically stacked card layout',
                    'clustered-tabs' => 'Items grouped into switchable tabs',
                    '_note' => 'detailed-cards, grid-bullets, and icon_grid all render as a 3-column grid. '
                        . 'For genuine layout variety use slider, text-columns, or clustered-tabs.',
                ],
                'info_media' => [
                    'stacked' => 'Single column — media stacked above text',
                    'image-left' => 'Two columns — image left, text and items right',
                    'image-right' => 'Two columns — text and items left, image right',
                    'switcher' => 'Multiple items displayed as switchable tabs with media',
                    '_note' => 'image-left and image-right produce genuine 2-column layouts. '
                        . 'Use these to break the 3-column grid monotony of feature_list.',
                ],
                'stat_strip' => [
                    'inline' => 'Horizontal row of large metric values with labels',
                    'cards' => 'Each metric displayed in its own card',
                    'centered' => 'Centred metric values with labels below',
                    'highlight' => 'Accent-background metrics designed for dark sections',
                    'trust_bar' => 'Compact inline list of icon + label pairs separated by dividers',
                    'trust_band' => 'Slightly larger icon + label list than trust_bar',
                    '_note' => 'trust_bar and trust_band use data.items[].icon. '
                        . 'All other variants use data.metrics[].icon.',
                ],
                'hero' => [
                    'centered_cta' => 'Centred headline, subheadline, and CTA buttons',
                    'media_right' => 'Two columns — text left, image right',
                    'media_left' => 'Two columns — image left, text right',
                    'media_video' => 'Text with embedded video player',
                    'minimal' => 'Text only, no media element',
                    'stat_tiles' => 'Text left with stat tiles on the right',
                    'small' => 'Compact hero with reduced padding',
                ],
                'audience_spotlight' => [
                    'tabs' => 'Tabbed case views (known issue: may render as accordion)',
                    'tiles' => 'Tile grid of cases (known issue: may render as accordion)',
                    'single-focus' => 'Single case prominently displayed',
                    '_note' => 'tabs and tiles variants have a known rendering bug. '
                        . 'Consider using feature_list or info_media as alternatives.',
                ],
                'process_steps' => [
                    'numbered-vertical' => 'Vertical numbered step list',
                    'numbered-horizontal' => 'Horizontal numbered step row',
                    'timeline' => 'Timeline with visual connectors',
                    'timeline_horizontal' => 'Horizontal timeline variant',
                    'timeline_vertical' => 'Vertical timeline variant',
                ],
                'testimonial' => [
                    'single_quote' => 'Single prominent quote with author attribution',
                    'quote_wall' => 'Grid of 2–3 quotes side by side',
                ],
                'cta' => [
                    'full_width' => 'Full-width call-to-action bar',
                    'split' => 'Two CTA buttons displayed side by side',
                ],
                'faq' => [
                    'accordion' => 'Collapsible question/answer sections',
                ],
                'package_summary' => [
                    'toggle' => 'Uses data.options[] with expandable highlights',
                    'comparison-cards' => 'Uses data.plans[] displayed as comparison cards',
                ],
            ],
            'iconCatalog' => [
                'general' => ['check', 'close', 'plus', 'minus', 'star', 'heart', 'bolt', 'bell', 'bookmark', 'tag', 'ban', 'info', 'question', 'warning', 'settings', 'cog', 'search', 'home', 'grid', 'list', 'hashtag', 'happy', 'clock', 'calendar', 'history', 'future'],
                'media' => ['image', 'camera', 'play', 'video-camera', 'microphone', 'tv', 'album', 'thumbnails', 'file', 'file-text', 'file-pdf', 'file-edit', 'folder', 'copy', 'code', 'print'],
                'navigation' => ['arrow-up', 'arrow-down', 'arrow-left', 'arrow-right', 'arrow-up-right', 'chevron-up', 'chevron-down', 'chevron-left', 'chevron-right', 'chevron-double-left', 'chevron-double-right', 'expand', 'shrink', 'move', 'forward', 'reply', 'refresh'],
                'communication' => ['mail', 'comment', 'commenting', 'comments', 'receiver', 'phone', 'social', 'users', 'user', 'location', 'world', 'link', 'link-external', 'rss'],
                'devices' => ['desktop', 'laptop', 'tablet', 'server', 'database', 'cloud-upload', 'cloud-download', 'download', 'upload'],
                'security' => ['lock', 'unlock', 'key', 'shield', 'fingerprint', 'eye', 'eye-slash', 'sign-in', 'sign-out', 'credit-card'],
                'editing' => ['pencil', 'paint-bucket', 'bold', 'italic', 'strikethrough', 'quote-right', 'nut', 'crosshairs', 'trash', 'bag', 'cart', 'lifesaver'],
                'brands' => ['github', 'google', 'facebook', 'instagram', 'x', 'linkedin', 'youtube', 'tiktok', 'discord', 'whatsapp', 'telegram', 'signal', 'bluesky', 'mastodon', 'reddit', 'pinterest'],
                'custom' => ['sun', 'moon', 'handbook'],
                '_note' => 'Only these exact icon names are valid. UIkit silently renders nothing for unknown names. '
                    . 'Names like "shield-check" or "badge-check" do NOT exist.',
            ],
            'sectionAppearanceLegacy' => [
                'description' => 'sectionAppearance is a legacy shorthand. It only affects layout, NOT background or intent. '
                    . 'Use meta.sectionStyle with explicit intent and background.colorToken instead.',
                'aliases' => [
                    'default' => 'contained (layout: normal)',
                    'surface' => 'contained (layout: normal) — identical to default',
                    'contrast' => 'full (layout: full) — does NOT set a dark background',
                    'card' => 'card (layout: card)',
                    'image' => 'full (layout: full) with image support',
                    'image-fixed' => 'full (layout: full) with fixed attachment',
                ],
            ],
            'recipes' => [
                'lightContent' => [
                    'description' => 'White background, dark text, standard width',
                    'sectionStyle' => ['layout' => 'normal', 'intent' => 'content'],
                ],
                'mutedFeature' => [
                    'description' => 'Light grey background for feature showcases',
                    'sectionStyle' => ['layout' => 'normal', 'intent' => 'feature'],
                ],
                'darkHighlight' => [
                    'description' => 'Brand-coloured dark section with white text',
                    'sectionStyle' => [
                        'layout' => 'normal',
                        'intent' => 'highlight',
                        'background' => ['mode' => 'color', 'colorToken' => 'primary'],
                    ],
                ],
                'heroSection' => [
                    'description' => 'Full-width dark hero with maximum impact',
                    'sectionStyle' => [
                        'layout' => 'full',
                        'intent' => 'hero',
                        'background' => ['mode' => 'color', 'colorToken' => 'secondary'],
                    ],
                ],
                'minimalBar' => [
                    'description' => 'No visual weight — ideal for trust bars',
                    'sectionStyle' => ['layout' => 'normal', 'intent' => 'plain'],
                ],
                'backgroundImage' => [
                    'description' => 'Full-bleed parallax background image with overlay',
                    'sectionStyle' => [
                        'layout' => 'full',
                        'background' => [
                            'mode' => 'image',
                            'imageId' => '/uploads/example.jpg',
                            'attachment' => 'fixed',
                            'overlay' => 0.4,
                        ],
                    ],
                ],
            ],
            'pageFlowGuidelines' => [
                'Alternate section intents — never place two feature (muted) sections back to back.',
                'Use info_media (image-left / image-right) to break 3-column grid monotony.',
                'Reserve highlight intent for sections that need emphasis (stats, CTAs, testimonials).',
                'Start with hero (dark), end with cta (dark), keep middle sections varied.',
                'Typical flow: hero → plain → feature → content → feature → content → highlight → plain → highlight.',
            ],
            'knownLimitations' => [
                ['id' => 5, 'summary' => 'audience_spotlight tabs/tiles render as accordion', 'workaround' => 'Use feature_list or info_media instead'],
                ['id' => 6, 'summary' => 'German compound words break in 3-column grids', 'workaround' => 'Use shorter titles or 2-column layouts (info_media)'],
                ['id' => 7, 'summary' => 'detailed-cards, icon_grid, grid-bullets render identically', 'workaround' => 'Use slider, text-columns, or clustered-tabs for layout variety'],
                ['id' => 8, 'summary' => 'sectionAppearance default/surface/contrast produce no visual difference', 'workaround' => 'Use meta.sectionStyle with explicit intent and background'],
            ],
        ];
    }

    /**
     * Validate a page design for consistency.
     *
     * Checks block tokens against valid values, verifies section intents and
     * appearances, and flags deprecated block types.
     *
     * @return array{valid: bool,
     *     errors: list<array{block: string, field: string, message: string}>,
     *     warnings: list<array{block: string, field: string, message: string}>
     * }
     */
    public function validatePageDesign(string $namespace, string $pageContent): array
    {
        $errors = [];
        $warnings = [];

        $blocks = json_decode($pageContent, true);
        if (!is_array($blocks)) {
            $wrapped = json_decode($pageContent, true);
            $blocks = is_array($wrapped) && isset($wrapped['blocks']) ? $wrapped['blocks'] : [];
        }

        if (isset($blocks['blocks']) && is_array($blocks['blocks'])) {
            $blocks = $blocks['blocks'];
        }

        $validBackgrounds = ['primary', 'secondary', 'muted', 'accent', 'surface'];
        $validSpacings = ['small', 'normal', 'large'];
        $validWidths = ['narrow', 'normal', 'wide'];
        $validColumns = ['single', 'two', 'three', 'four'];
        $validAccents = ['brandA', 'brandB', 'brandC'];
        $validAppearances = ['contained', 'full', 'card', 'default', 'surface', 'contrast', 'image', 'image-fixed'];
        $deprecatedTypes = ['system_module', 'case_showcase'];

        foreach ($blocks as $index => $block) {
            if (!is_array($block)) {
                continue;
            }

            $blockId = $block['id'] ?? $block['type'] ?? "block[{$index}]";
            $blockType = $block['type'] ?? null;

            if ($blockType === null) {
                $errors[] = ['block' => (string) $blockId, 'field' => 'type', 'message' => 'Block type is missing'];
                continue;
            }

            if (in_array($blockType, $deprecatedTypes, true)) {
                $warnings[] = [
                    'block' => (string) $blockId,
                    'field' => 'type',
                    'message' => "Block type '{$blockType}' is deprecated",
                ];
            }

            if (!isset($block['variant']) || !is_string($block['variant'])) {
                $errors[] = [
                    'block' => (string) $blockId,
                    'field' => 'variant',
                    'message' => 'Block variant is missing or not a string',
                ];
            }

            $tokens = $block['tokens'] ?? null;
            if (is_array($tokens)) {
                $this->validateTokenField($tokens, 'background', $validBackgrounds, (string) $blockId, $errors);
                $this->validateTokenField($tokens, 'spacing', $validSpacings, (string) $blockId, $errors);
                $this->validateTokenField($tokens, 'width', $validWidths, (string) $blockId, $errors);
                $this->validateTokenField($tokens, 'columns', $validColumns, (string) $blockId, $errors);
                $this->validateTokenField($tokens, 'accent', $validAccents, (string) $blockId, $errors);
            }

            $appearance = $block['sectionAppearance'] ?? null;
            if ($appearance !== null && !in_array($appearance, $validAppearances, true)) {
                $errors[] = [
                    'block' => (string) $blockId,
                    'field' => 'sectionAppearance',
                    'message' => "Invalid sectionAppearance '{$appearance}'. Valid: "
                        . implode(', ', $validAppearances),
                ];
            }
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param array<string, mixed> $tokens
     * @param list<string> $validValues
     * @param list<array{block: string, field: string, message: string}> &$errors
     */
    private function validateTokenField(
        array $tokens,
        string $field,
        array $validValues,
        string $blockId,
        array &$errors
    ): void {
        if (!isset($tokens[$field])) {
            return;
        }
        $value = $tokens[$field];
        if (!is_string($value) || !in_array($value, $validValues, true)) {
            $errors[] = [
                'block' => $blockId,
                'field' => "tokens.{$field}",
                'message' => "Invalid value '{$value}'. Valid: " . implode(', ', $validValues),
            ];
        }
    }
}
