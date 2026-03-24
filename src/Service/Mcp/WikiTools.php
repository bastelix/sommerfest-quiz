<?php

declare(strict_types=1);

namespace App\Service\Mcp;

use App\Domain\CmsPageWikiArticle;
use App\Service\CmsPageWikiArticleService;
use App\Service\CmsPageWikiSettingsService;
use App\Service\PageService;
use PDO;

final class WikiTools
{
    private CmsPageWikiArticleService $articleService;

    private CmsPageWikiSettingsService $settingsService;

    private PageService $pageService;

    private const NS_PROP = [
        'type' => 'string',
        'description' => 'Optional namespace (defaults to the token namespace)',
    ];

    public function __construct(PDO $pdo, private readonly string $defaultNamespace)
    {
        $this->articleService = new CmsPageWikiArticleService($pdo);
        $this->settingsService = new CmsPageWikiSettingsService($pdo);
        $this->pageService = new PageService($pdo);
    }

    private function resolveNamespace(array $args): string
    {
        $ns = isset($args['namespace']) && is_string($args['namespace']) ? trim($args['namespace']) : '';
        return $ns !== '' ? $ns : $this->defaultNamespace;
    }

    private function requirePage(int $pageId): void
    {
        $page = $this->pageService->findById($pageId);
        if ($page === null) {
            throw new \RuntimeException("Page with ID {$pageId} not found. Use list_pages to find valid page IDs.");
        }
    }

