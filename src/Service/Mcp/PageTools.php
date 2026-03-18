<?php

declare(strict_types=1);

namespace App\Service\Mcp;

use App\Service\PageBlockContractMigrator;
use App\Service\PageService;
use PDO;

final class PageTools
{
    private PageService $pages;

    private const NS_PROP = ['type' => 'string', 'description' => 'Optional namespace (defaults to the token namespace)'];

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
                'description' => 'List all pages for a namespace. Returns page id, slug, title, status, type, and language.',
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
                'name' => 'upsert_page',
                'method' => 'upsertPage',
                'description' => 'Create or update a page. Provide slug and blocks (array of block objects). Optionally set title, status (draft/published), meta, and seo.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => self::NS_PROP,
                        'slug' => ['type' => 'string', 'description' => 'Page slug (URL path segment)'],
                        'blocks' => ['type' => 'array', 'description' => 'Array of block objects for the page content'],
                        'meta' => ['type' => 'object', 'description' => 'Optional page metadata'],
                        'title' => ['type' => 'string', 'description' => 'Optional page title'],
                        'status' => ['type' => 'string', 'enum' => ['draft', 'published'], 'description' => 'Optional page status'],
                    ],
                    'required' => ['slug', 'blocks'],
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
            throw new \InvalidArgumentException('block_contract_invalid');
        }

        $contentJson = json_encode($content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($contentJson === false) {
            throw new \RuntimeException('Failed to encode content');
        }

        $existing = $this->pages->findByKey($ns, $slug);
        if ($existing === null) {
            $title = isset($args['title']) && is_string($args['title']) ? trim($args['title']) : $slug;
            $this->pages->create($ns, $slug, $title, $contentJson, 'mcp');
        } else {
            $this->pages->save($ns, $slug, $contentJson);
        }

        $page = $this->pages->findByKey($ns, $slug);
        if ($page === null) {
            throw new \RuntimeException('Page not found after upsert');
        }

        // Update status/title if provided
        if (isset($args['status']) || isset($args['title'])) {
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

            if ($fields !== []) {
                $fields[] = 'updated_at = CURRENT_TIMESTAMP';
                $params[] = $ns;
                $params[] = $slug;
                $stmt = $this->pdo->prepare('UPDATE pages SET ' . implode(', ', $fields) . ' WHERE namespace = ? AND slug = ?');
                $stmt->execute($params);
            }
        }

        return [
            'status' => 'ok',
            'namespace' => $ns,
            'slug' => $slug,
            'pageId' => $page->getId(),
        ];
    }
}
