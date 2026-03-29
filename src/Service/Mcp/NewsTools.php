<?php

declare(strict_types=1);

namespace App\Service\Mcp;

use App\Service\LandingNewsService;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

final class NewsTools
{
    use McpToolTrait;

    private LandingNewsService $news;

    private const NS_PROP = [
        'type' => 'string',
        'description' => 'Optional namespace (defaults to the token namespace)',
    ];

    public function __construct(PDO $pdo, private readonly string $defaultNamespace)
    {
        $this->news = new LandingNewsService($pdo);
    }

    /**
     * @return list<array{name: string, method: string, description: string, inputSchema: array}>
     */
    public function definitions(): array
    {
        return [
            [
                'name' => 'list_news',
                'method' => 'listNews',
                'description' => 'List all news articles for a namespace.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => self::NS_PROP,
                    ],
                ],
            ],
            [
                'name' => 'get_news',
                'method' => 'getNews',
                'description' => 'Get a single news article by ID.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => self::NS_PROP,
                        'id' => ['type' => 'integer', 'description' => 'News article ID'],
                    ],
                    'required' => ['id'],
                ],
            ],
            [
                'name' => 'create_news',
                'method' => 'createNews',
                'description' => 'Create a new news article.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => self::NS_PROP,
                        'pageId' => ['type' => 'integer', 'description' => 'Associated page ID'],
                        'slug' => ['type' => 'string', 'description' => 'URL slug for the article'],
                        'title' => ['type' => 'string', 'description' => 'Article title'],
                        'content' => ['type' => 'string', 'description' => 'Article content (HTML)'],
                        'excerpt' => ['type' => 'string', 'description' => 'Optional short excerpt'],
                        'imageUrl' => ['type' => 'string', 'description' => 'Optional image URL'],
                        'isPublished' => ['type' => 'boolean', 'description' => 'Publish immediately (default false)'],
                        'publishedAt' => ['type' => 'string', 'description' => 'Optional ISO 8601 publish date'],
                    ],
                    'required' => ['pageId', 'slug', 'title', 'content'],
                ],
            ],
            [
                'name' => 'update_news',
                'method' => 'updateNews',
                'description' => 'Update an existing news article. Only provided fields are updated.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => self::NS_PROP,
                        'id' => ['type' => 'integer', 'description' => 'News article ID'],
                        'pageId' => ['type' => 'integer', 'description' => 'Associated page ID'],
                        'slug' => ['type' => 'string', 'description' => 'URL slug'],
                        'title' => ['type' => 'string', 'description' => 'Article title'],
                        'content' => ['type' => 'string', 'description' => 'Article content'],
                        'excerpt' => ['type' => 'string', 'description' => 'Short excerpt'],
                        'imageUrl' => ['type' => 'string', 'description' => 'Image URL'],
                        'isPublished' => ['type' => 'boolean', 'description' => 'Published state'],
                        'publishedAt' => ['type' => 'string', 'description' => 'ISO 8601 publish date (null to clear)'],
                    ],
                    'required' => ['id'],
                ],
            ],
            [
                'name' => 'delete_news',
                'method' => 'deleteNews',
                'description' => 'Delete a news article by ID.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => self::NS_PROP,
                        'id' => ['type' => 'integer', 'description' => 'News article ID'],
                    ],
                    'required' => ['id'],
                ],
            ],
            [
                'name' => 'list_news_categories',
                'method' => 'listCategories',
                'description' => 'List all news categories for a namespace.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => self::NS_PROP,
                    ],
                ],
            ],
            [
                'name' => 'create_news_category',
                'method' => 'createCategory',
                'description' => 'Create a new news category.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => self::NS_PROP,
                        'slug' => ['type' => 'string', 'description' => 'URL slug for the category'],
                        'name' => ['type' => 'string', 'description' => 'Display name'],
                        'sortOrder' => ['type' => 'integer', 'description' => 'Sort order (default 0)'],
                    ],
                    'required' => ['slug', 'name'],
                ],
            ],
            [
                'name' => 'delete_news_category',
                'method' => 'deleteCategory',
                'description' => 'Delete a news category by ID. Removes all article associations.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => self::NS_PROP,
                        'id' => ['type' => 'integer', 'description' => 'Category ID'],
                    ],
                    'required' => ['id'],
                ],
            ],
            [
                'name' => 'assign_news_category',
                'method' => 'assignCategoryToArticle',
                'description' => 'Assign a category to a news article.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => self::NS_PROP,
                        'articleId' => ['type' => 'integer', 'description' => 'News article ID'],
                        'categoryId' => ['type' => 'integer', 'description' => 'Category ID'],
                    ],
                    'required' => ['articleId', 'categoryId'],
                ],
            ],
            [
                'name' => 'remove_news_category',
                'method' => 'removeCategoryFromArticle',
                'description' => 'Remove a category assignment from a news article.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => self::NS_PROP,
                        'articleId' => ['type' => 'integer', 'description' => 'News article ID'],
                        'categoryId' => ['type' => 'integer', 'description' => 'Category ID'],
                    ],
                    'required' => ['articleId', 'categoryId'],
                ],
            ],
        ];
    }

    public function listNews(array $args): array
    {
        $ns = $this->resolveNamespace($args);
        $items = [];
        foreach ($this->news->getAllForNamespace($ns) as $news) {
            $items[] = $news->jsonSerialize();
        }
        return ['namespace' => $ns, 'news' => $items];
    }

    public function getNews(array $args): array
    {
        $ns = $this->resolveNamespace($args);
        $id = isset($args['id']) ? (int) $args['id'] : 0;
        if ($id <= 0) {
            throw new \InvalidArgumentException('id is required');
        }

        $news = $this->news->find($id);
        if ($news === null) {
            throw new \RuntimeException('News article not found');
        }

        $data = $news->jsonSerialize();
        $data['categories'] = $this->news->getCategoriesForArticle($id);

        return ['namespace' => $ns, 'news' => $data];
    }

    public function createNews(array $args): array
    {
        $pageId = isset($args['pageId']) ? (int) $args['pageId'] : 0;
        $slug = isset($args['slug']) && is_string($args['slug']) ? trim($args['slug']) : '';
        $title = isset($args['title']) && is_string($args['title']) ? trim($args['title']) : '';
        $content = isset($args['content']) && is_string($args['content']) ? $args['content'] : '';

        if ($pageId <= 0 || $slug === '' || $title === '' || trim($content) === '') {
            throw new \InvalidArgumentException('pageId, slug, title, and content are required');
        }

        $excerpt = isset($args['excerpt']) && is_string($args['excerpt']) ? $args['excerpt'] : null;
        $imageUrl = isset($args['imageUrl']) && is_string($args['imageUrl']) ? $args['imageUrl'] : null;
        $isPublished = isset($args['isPublished']) ? (bool) $args['isPublished'] : false;

        $publishedAt = null;
        if (isset($args['publishedAt']) && is_string($args['publishedAt'])) {
            try {
                $publishedAt = new DateTimeImmutable($args['publishedAt'], new DateTimeZone('UTC'));
            } catch (\Exception $e) {
                throw new \InvalidArgumentException('publishedAt must be a valid ISO 8601 date string, got: ' . $args['publishedAt']);
            }
        }

        $news = $this->news->create($pageId, $slug, $title, $excerpt, $content, $publishedAt, $isPublished, $imageUrl);
        return ['status' => 'created', 'news' => $news->jsonSerialize()];
    }

    public function updateNews(array $args): array
    {
        $id = isset($args['id']) ? (int) $args['id'] : 0;
        if ($id <= 0) {
            throw new \InvalidArgumentException('id is required');
        }

        $existing = $this->news->find($id);
        if ($existing === null) {
            throw new \RuntimeException('News article not found');
        }

        $pageId = isset($args['pageId']) && is_numeric($args['pageId'])
            ? (int) $args['pageId']
            : $existing->getPageId();
        $slug = isset($args['slug']) && is_string($args['slug']) ? trim($args['slug']) : $existing->getSlug();
        $title = isset($args['title']) && is_string($args['title']) ? trim($args['title']) : $existing->getTitle();
        $content = isset($args['content']) && is_string($args['content']) ? $args['content'] : $existing->getContent();
        $excerpt = array_key_exists('excerpt', $args) && is_string($args['excerpt'])
            ? $args['excerpt']
            : $existing->getExcerpt();
        $imageUrl = array_key_exists('imageUrl', $args) && is_string($args['imageUrl'])
            ? $args['imageUrl']
            : $existing->getImageUrl();
        $isPublished = isset($args['isPublished']) ? (bool) $args['isPublished'] : $existing->isPublished();

        $publishedAt = $existing->getPublishedAt();
        if (array_key_exists('publishedAt', $args)) {
            if ($args['publishedAt'] === null) {
                $publishedAt = null;
            } elseif (is_string($args['publishedAt'])) {
                try {
                    $publishedAt = new DateTimeImmutable($args['publishedAt'], new DateTimeZone('UTC'));
                } catch (\Exception $e) {
                    throw new \InvalidArgumentException('publishedAt must be a valid ISO 8601 date string, got: ' . $args['publishedAt']);
                }
            }
        }

        $news = $this->news->update(
            $id,
            $pageId,
            $slug,
            $title,
            $excerpt,
            $content,
            $publishedAt,
            $isPublished,
            $imageUrl
        );
        return ['status' => 'updated', 'news' => $news->jsonSerialize()];
    }

    public function deleteNews(array $args): array
    {
        $id = isset($args['id']) ? (int) $args['id'] : 0;
        if ($id <= 0) {
            throw new \InvalidArgumentException('id is required');
        }

        $existing = $this->news->find($id);
        if ($existing === null) {
            throw new \RuntimeException('News article not found');
        }

        $this->news->delete($id);
        return ['status' => 'deleted'];
    }

    // ── Category tools ──────────────────────────────────────────────

    public function listCategories(array $args): array
    {
        $ns = $this->resolveNamespace($args);

        return ['namespace' => $ns, 'categories' => $this->news->getCategoriesForNamespace($ns)];
    }

    public function createCategory(array $args): array
    {
        $ns = $this->resolveNamespace($args);
        $slug = $this->requireString($args, 'slug');
        $name = $this->requireString($args, 'name');
        $sortOrder = $this->optionalInt($args, 'sortOrder', 0);

        $category = $this->news->createCategory($ns, $slug, $name, $sortOrder);

        return ['status' => 'created', 'category' => $category];
    }

    public function deleteCategory(array $args): array
    {
        $id = $this->requireInt($args, 'id');

        $this->news->deleteCategory($id);

        return ['status' => 'deleted'];
    }

    public function assignCategoryToArticle(array $args): array
    {
        $articleId = $this->requireInt($args, 'articleId');
        $categoryId = $this->requireInt($args, 'categoryId');

        $this->news->assignCategory($articleId, $categoryId);

        return ['status' => 'assigned', 'articleId' => $articleId, 'categoryId' => $categoryId];
    }

    public function removeCategoryFromArticle(array $args): array
    {
        $articleId = $this->requireInt($args, 'articleId');
        $categoryId = $this->requireInt($args, 'categoryId');

        $this->news->removeCategory($articleId, $categoryId);

        return ['status' => 'removed', 'articleId' => $articleId, 'categoryId' => $categoryId];
    }
}
