<?php

/**
 * Offline namespace token CSS rebuilder.
 *
 * Regenerates all per-namespace and global namespace-tokens.css files
 * without requiring a database connection. Uses:
 * - content/design/*.json for file-based namespace tokens
 * - Existing per-namespace CSS files for namespaces without JSON
 * - ColorContrastService for WCAG-safe contrast token computation
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Service\ColorContrastService;

$contrastService = new ColorContrastService();
$projectRoot = dirname(__DIR__);
$cssBase = $projectRoot . '/public/css';

$defaultTokens = [
    'brand' => ['primary' => '#1e87f0', 'accent' => '#f97316', 'secondary' => '#f97316'],
    'layout' => ['profile' => 'standard'],
    'typography' => ['preset' => 'modern'],
    'components' => ['cardStyle' => 'rounded', 'buttonStyle' => 'filled'],
];

// ---------- Collect all namespace tokens ----------

$namespaces = [];

// 1) Read from content/design/*.json
foreach (glob($projectRoot . '/content/design/*.json') as $file) {
    $name = strtolower(pathinfo($file, PATHINFO_FILENAME));
    $data = json_decode(file_get_contents($file), true) ?: [];
    $tokens = $data['designTokens'] ?? $data['tokens'] ?? [];
    if ($tokens === []) {
        continue;
    }
    $namespaces[$name] = mergeTokens($defaultTokens, validateTokens($tokens));
}

// 2) Read from existing per-namespace CSS files (for DB-sourced namespaces without JSON)
foreach (glob($cssBase . '/*/namespace-tokens.css') as $cssFile) {
    $name = basename(dirname($cssFile));
    if (isset($namespaces[$name]) || $name === 'default') {
        continue;
    }
    $parsed = parseExistingCss($cssFile);
    if ($parsed !== null) {
        $namespaces[$name] = mergeTokens($defaultTokens, $parsed);
    }
}

// Ensure default is present
if (!isset($namespaces['default'])) {
    $namespaces['default'] = $defaultTokens;
}

ksort($namespaces);

// ---------- Build global namespace-tokens.css ----------

$blocks = [];
$blocks[] = "/**\n * Auto-generated. Do not edit manually.\n */";

$defaultMerged = mergeTokens($defaultTokens, $namespaces['default']);
$blocks[] = renderTokenCssBlock(':root', $defaultMerged, $contrastService);
$blocks[] = renderTokenCssBlock('html[data-namespace="default"]', $defaultMerged, $contrastService);
$blocks[] = renderDarkTokenCssBlock(
    'html[data-namespace="default"][data-theme="dark"]',
    $defaultMerged,
    $contrastService,
);

foreach ($namespaces as $ns => $tokens) {
    if ($ns === 'default') {
        continue;
    }
    $merged = mergeTokens($defaultMerged, mergeTokens($defaultTokens, $tokens));
    $blocks[] = renderTokenCssBlock('html[data-namespace="' . $ns . '"]', $merged, $contrastService);
    $blocks[] = renderDarkTokenCssBlock(
        'html[data-namespace="' . $ns . '"][data-theme="dark"]',
        $merged,
        $contrastService,
    );
}

$blocks[] = renderBaseTokenBlock(':root');

$globalCss = implode("\n\n", $blocks) . "\n";
file_put_contents($cssBase . '/namespace-tokens.css', $globalCss);
echo "  Written: /public/css/namespace-tokens.css\n";

// ---------- Build per-namespace CSS files ----------

foreach ($namespaces as $ns => $tokens) {
    if ($ns === 'default') {
        continue;
    }
    $dir = $cssBase . '/' . $ns;
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    $selector = 'html[data-namespace="' . $ns . '"]';
    $darkSelector = 'html[data-namespace="' . $ns . '"][data-theme="dark"]';
    $merged = mergeTokens($defaultMerged, mergeTokens($defaultTokens, $tokens));
    $perNsCss = implode("\n\n", [
        "/**\n * Auto-generated. Do not edit manually.\n */",
        renderTokenCssBlock($selector, $merged, $contrastService, getBaseTokenLines()),
        renderDarkTokenCssBlock($darkSelector, $merged, $contrastService),
    ]) . "\n";
    file_put_contents($dir . '/namespace-tokens.css', $perNsCss);
    echo "  Written: /public/css/{$ns}/namespace-tokens.css\n";
}