    /**
     * @return list<array{name: string, method: string, description: string, inputSchema: array}>
     */
    public function definitions(): array
    {
        return [
            [
                'name' => 'get_wiki_settings',
                'method' => 'getWikiSettings',
                'description' => 'Get wiki settings for a page, including activation status and menu labels.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => self::NS_PROP,
                        'pageId' => ['type' => 'integer', 'description' => 'The page ID to get wiki settings for'],
                    ],
                    'required' => ['pageId'],
                ],
            ],
            [
                'name' => 'list_wiki_articles',
                'method' => 'listWikiArticles',
                'description' => 'List all wiki articles for a page. Optionally filter by locale.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => self::NS_PROP,
                        'pageId' => ['type' => 'integer', 'description' => 'The page ID to list wiki articles for'],
                        'locale' => ['type' => 'string', 'description' => 'Optional locale filter (e.g. "de", "en")'],
                    ],
                    'required' => ['pageId'],
                ],
            ],
            [
                'name' => 'get_wiki_article',
                'method' => 'getWikiArticle',
                'description' => 'Get a single wiki article by ID, including its full content.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => self::NS_PROP,
                        'id' => ['type' => 'integer', 'description' => 'Wiki article ID'],
                    ],
                    'required' => ['id'],
                ],
            ],
            [
                'name' => 'get_wiki_article_versions',
                'method' => 'getWikiArticleVersions',
                'description' => 'Get the version history of a wiki article.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => self::NS_PROP,
                        'id' => ['type' => 'integer', 'description' => 'Wiki article ID'],
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Maximum number of versions to return (default 10)',
                        ],
                    ],
                    'required' => ['id'],
                ],
            ],
            [
                'name' => 'update_wiki_settings',
                'method' => 'updateWikiSettings',
                'description' => 'Enable or disable the wiki for a page and configure menu labels.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => self::NS_PROP,
                        'pageId' => ['type' => 'integer', 'description' => 'The page ID to update wiki settings for'],
                        'isActive' => [
                            'type' => 'boolean',
                            'description' => 'Whether the wiki is active for this page',
                        ],
                        'menuLabel' => [
                            'type' => 'string',
                            'description' => 'Optional default menu label (max 64 characters)',
                        ],
                        'menuLabels' => [
                            'type' => 'object',
                            'description' => 'Optional per-locale menu labels, e.g. {"de": "Wiki", "en": "Wiki"}',
                            'additionalProperties' => ['type' => 'string'],
                        ],
                    ],
                    'required' => ['pageId', 'isActive'],
                ],
            ],
            [
                'name' => 'create_wiki_article',
                'method' => 'createWikiArticle',
                'description' => 'Create a new wiki article. Content is provided as markdown.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => self::NS_PROP,
                        'pageId' => ['type' => 'integer', 'description' => 'The page ID this article belongs to'],
                        'locale' => ['type' => 'string', 'description' => 'Article locale (e.g. "de", "en"). Defaults to "de"'],
                        'slug' => ['type' => 'string', 'description' => 'URL-friendly slug (lowercase, alphanumeric with hyphens)'],
                        'title' => ['type' => 'string', 'description' => 'Article title'],
                        'markdown' => ['type' => 'string', 'description' => 'Article content in markdown format'],
                        'excerpt' => ['type' => 'string', 'description' => 'Optional short excerpt (max 300 characters)'],
                        'status' => ['type' => 'string', 'description' => 'Article status: "draft", "published", or "archived" (default "draft")', 'enum' => ['draft', 'published', 'archived']],
                        'isStartDocument' => ['type' => 'boolean', 'description' => 'Whether this is the start/home document for the wiki (default false)'],
                    ],
                    'required' => ['pageId', 'slug', 'title', 'markdown'],
                ],
            ],
            [
                'name' => 'update_wiki_article',
                'method' => 'updateWikiArticle',
                'description' => 'Update an existing wiki article. Only provided fields are updated. Content is provided as markdown.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => self::NS_PROP,
                        'id' => ['type' => 'integer', 'description' => 'Wiki article ID'],
                        'locale' => ['type' => 'string', 'description' => 'Article locale'],
                        'slug' => ['type' => 'string', 'description' => 'URL-friendly slug'],
                        'title' => ['type' => 'string', 'description' => 'Article title'],
                        'markdown' => ['type' => 'string', 'description' => 'Article content in markdown format'],
                        'excerpt' => ['type' => 'string', 'description' => 'Short excerpt (max 300 characters)'],
                        'status' => ['type' => 'string', 'description' => 'Article status', 'enum' => ['draft', 'published', 'archived']],
                        'isStartDocument' => ['type' => 'boolean', 'description' => 'Whether this is the start document'],
                    ],
                    'required' => ['id'],
                ],
            ],
            [
                'name' => 'delete_wiki_article',
                'method' => 'deleteWikiArticle',
                'description' => 'Delete a wiki article by ID.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => self::NS_PROP,
                        'id' => ['type' => 'integer', 'description' => 'Wiki article ID'],
                    ],
                    'required' => ['id'],
                ],
            ],
            [
                'name' => 'update_wiki_article_status',
                'method' => 'updateWikiArticleStatus',
                'description' => 'Change the status of a wiki article (draft, published, or archived).',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => self::NS_PROP,
                        'id' => ['type' => 'integer', 'description' => 'Wiki article ID'],
                        'status' => ['type' => 'string', 'description' => 'New status', 'enum' => ['draft', 'published', 'archived']],
                    ],
                    'required' => ['id', 'status'],
                ],
            ],
            [
                'name' => 'reorder_wiki_articles',
                'method' => 'reorderWikiArticles',
                'description' => 'Reorder wiki articles for a page by providing an ordered list of article IDs.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => self::NS_PROP,
                        'pageId' => ['type' => 'integer', 'description' => 'The page ID'],
                        'orderedIds' => [
                            'type' => 'array',
                            'description' => 'Ordered list of article IDs defining the new sort order',
                            'items' => ['type' => 'integer'],
                        ],
                    ],
                    'required' => ['pageId', 'orderedIds'],
                ],
            ],
        ];
    }

    public function getWikiSettings(array $args): array
    {
        $pageId = isset($args['pageId']) ? (int) $args['pageId'] : 0;
        if ($pageId <= 0) {
            throw new \InvalidArgumentException('pageId is required');
        }

        $settings = $this->settingsService->getSettingsForPage($pageId);

        return [
            'namespace' => $this->resolveNamespace($args),
            'settings' => [
                'pageId' => $settings->getPageId(),
                'isActive' => $settings->isActive(),
                'menuLabel' => $settings->getMenuLabel(),
                'menuLabels' => $settings->getMenuLabels(),
                'updatedAt' => $settings->getUpdatedAt()?->format(\DateTimeImmutable::ATOM),
            ],
        ];
    }

    public function listWikiArticles(array $args): array
    {
        $pageId = isset($args['pageId']) ? (int) $args['pageId'] : 0;
        if ($pageId <= 0) {
            throw new \InvalidArgumentException('pageId is required');
        }

        $locale = isset($args['locale']) && is_string($args['locale']) ? strtolower(trim($args['locale'])) : '';

        $articles = $this->articleService->getArticlesForPage($pageId);

        if ($locale !== '') {
            $articles = array_values(array_filter(
                $articles,
                static fn (CmsPageWikiArticle $a): bool => $a->getLocale() === $locale
            ));
        }

        $items = array_map(
            static fn (CmsPageWikiArticle $a): array => $a->jsonSerialize(),
            $articles
        );

        return [
            'namespace' => $this->resolveNamespace($args),
            'pageId' => $pageId,
            'articles' => $items,
        ];
    }

    public function getWikiArticle(array $args): array
    {
        $id = isset($args['id']) ? (int) $args['id'] : 0;
        if ($id <= 0) {
            throw new \InvalidArgumentException('id is required');
        }

        $article = $this->articleService->getArticleById($id);
        if ($article === null) {
            throw new \RuntimeException('Wiki article not found');
        }

        return [
            'namespace' => $this->resolveNamespace($args),
            'article' => $article->jsonSerialize(),
        ];
    }

    public function getWikiArticleVersions(array $args): array
    {
        $id = isset($args['id']) ? (int) $args['id'] : 0;
        if ($id <= 0) {
            throw new \InvalidArgumentException('id is required');
        }

        $limit = isset($args['limit']) ? (int) $args['limit'] : 10;
        if ($limit <= 0) {
            $limit = 10;
        }

        $versions = $this->articleService->getVersions($id, $limit);
        $items = array_map(static function ($v): array {
            return [
                'id' => $v->getId(),
                'articleId' => $v->getArticleId(),
                'contentMarkdown' => $v->getContentMarkdown(),
                'createdAt' => $v->getCreatedAt()->format(\DateTimeImmutable::ATOM),
                'createdBy' => $v->getCreatedBy(),
            ];
        }, $versions);

        return [
            'namespace' => $this->resolveNamespace($args),
            'articleId' => $id,
            'versions' => $items,
        ];
    }

    public function updateWikiSettings(array $args): array
    {
        $pageId = isset($args['pageId']) ? (int) $args['pageId'] : 0;
        if ($pageId <= 0) {
            throw new \InvalidArgumentException('pageId is required');
        }

        $this->requirePage($pageId);

        $isActive = isset($args['isActive']) ? (bool) $args['isActive'] : false;
        $menuLabel = isset($args['menuLabel']) && is_string($args['menuLabel']) ? $args['menuLabel'] : null;
        $menuLabels = isset($args['menuLabels']) && is_array($args['menuLabels']) ? $args['menuLabels'] : null;

        $settings = $this->settingsService->updateSettings($pageId, $isActive, $menuLabel, $menuLabels);

        return [
            'status' => 'updated',
            'namespace' => $this->resolveNamespace($args),
            'settings' => [
                'pageId' => $settings->getPageId(),
                'isActive' => $settings->isActive(),
                'menuLabel' => $settings->getMenuLabel(),
                'menuLabels' => $settings->getMenuLabels(),
                'updatedAt' => $settings->getUpdatedAt()?->format(\DateTimeImmutable::ATOM),
            ],
        ];
    }

    public function createWikiArticle(array $args): array
    {
        $pageId = isset($args['pageId']) ? (int) $args['pageId'] : 0;
        $slug = isset($args['slug']) && is_string($args['slug']) ? trim($args['slug']) : '';
        $title = isset($args['title']) && is_string($args['title']) ? trim($args['title']) : '';
        $markdown = isset($args['markdown']) && is_string($args['markdown']) ? $args['markdown'] : '';

        if ($pageId <= 0 || $slug === '' || $title === '' || trim($markdown) === '') {
            throw new \InvalidArgumentException('pageId, slug, title, and markdown are required');
        }

        $this->requirePage($pageId);

        $locale = isset($args['locale']) && is_string($args['locale']) ? trim($args['locale']) : 'de';
        $excerpt = isset($args['excerpt']) && is_string($args['excerpt']) ? $args['excerpt'] : null;
        $status = isset($args['status']) && is_string($args['status']) ? $args['status'] : CmsPageWikiArticle::STATUS_DRAFT;
        $isStartDocument = isset($args['isStartDocument']) ? (bool) $args['isStartDocument'] : false;

        $article = $this->articleService->saveArticleFromMarkdown(
            $pageId,
            $locale,
            $slug,
            $title,
            $markdown,
            $excerpt,
            $status,
            null,
            null,
            $isStartDocument
        );

        return [
            'status' => 'created',
            'namespace' => $this->resolveNamespace($args),
            'article' => $article->jsonSerialize(),
        ];
    }

    public function updateWikiArticle(array $args): array
    {
        $id = isset($args['id']) ? (int) $args['id'] : 0;
        if ($id <= 0) {
            throw new \InvalidArgumentException('id is required');
        }

        $existing = $this->articleService->getArticleById($id);
        if ($existing === null) {
            throw new \RuntimeException('Wiki article not found');
        }

        $locale = isset($args['locale']) && is_string($args['locale']) ? trim($args['locale']) : $existing->getLocale();
        $slug = isset($args['slug']) && is_string($args['slug']) ? trim($args['slug']) : $existing->getSlug();
        $title = isset($args['title']) && is_string($args['title']) ? trim($args['title']) : $existing->getTitle();
        $markdown = isset($args['markdown']) && is_string($args['markdown']) ? $args['markdown'] : $existing->getContentMarkdown();
        $excerpt = array_key_exists('excerpt', $args) && is_string($args['excerpt']) ? $args['excerpt'] : $existing->getExcerpt();
        $status = isset($args['status']) && is_string($args['status']) ? $args['status'] : $existing->getStatus();
        $isStartDocument = isset($args['isStartDocument']) ? (bool) $args['isStartDocument'] : $existing->isStartDocument();

        $article = $this->articleService->saveArticleFromMarkdown(
            $existing->getPageId(),
            $locale,
            $slug,
            $title,
            $markdown,
            $excerpt,
            $status,
            $id,
            null,
            $isStartDocument
        );

        return [
            'status' => 'updated',
            'namespace' => $this->resolveNamespace($args),
            'article' => $article->jsonSerialize(),
        ];
    }

    public function deleteWikiArticle(array $args): array
    {
        $id = isset($args['id']) ? (int) $args['id'] : 0;
        if ($id <= 0) {
            throw new \InvalidArgumentException('id is required');
        }

        $existing = $this->articleService->getArticleById($id);
        if ($existing === null) {
            throw new \RuntimeException('Wiki article not found');
        }

        $this->articleService->deleteArticle($id);

        return ['status' => 'deleted'];
    }

    public function updateWikiArticleStatus(array $args): array
    {
        $id = isset($args['id']) ? (int) $args['id'] : 0;
        if ($id <= 0) {
            throw new \InvalidArgumentException('id is required');
        }

        $status = isset($args['status']) && is_string($args['status']) ? $args['status'] : '';
        if ($status === '') {
            throw new \InvalidArgumentException('status is required');
        }

        $article = $this->articleService->updateStatus($id, $status);

        return [
            'status' => 'updated',
            'namespace' => $this->resolveNamespace($args),
            'article' => $article->jsonSerialize(),
        ];
    }

    public function reorderWikiArticles(array $args): array
    {
        $pageId = isset($args['pageId']) ? (int) $args['pageId'] : 0;
        if ($pageId <= 0) {
            throw new \InvalidArgumentException('pageId is required');
        }

        $this->requirePage($pageId);

        $orderedIds = isset($args['orderedIds']) && is_array($args['orderedIds']) ? $args['orderedIds'] : [];
        if ($orderedIds === []) {
            throw new \InvalidArgumentException('orderedIds is required and must not be empty');
        }

        $orderedIds = array_map('intval', $orderedIds);

        $this->articleService->reorderArticles($pageId, $orderedIds);

        return [
            'status' => 'reordered',
            'namespace' => $this->resolveNamespace($args),
            'pageId' => $pageId,
        ];
    }
}
