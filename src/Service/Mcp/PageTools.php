<?php

declare(strict_types=1);

namespace App\Service\Mcp;

use App\Application\Seo\PageSeoConfigService;
use App\Domain\PageSeoConfig;
use App\Service\BlockContractSchemaValidator;
use App\Service\PageBlockContractMigrator;
use App\Service\PageService;
use PDO;

final class PageTools
{
    private PageService $pages;

    private const NS_PROP = [
        'type' => 'string',
        'description' => 'Optional namespace (defaults to the token namespace)',
    ];

    public function __construct(private readonly PDO $pdo, private readonly string $defaultNamespace)
    {
        $this->pages = new PageService($pdo);
    }

    private function resolveNamespace(array $args): string
    {
        $ns = isset($args['namespace']) && is_string($args['namespace']) ? trim($args['namespace']) : '';
        return $ns !== '' ? $ns : $this->defaultNamespace;
    }

    /**
     * @return list<array{name: string, method: string, description: string, inputSchema: array}>
     */
    public function definitions(): array
    {
        return [
            [
                'name' => 'list_pages',
                'method' => 'listPages',
                'description' => 'List all pages for a namespace. Returns page id, slug, '
                    . 'title, status, type, and language.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => self::NS_PROP,
                    ],
                ],
            ],
            [
                'name' => 'get_page_tree',
                'method' => 'getPageTree',
                'description' => 'Get the page tree (hierarchical structure) for a namespace.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => self::NS_PROP,
                    ],
                ],
            ],
            [
                'name' => 'get_block_contract',
                'method' => 'getBlockContract',
                'description' => 'Get the block contract JSON schema. Returns all supported '
                    . 'block types, their variants, data structures, tokens, and section '
                    . 'appearance options. Use this before creating or updating pages to '
                    . 'understand the valid block format.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
            ],
            [
                'name' => 'upsert_page',
                'method' => 'upsertPage',
                'description' => 'Create or update a page. Provide slug and blocks '
                    . '(array of block objects). Optionally set title, status '
                    . '(draft/published), meta, seo (separate SEO config with '
                    . 'metaTitle, metaDescription, ogTitle, etc.), language, and '
                    . 'base_slug. IMPORTANT: Call get_block_contract first to learn '
                    . 'the required block structure. On validation failure, detailed '
                    . 'field-level errors are returned.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => self::NS_PROP,
                        'slug' => ['type' => 'string', 'description' => 'Page slug (URL path segment)'],
                        'blocks' => ['type' => 'array', 'description' => 'Array of block objects for the page content'],
                        'meta' => [
                            'type' => 'object',
                            'description' => 'Optional page metadata stored inline in the page content JSON',
                        ],
                        'title' => ['type' => 'string', 'description' => 'Optional page title'],
                        'status' => [
                            'type' => 'string',
                            'enum' => ['draft', 'published'],
                            'description' => 'Optional page status',
                        ],
                        'seo' => [
                            'type' => 'object',
                            'description' => 'SEO configuration saved to the dedicated '
                                . 'SEO table. Supported fields: metaTitle, '
                                . 'metaDescription, canonicalUrl, robotsMeta, ogTitle, '
                                . 'ogDescription, ogImage, schemaJson, hreflang, '
                                . 'domain, faviconPath',
                        ],
                        'language' => [
                            'type' => 'string',
                            'enum' => ['de', 'en'],
                            'description' => 'Page language for variant resolution (de or en)',
                        ],
                        'base_slug' => [
                            'type' => 'string',
                            'description' => 'Base slug of the primary (German) page '
                                . 'this is a language variant of',
                        ],
                        'type' => [
                            'type' => 'string',
                            'enum' => ['wiki', 'mcp', ''],
                            'description' => 'Page type. Set to "wiki" to enable direct '
                                . 'wiki mode (articles served at '
                                . '/pages/{slug}/{articleSlug} without /wiki prefix). '
                                . 'Use empty string to clear.',
                        ],
                    ],
                    'required' => ['slug', 'blocks'],
                ],
            ],
            [
                'name' => 'delete_page',
                'method' => 'deletePage',
                'description' => 'Delete a page by its slug. This permanently removes the page and cannot be undone.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => self::NS_PROP,
                        'slug' => ['type' => 'string', 'description' => 'Page slug to delete'],
                    ],
                    'required' => ['slug'],
                ],
            ],
        ];
    }

    public function listPages(array $args): array
    {
        $ns = $this->resolveNamespace($args);
        $items = [];
        foreach ($this->pages->getAllForNamespace($ns) as $page) {
            $items[] = [
                'id' => $page->getId(),
                'namespace' => $page->getNamespace(),
                'slug' => $page->getSlug(),
                'title' => $page->getTitle(),
                'status' => $page->getStatus(),
                'type' => $page->getType(),
                'language' => $page->getLanguage(),
                'base_slug' => $page->getBaseSlug(),
            ];
        }
        return ['namespace' => $ns, 'pages' => $items];
    }

    public function getPageTree(array $args): array
    {
        $ns = $this->resolveNamespace($args);
        $tree = $this->pages->getTree();
        foreach ($tree as $entry) {
            if (($entry['namespace'] ?? null) === $ns) {
                return ['namespace' => $ns, 'tree' => $entry['pages'] ?? []];
            }
        }
        return ['namespace' => $ns, 'tree' => []];
    }

    public function getBlockContract(array $args): array
    {
        $schemaPath = dirname(__DIR__, 3) . '/public/js/components/block-contract.schema.json';
        $json = @file_get_contents($schemaPath);
        if ($json === false) {
            throw new \RuntimeException('Block contract schema file not found');
        }

        $schema = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $definitions = $schema['definitions'] ?? [];

        // Build a resolved, flat reference per block type
        $blockTypes = [];
        foreach ($schema['oneOf'] ?? [] as $entry) {
            $props = $entry['properties'] ?? null;
            $allOf = $entry['allOf'] ?? null;

            $type = null;
            $variants = null;
            $dataRef = null;
            $title = $entry['title'] ?? null;
            $deprecated = !empty($entry['deprecated']);

            if (is_array($props)) {
                $type = $props['type']['const'] ?? null;
                $variants = $props['variant']['enum'] ?? null;
                $dataRef = $props['data'] ?? null;
            }

            // Handle allOf (e.g. proof block with variant-dependent data)
            if ($type === null && is_array($allOf)) {
                foreach ($allOf as $sub) {
                    if (!is_array($sub)) {
                        continue;
                    }
                    $sp = $sub['properties'] ?? [];
                    $type ??= $sp['type']['const'] ?? null;
                    $variants ??= $sp['variant']['enum'] ?? null;
                    $dataRef ??= $sp['data'] ?? null;
                }
            }

            if (!is_string($type)) {
                continue;
            }

            $dataSchema = null;
            if (is_array($dataRef)) {
                $dataSchema = $this->resolveSchemaRefs($dataRef, $definitions);
            }

            $blockTypes[$type] = [
                'title' => $title,
                'type' => $type,
                'variants' => $variants ?? [],
                'deprecated' => $deprecated,
                'dataSchema' => $dataSchema,
            ];
        }

        // Resolve shared definitions (Tokens, SectionAppearance, BlockMeta)
        $tokens = isset($definitions['Tokens']) ? $this->resolveSchemaRefs($definitions['Tokens'], $definitions) : null;
        $blockMeta = isset($definitions['BlockMeta'])
            ? $this->resolveSchemaRefs($definitions['BlockMeta'], $definitions)
            : null;

        $sectionAppearance = $schema['properties']['sectionAppearance']['enum'] ?? null;

        // Build a minimal working example
        $example = [
            'id' => 'block-1',
            'type' => 'rich_text',
            'variant' => 'prose',
            'data' => ['body' => '<p>Hello world</p>'],
        ];

        return [
            'version' => 'block-contract-v1',
            'requiredFields' => ['id', 'type', 'variant', 'data'],
            'optionalFields' => ['tokens', 'sectionAppearance', 'backgroundImage', 'meta'],
            'tokens' => $tokens,
            'sectionAppearance' => $sectionAppearance,
            'blockMeta' => $blockMeta,
            'blockTypes' => $blockTypes,
            'example' => $example,
        ];
    }

    /**
     * Recursively resolve $ref pointers to inline definitions.
     *
     * @param array<string,mixed> $schema
     * @param array<string,mixed> $definitions
     * @return array<string,mixed>
     */
    private function resolveSchemaRefs(array $schema, array $definitions): array
    {
        if (
            isset($schema['$ref'])
            && is_string($schema['$ref'])
            && str_starts_with($schema['$ref'], '#/definitions/')
        ) {
            $refName = substr($schema['$ref'], strlen('#/definitions/'));
            if (isset($definitions[$refName]) && is_array($definitions[$refName])) {
                return $this->resolveSchemaRefs($definitions[$refName], $definitions);
            }
        }

        $resolved = [];
        foreach ($schema as $key => $value) {
            if (is_array($value)) {
                $resolved[$key] = $this->resolveSchemaRefs($value, $definitions);
            } else {
                $resolved[$key] = $value;
            }
        }

        return $resolved;
    }

    public function upsertPage(array $args): array
    {
        $ns = $this->resolveNamespace($args);

        $slug = isset($args['slug']) && is_string($args['slug']) ? trim($args['slug']) : '';
        if ($slug === '') {
            throw new \InvalidArgumentException('slug is required');
        }

        $blocks = $args['blocks'] ?? null;
        if (!is_array($blocks)) {
            throw new \InvalidArgumentException('blocks must be an array');
        }

        $meta = isset($args['meta']) && is_array($args['meta']) ? $args['meta'] : [];

        $content = [
            'blocks' => $blocks,
            'meta' => $meta,
        ];

        $contract = new PageBlockContractMigrator($this->pages);
        if (!$contract->isContractValid($content)) {
            // Run the strict schema validator to collect detailed errors
            $validator = new BlockContractSchemaValidator();
            $errors = $validator->validatePageContent($content);

            $message = 'block_contract_invalid';
            if ($errors !== []) {
                $details = array_map(
                    static fn(array $e): string => sprintf('[%s] %s: %s', $e['blockId'], $e['path'], $e['message']),
                    array_slice($errors, 0, 10)
                );
                $message = "block_contract_invalid:\n" . implode("\n", $details);
                if (count($errors) > 10) {
                    $message .= sprintf("\n… and %d more errors", count($errors) - 10);
                }
            }

            throw new \InvalidArgumentException($message);
        }

        $contentJson = json_encode($content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($contentJson === false) {
            throw new \RuntimeException('Failed to encode content');
        }

        $language = isset($args['language']) && is_string($args['language']) ? trim($args['language']) : null;
        $baseSlug = isset($args['base_slug']) && is_string($args['base_slug']) ? trim($args['base_slug']) : null;

        $existing = $this->pages->findByKey($ns, $slug);
        if ($existing === null) {
            $title = isset($args['title']) && is_string($args['title']) ? trim($args['title']) : $slug;
            $this->pages->create($ns, $slug, $title, $contentJson, 'mcp', $language, $baseSlug);
        } else {
            $this->pages->save($ns, $slug, $contentJson);
        }

        // Update status/title/language/base_slug/type if provided
        $type = isset($args['type']) && is_string($args['type']) ? trim($args['type']) : null;
        $hasUpdatableFields = isset($args['status'])
            || isset($args['title'])
            || $language !== null
            || $baseSlug !== null
            || $type !== null;
        if ($hasUpdatableFields) {
            $fields = [];
            $params = [];

            if (isset($args['status']) && is_string($args['status'])) {
                $status = trim($args['status']);
                if (!in_array($status, ['draft', 'published'], true)) {
                    throw new \InvalidArgumentException('status must be draft or published');
                }
                $fields[] = 'status = ?';
                $params[] = $status;
            }

            if (isset($args['title']) && is_string($args['title']) && trim($args['title']) !== '') {
                $fields[] = 'title = ?';
                $params[] = trim($args['title']);
            }

            if ($language !== null && $language !== '') {
                $fields[] = 'language = ?';
                $params[] = $language;
            }

            if ($baseSlug !== null && $baseSlug !== '') {
                $fields[] = 'base_slug = ?';
                $params[] = $baseSlug;
            }

            if ($type !== null) {
                if (!in_array($type, ['wiki', 'mcp', ''], true)) {
                    throw new \InvalidArgumentException('type must be wiki, mcp, or empty string to clear');
                }
                $fields[] = 'type = ?';
                $params[] = $type === '' ? null : $type;
            }

            if ($fields !== []) {
                $fields[] = 'updated_at = CURRENT_TIMESTAMP';
                $params[] = $ns;
                $params[] = $slug;
                $sql = 'UPDATE pages SET '
                    . implode(', ', $fields)
                    . ' WHERE namespace = ? AND slug = ?';
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);
            }
        }

        // Re-read page to get updated fields (needed for pageId in SEO)
        $page = $this->pages->findByKey($ns, $slug);
        if ($page === null) {
            throw new \RuntimeException('Page not found after upsert');
        }

        // Optional: SEO config (stored in dedicated seo table)
        if (isset($args['seo']) && is_array($args['seo'])) {
            $s = $args['seo'];
            $seoService = new PageSeoConfigService($this->pdo);
            $cfg = new PageSeoConfig(
                $page->getId(),
                is_string($s['slug'] ?? null) ? (string) $s['slug'] : $slug,
                is_string($s['metaTitle'] ?? null) ? $s['metaTitle'] : null,
                is_string($s['metaDescription'] ?? null) ? $s['metaDescription'] : null,
                is_string($s['canonicalUrl'] ?? null) ? $s['canonicalUrl'] : null,
                is_string($s['robotsMeta'] ?? null) ? $s['robotsMeta'] : null,
                is_string($s['ogTitle'] ?? null) ? $s['ogTitle'] : null,
                is_string($s['ogDescription'] ?? null) ? $s['ogDescription'] : null,
                is_string($s['ogImage'] ?? null) ? $s['ogImage'] : null,
                is_string($s['schemaJson'] ?? null) ? $s['schemaJson'] : null,
                is_string($s['hreflang'] ?? null) ? $s['hreflang'] : null,
                is_string($s['domain'] ?? null) ? $s['domain'] : null,
                is_string($s['faviconPath'] ?? null) ? $s['faviconPath'] : null,
            );
            $seoService->save($cfg);
        }

        return [
            'status' => 'ok',
            'namespace' => $ns,
            'slug' => $slug,
            'pageId' => $page->getId(),
            'language' => $page->getLanguage(),
            'base_slug' => $page->getBaseSlug(),
        ];
    }

    public function deletePage(array $args): array
    {
        $ns = $this->resolveNamespace($args);
        $slug = isset($args['slug']) && is_string($args['slug']) ? trim($args['slug']) : '';
        if ($slug === '') {
            throw new \InvalidArgumentException('slug is required');
        }

        $existing = $this->pages->findByKey($ns, $slug);
        if ($existing === null) {
            throw new \RuntimeException('Page not found');
        }

        $this->pages->delete($ns, $slug);

        return [
            'status' => 'deleted',
            'namespace' => $ns,
            'slug' => $slug,
        ];
    }
}