echo "Done.\n";

// ---------- Helper functions ----------

function validateTokens(array $tokens): array
{
    $validated = ['brand' => [], 'layout' => [], 'typography' => [], 'components' => []];

    $brand = $tokens['brand'] ?? [];
    if (is_array($brand)) {
        foreach (['primary', 'accent', 'secondary'] as $key) {
            $color = normalizeColor($brand[$key] ?? null);
            if ($color !== null) {
                $validated['brand'][$key] = $color;
            }
        }
    }

    $layout = $tokens['layout'] ?? [];
    if (is_array($layout)) {
        $profile = normalizeChoice($layout['profile'] ?? null, ['narrow', 'standard', 'wide']);
        if ($profile !== null) {
            $validated['layout']['profile'] = $profile;
        }
    }

    $typography = $tokens['typography'] ?? [];
    if (is_array($typography)) {
        $preset = normalizeChoice($typography['preset'] ?? null, ['modern', 'classic', 'tech']);
        if ($preset !== null) {
            $validated['typography']['preset'] = $preset;
        }
    }

    $components = $tokens['components'] ?? [];
    if (is_array($components)) {
        $cardStyle = normalizeChoice($components['cardStyle'] ?? null, ['rounded', 'square', 'pill']);
        $buttonStyle = normalizeChoice($components['buttonStyle'] ?? null, ['filled', 'outline', 'ghost']);
        if ($cardStyle !== null) {
            $validated['components']['cardStyle'] = $cardStyle;
        }
        if ($buttonStyle !== null) {
            $validated['components']['buttonStyle'] = $buttonStyle;
        }
    }

    return $validated;
}

function mergeTokens(array $base, array $overrides): array
{
    $merged = $base;
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

function normalizeColor(mixed $value): ?string
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
    return preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i', $normalized) === 1 ? $normalized : null;
}

function normalizeChoice(mixed $value, array $choices): ?string
{
    if (!is_string($value)) {
        return null;
    }
    $normalized = strtolower(trim($value));
    return in_array($normalized, $choices, true) ? $normalized : null;
}

function parseExistingCss(string $path): ?array
{
    $content = file_get_contents($path);
    if ($content === false) {
        return null;
    }
    $tokens = ['brand' => [], 'layout' => [], 'typography' => [], 'components' => []];

    if (preg_match('/--brand-primary:\s*(#[0-9a-fA-F]{3,6})/', $content, $m)) {
        $tokens['brand']['primary'] = $m[1];
    }
    if (preg_match('/--brand-accent:\s*(#[0-9a-fA-F]{3,6})/', $content, $m)) {
        $tokens['brand']['accent'] = $m[1];
    }
    if (preg_match('/--brand-secondary:\s*(#[0-9a-fA-F]{3,6})/', $content, $m)) {
        $tokens['brand']['secondary'] = $m[1];
    }
    if (preg_match('/--layout-profile:\s*(\w+)/', $content, $m)) {
        $tokens['layout']['profile'] = $m[1];
    }
    if (preg_match('/--typography-preset:\s*(\w+)/', $content, $m)) {
        $tokens['typography']['preset'] = $m[1];
    }
    if (preg_match('/--components-card-style:\s*(\w+)/', $content, $m)) {
        $tokens['components']['cardStyle'] = $m[1];
    }
    if (preg_match('/--components-button-style:\s*(\w+)/', $content, $m)) {
        $tokens['components']['buttonStyle'] = $m[1];
    }

    return $tokens;
}

