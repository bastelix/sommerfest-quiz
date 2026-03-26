<?php

declare(strict_types=1);

namespace App\Service\Mcp;

use App\Service\DesignTokenService;
use App\Service\PageService;
use PDO;

final class StylesheetTools
{
    use McpToolTrait;

    private DesignTokenService $designTokens;

    private PageService $pageService;

    private const NS_PROP = [
        'type' => 'string',
        'description' => 'Optional namespace (defaults to the token namespace)',
    ];

    public function __construct(PDO $pdo, private readonly string $defaultNamespace)
    {
        $this->designTokens = new DesignTokenService($pdo);
        $this->pageService = new PageService($pdo);
    }

    /**
     * @return list<array{name: string, method: string, description: string, inputSchema: array}>
     */
    public function definitions(): array
    {
        return [
            [
                'name' => 'get_design_tokens',
                'method' => 'getDesignTokens',
                'description' => 'Get the current design tokens for a namespace. Returns '
                    . 'brand colors, layout profile, typography preset, and '
                    . 'component styles.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => self::NS_PROP,
                    ],
                ],
            ],
            [
                'name' => 'update_design_tokens',
                'method' => 'updateDesignTokens',
                'description' => 'Update design tokens for a namespace. Accepts partial '
                    . 'updates — only provided fields are changed. Triggers a CSS '
                    . 'rebuild. Call get_design_schema first to learn valid values.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => self::NS_PROP,
                        'tokens' => [
                            'type' => 'object',
                            'description' => 'Design token groups to update (partial updates allowed)',
                            'properties' => [
                                'brand' => [
                                    'type' => 'object',
                                    'description' => 'Brand colors as hex values (#RGB or #RRGGBB)',
                                    'properties' => [
                                        'primary' => ['type' => 'string', 'description' => 'Primary brand color (hex)'],
                                        'accent' => ['type' => 'string', 'description' => 'Accent color (hex)'],
                                        'secondary' => ['type' => 'string', 'description' => 'Secondary color (hex)'],
                                    ],
                                ],
                                'layout' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'profile' => ['type' => 'string', 'enum' => ['narrow', 'standard', 'wide']],
                                    ],
                                ],
                                'typography' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'preset' => ['type' => 'string', 'enum' => ['modern', 'classic', 'tech']],
                                    ],
                                ],
                                'components' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'cardStyle' => ['type' => 'string', 'enum' => ['rounded', 'square', 'pill']],
                                        'buttonStyle' => ['type' => 'string', 'enum' => ['filled', 'outline', 'ghost']],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'required' => ['tokens'],
                ],
            ],
            [
                'name' => 'get_custom_css',
                'method' => 'getCustomCss',
                'description' => 'Get the custom CSS overrides for a namespace. Returns '
                    . 'the raw CSS string that is applied on top of design tokens.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => self::NS_PROP,
                    ],
                ],
            ],
            [
                'name' => 'update_custom_css',
                'method' => 'updateCustomCss',
                'description' => 'Set custom CSS overrides for a namespace. The CSS is '
                    . 'sanitized and auto-scoped to the namespace. Triggers a CSS '
                    . 'rebuild. Use CSS custom properties (e.g. --brand-primary) to '
                    . 'reference design tokens.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => self::NS_PROP,
                        'css' => ['type' => 'string', 'description' => 'CSS string to apply as custom overrides'],
                    ],
                    'required' => ['css'],
                ],
            ],
            [
                'name' => 'list_design_presets',
                'method' => 'listDesignPresets',
                'description' => 'List all available design presets that can be imported into a namespace.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
            ],
            [
                'name' => 'import_design_preset',
                'method' => 'importDesignPreset',
                'description' => 'Import a design preset into a namespace. Replaces the '
                    . 'current design tokens, colors, and effects with the preset '
                    . 'values. Call list_design_presets first to see available presets.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => self::NS_PROP,
                        'preset' => ['type' => 'string', 'description' => 'Name of the design preset to import'],
                    ],
                    'required' => ['preset'],
                ],
            ],
            [
                'name' => 'reset_design',
                'method' => 'resetDesign',
                'description' => 'Reset the design tokens for a namespace to the default '
                    . 'values. Triggers a CSS rebuild.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => self::NS_PROP,
                    ],
                ],
            ],
            [
                'name' => 'get_design_schema',
                'method' => 'getDesignSchema',
                'description' => 'Get the design token schema with all valid options, '
                    . 'default values, and available CSS custom properties. Use this '
                    . 'before updating tokens to understand what values are accepted.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
            ],
            [
                'name' => 'get_design_manifest',
                'method' => 'getDesignManifest',
                'description' => 'Get the complete design token manifest for a namespace. '
                    . 'Returns all CSS custom properties with their resolved values, '
                    . 'the full token hierarchy (semantic -> namespace -> component), '
                    . 'block-level token options, section intents/appearances, and '
                    . 'legacy alias mappings. Use this to understand and validate the '
                    . 'full design state before making changes.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => self::NS_PROP,
                    ],
                ],
            ],
            [
                'name' => 'validate_page_design',
                'method' => 'validatePageDesign',
                'description' => 'Validate a page design for consistency. Checks block '
                    . 'tokens against valid enum values, verifies section appearances, '
                    . 'and flags deprecated block types (system_module, case_showcase). '
                    . 'Returns errors (invalid values) and warnings (deprecated types).',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => self::NS_PROP,
                        'slug' => ['type' => 'string', 'description' => 'Page slug to validate'],
                    ],
                    'required' => ['slug'],
                ],
            ],
        ];
    }

    public function getDesignTokens(array $args): array
    {
        $ns = $this->resolveNamespace($args);
        $tokens = $this->designTokens->getTokensForNamespace($ns);
        $importMeta = $this->designTokens->getImportMeta($ns);

        return [
            'namespace' => $ns,
            'tokens' => $tokens,
            'importMeta' => $importMeta,
        ];
    }

    public function updateDesignTokens(array $args): array
    {
        $ns = $this->resolveNamespace($args);

        $tokens = $args['tokens'] ?? null;
        if (!is_array($tokens)) {
            throw new \InvalidArgumentException('tokens must be an object');
        }

        $current = $this->designTokens->getTokensForNamespace($ns);
        foreach ($tokens as $group => $values) {
            if (!is_array($values) || !array_key_exists($group, $current)) {
                continue;
            }
            foreach ($values as $key => $value) {
                if ($value !== null && $value !== '') {
                    $current[$group][$key] = $value;
                }
            }
        }

        $persisted = $this->designTokens->persistTokens($ns, $current);

        return [
            'namespace' => $ns,
            'tokens' => $persisted,
        ];
    }

    public function getCustomCss(array $args): array
    {
        $ns = $this->resolveNamespace($args);
        $css = $this->designTokens->getCustomCssForNamespace($ns);

        return [
            'namespace' => $ns,
            'css' => $css,
        ];
    }

    public function updateCustomCss(array $args): array
    {
        $ns = $this->resolveNamespace($args);

        $css = isset($args['css']) && is_string($args['css']) ? $args['css'] : '';
        $this->designTokens->persistCustomCss($ns, $css);

        return [
            'namespace' => $ns,
            'status' => 'ok',
        ];
    }

    public function listDesignPresets(array $args): array
    {
        $presets = $this->designTokens->listAvailablePresets();

        return ['presets' => $presets];
    }

    public function importDesignPreset(array $args): array
    {
        $ns = $this->resolveNamespace($args);

        $preset = isset($args['preset']) && is_string($args['preset']) ? trim($args['preset']) : '';
        if ($preset === '') {
            throw new \InvalidArgumentException('preset is required');
        }

        $result = $this->designTokens->importDesign($ns, $preset);

        return [
            'namespace' => $ns,
            'tokens' => $result['tokens'],
            'colors' => $result['colors'],
            'effects' => $result['effects'],
        ];
    }

    public function resetDesign(array $args): array
    {
        $ns = $this->resolveNamespace($args);
        $tokens = $this->designTokens->resetToDefaults($ns);

        return [
            'namespace' => $ns,
            'tokens' => $tokens,
        ];
    }

    public function getDesignSchema(array $args): array
    {
        return [
            'defaults' => $this->designTokens->getDefaults(),
            'options' => [
                'layoutProfiles' => $this->designTokens->getLayoutProfiles(),
                'typographyPresets' => $this->designTokens->getTypographyPresets(),
                'cardStyles' => $this->designTokens->getCardStyles(),
                'buttonStyles' => $this->designTokens->getButtonStyles(),
            ],
            'brandColors' => [
                'description' => 'Hex color values (#RGB or #RRGGBB)',
                'fields' => ['primary', 'accent', 'secondary'],
            ],
            'cssVariables' => [
                '--brand-primary',
                '--brand-accent',
                '--brand-secondary',
                '--surface-page',
                '--surface-section',
                '--surface-card',
                '--surface-muted',
                '--surface-subtle',
                '--text-body',
                '--text-heading',
                '--text-muted',
                '--text-on-primary',
                '--text-on-secondary',
                '--text-on-accent',
                '--border-muted',
                '--border-strong',
                '--space-section',
                '--card-radius',
                '--layout-profile',
                '--typography-preset',
                '--components-card-style',
                '--components-button-style',
            ],
        ];
    }

    public function getDesignManifest(array $args): array
    {
        $ns = $this->resolveNamespace($args);

        return $this->designTokens->getDesignManifest($ns);
    }

    public function validatePageDesign(array $args): array
    {
        $ns = $this->resolveNamespace($args);

        $slug = isset($args['slug']) && is_string($args['slug']) ? trim($args['slug']) : '';
        if ($slug === '') {
            throw new \InvalidArgumentException('slug is required');
        }

        $content = $this->pageService->getByKey($ns, $slug);
        if ($content === null) {
            throw new \InvalidArgumentException("Page '{$slug}' not found in namespace '{$ns}'");
        }

        return $this->designTokens->validatePageDesign($ns, $content);
    }
}