function renderTokenCssBlock(
    string $selector,
    array $tokens,
    ColorContrastService $cs,
    array $extraLines = []
): string {
    $primary = $tokens['brand']['primary'] ?? '#1e87f0';
    $accent = $tokens['brand']['accent'] ?? '#f97316';
    $secondary = $tokens['brand']['secondary'] ?? '#f97316';

    $contrast = $cs->resolveContrastTokens([
        'primary' => $primary,
        'secondary' => $secondary,
        'accent' => $accent,
    ]);

    $lines = [
        $selector . ' {',
        '  --brand-primary: ' . $primary . ';',
        '  --brand-accent: ' . $accent . ';',
        '  --brand-secondary: ' . $secondary . ';',
        '  --contrast-text-on-primary: ' . ($contrast['textOnPrimary'] ?? '#ffffff') . ';',
        '  --contrast-text-on-secondary: ' . ($contrast['textOnSecondary'] ?? '#ffffff') . ';',
        '  --contrast-text-on-accent: ' . ($contrast['textOnAccent'] ?? '#ffffff') . ';',
        '  --contrast-text-on-surface: ' . ($contrast['textOnSurface'] ?? '#000000') . ';',
        '  --contrast-text-on-surface-muted: ' . ($contrast['textOnSurfaceMuted'] ?? '#000000') . ';',
        '  --contrast-text-on-page: ' . ($contrast['textOnPage'] ?? '#000000') . ';',
        '  --marketing-primary: ' . $primary . ';',
        '  --marketing-accent: ' . $accent . ';',
        '  --marketing-secondary: ' . $secondary . ';',
        '  --marketing-link: ' . $primary . ';',
        '  --marketing-surface: var(--surface-card, #ffffff);',
        '  --marketing-white: #ffffff;',
        '  --marketing-black: #000000;',
        '  --marketing-black-rgb: 0 0 0;',
        '  --marketing-ink: #0f172a;',
        '  --layout-profile: ' . ($tokens['layout']['profile'] ?? 'standard') . ';',
        '  --typography-preset: ' . ($tokens['typography']['preset'] ?? 'modern') . ';',
        '  --components-card-style: ' . ($tokens['components']['cardStyle'] ?? 'rounded') . ';',
        '  --components-button-style: ' . ($tokens['components']['buttonStyle'] ?? 'filled') . ';',
    ];
    if ($extraLines !== []) {
        $lines = array_merge($lines, $extraLines);
    }
    $lines[] = '}';
    return implode("\n", $lines);
}

function getBaseTokenLines(): array
{
    return [
        '  --brand-surface: #0f172a;',
        '  --brand-on-surface: #ffffff;',
        '  --section-gap: 2.25rem;',
        '  --card-radius: 10px;',
        '  --font-heading-weight: 700;',
    ];
}

function renderDarkTokenCssBlock(
    string $selector,
    array $tokens,
    ColorContrastService $cs,
    array $extraLines = []
): string {
    $primary = $tokens['brand']['primary'] ?? '#1e87f0';
    $accent = $tokens['brand']['accent'] ?? '#f97316';
    $secondary = $tokens['brand']['secondary'] ?? '#f97316';

    $contrast = $cs->resolveContrastTokens([
        'primary' => $primary,
        'secondary' => $secondary,
        'accent' => $accent,
        'surface' => '#1a2636',
        'surfaceMuted' => '#0b111a',
        'surfacePage' => '#0a0d12',
    ]);

    $lines = [
        $selector . ' {',
        '  --brand-primary: ' . $primary . ';',
        '  --brand-accent: ' . $accent . ';',
        '  --brand-secondary: ' . $secondary . ';',
        '  --contrast-text-on-primary: ' . ($contrast['textOnPrimary'] ?? '#ffffff') . ';',
        '  --contrast-text-on-secondary: ' . ($contrast['textOnSecondary'] ?? '#ffffff') . ';',
        '  --contrast-text-on-accent: ' . ($contrast['textOnAccent'] ?? '#ffffff') . ';',
        '  --contrast-text-on-surface: ' . ($contrast['textOnSurface'] ?? '#f2f5fa') . ';',
        '  --contrast-text-on-surface-muted: ' . ($contrast['textOnSurfaceMuted'] ?? '#f2f5fa') . ';',
        '  --contrast-text-on-page: ' . ($contrast['textOnPage'] ?? '#f2f5fa') . ';',
        '  --marketing-primary: ' . $primary . ';',
        '  --marketing-accent: ' . $accent . ';',
        '  --marketing-secondary: ' . $secondary . ';',
        '  --marketing-link: ' . $primary . ';',
        '  --marketing-surface: var(--surface-card, #1a2636);',
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

function renderBaseTokenBlock(string $selector): string
{
    return implode("\n", array_merge([$selector . ' {'], getBaseTokenLines(), ['}']));
}
