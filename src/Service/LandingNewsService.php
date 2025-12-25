<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\LandingNews;
use App\Infrastructure\Database;
use App\Service\PageService;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use LogicException;
use PDO;
use PDOException;

/**
 * Handles CRUD operations for landing page news entries.
 */
class LandingNewsService
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connectFromEnv();
    }

    /**
     * Retrieve a single news item by identifier.
     */
    public function find(int $id): ?LandingNews
    {
        if ($id <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare($this->baseSelect('WHERE ln.id = :id LIMIT 1'));
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->hydrate($row) : null;
    }

    /**
     * Retrieve a published news item by page and slug.
     */
    public function findPublished(string $pageSlug, string $newsSlug): ?LandingNews
    {
        $stmt = $this->pdo->prepare($this->baseSelect(
            'WHERE p.slug = :pageSlug AND ln.slug = :slug AND ln.is_published = TRUE LIMIT 1'
        ));
        $stmt->execute([
            'pageSlug' => trim($pageSlug),
            'slug' => trim($newsSlug),
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->hydrate($row) : null;
    }

    /**
     * Fetch the latest published entries for a landing page.
     *
     * @return LandingNews[]
     */
    public function getPublishedForPage(int $pageId, int $limit = 3): array
    {
        if ($pageId <= 0) {
            return [];
        }

        $stmt = $this->pdo->prepare($this->baseSelect(
            'WHERE ln.page_id = :pageId AND ln.is_published = TRUE '
            . 'ORDER BY CASE WHEN ln.published_at IS NULL THEN 1 ELSE 0 END, '
            . 'ln.published_at DESC, ln.id DESC '
            . 'LIMIT :limit'
        ));
        $stmt->bindValue('pageId', $pageId, PDO::PARAM_INT);
        $stmt->bindValue('limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();

        return array_map([$this, 'hydrate'], $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    /**
     * Fetch all news entries for a namespace.
     *
     * @return LandingNews[]
     */
    public function getAllForNamespace(string $namespace): array
    {
        $stmt = $this->pdo->prepare($this->baseSelect(
            'WHERE p.namespace = :namespace ORDER BY ln.is_published DESC, '
            . 'CASE WHEN ln.published_at IS NULL THEN 1 ELSE 0 END, '
            . 'ln.published_at DESC, ln.id DESC'
        ));
        $stmt->execute(['namespace' => trim($namespace) !== '' ? strtolower($namespace) : PageService::DEFAULT_NAMESPACE]);

        return array_map([$this, 'hydrate'], $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    /**
     * Fetch all news entries optionally filtered by landing page.
     *
     * @return LandingNews[]
     */
    public function getAll(?int $pageId = null): array
    {
        $condition = '';
        $params = [];
        if ($pageId !== null && $pageId > 0) {
            $condition = 'WHERE ln.page_id = :pageId';
            $params['pageId'] = $pageId;
        }

        $stmt = $this->pdo->prepare($this->baseSelect(
            $condition . ' ORDER BY ln.is_published DESC, '
            . 'CASE WHEN ln.published_at IS NULL THEN 1 ELSE 0 END, '
            . 'ln.published_at DESC, ln.id DESC'
        ));
        $stmt->execute($params);

        return array_map([$this, 'hydrate'], $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    /**
     * Fetch news entries by IDs.
     *
     * @param int[] $ids
     * @return LandingNews[]
     */
    public function getByIds(array $ids): array
    {
        $normalized = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
        if ($normalized === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($normalized), '?'));
        $stmt = $this->pdo->prepare($this->baseSelect(
            sprintf('WHERE ln.id IN (%s) ORDER BY ln.published_at DESC, ln.id DESC', $placeholders)
        ));
        foreach ($normalized as $index => $id) {
            $stmt->bindValue($index + 1, $id, PDO::PARAM_INT);
        }
        $stmt->execute();

        return array_map([$this, 'hydrate'], $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    /**
     * Create a new news entry.
     */
    public function create(
        int $pageId,
        string $slug,
        string $title,
        ?string $excerpt,
        string $content,
        ?DateTimeImmutable $publishedAt,
        bool $isPublished
    ): LandingNews {
        $pageId = $this->normalizePageId($pageId);
        $normalizedSlug = $this->normalizeSlug($slug);
        $normalizedTitle = $this->normalizeTitle($title);
        $html = trim($content);
        if ($html === '') {
            throw new InvalidArgumentException('Content cannot be empty.');
        }

        $exists = $this->existsForPage($pageId, $normalizedSlug);
        if ($exists) {
            throw new LogicException('A news entry with this slug already exists for the selected page.');
        }

        $normalizedExcerpt = $excerpt !== null ? trim($excerpt) : null;
        $timestamp = $this->normalizePublicationDate($publishedAt, $isPublished);

        $query = <<<'SQL'
            INSERT INTO landing_news (
                page_id,
                slug,
                title,
                excerpt,
                content,
                published_at,
                is_published,
                created_at,
                updated_at
            ) VALUES (
                :pageId,
                :slug,
                :title,
                :excerpt,
                :content,
                :publishedAt,
                :isPublished,
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
            )
SQL;

        $stmt = $this->pdo->prepare($query);
        $stmt->bindValue('pageId', $pageId, PDO::PARAM_INT);
        $stmt->bindValue('slug', $normalizedSlug);
        $stmt->bindValue('title', $normalizedTitle);
        $stmt->bindValue('excerpt', $normalizedExcerpt);
        $stmt->bindValue('content', $html);
        $stmt->bindValue('publishedAt', $timestamp);
        $stmt->bindValue('isPublished', $isPublished, PDO::PARAM_BOOL);
        $stmt->execute();

        $news = $this->findByComposite($pageId, $normalizedSlug);
        if ($news === null) {
            throw new PDOException('Failed to persist landing news entry.');
        }

        return $news;
    }

    /**
     * Update an existing news entry.
     */
    public function update(
        int $id,
        int $pageId,
        string $slug,
        string $title,
        ?string $excerpt,
        string $content,
        ?DateTimeImmutable $publishedAt,
        bool $isPublished
    ): LandingNews {
        $existing = $this->find($id);
        if ($existing === null) {
            throw new InvalidArgumentException('News entry not found.');
        }

        $pageId = $this->normalizePageId($pageId);
        $normalizedSlug = $this->normalizeSlug($slug);
        $normalizedTitle = $this->normalizeTitle($title);
        $html = trim($content);
        if ($html === '') {
            throw new InvalidArgumentException('Content cannot be empty.');
        }

        $other = $this->findByComposite($pageId, $normalizedSlug);
        if ($other !== null && $other->getId() !== $id) {
            throw new LogicException('Another news entry already uses this slug on the selected page.');
        }

        $normalizedExcerpt = $excerpt !== null ? trim($excerpt) : null;
        $timestamp = $this->normalizePublicationDate($publishedAt, $isPublished);

        $query = <<<'SQL'
            UPDATE landing_news SET
                page_id = :pageId,
                slug = :slug,
                title = :title,
                excerpt = :excerpt,
                content = :content,
                published_at = :publishedAt,
                is_published = :isPublished,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
SQL;

        $stmt = $this->pdo->prepare($query);
        $stmt->bindValue('pageId', $pageId, PDO::PARAM_INT);
        $stmt->bindValue('slug', $normalizedSlug);
        $stmt->bindValue('title', $normalizedTitle);
        $stmt->bindValue('excerpt', $normalizedExcerpt);
        $stmt->bindValue('content', $html);
        $stmt->bindValue('publishedAt', $timestamp);
        $stmt->bindValue('isPublished', $isPublished, PDO::PARAM_BOOL);
        $stmt->bindValue('id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $updated = $this->find($id);
        if ($updated === null) {
            throw new PDOException('Failed to update landing news entry.');
        }

        return $updated;
    }

    public function delete(int $id): void
    {
        if ($id <= 0) {
            return;
        }

        $stmt = $this->pdo->prepare('DELETE FROM landing_news WHERE id = :id');
        $stmt->bindValue('id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * Fetch all news entries for media reference analysis.
     *
     * @return LandingNews[]
     */
    public function getAllForPage(int $pageId): array
    {
        if ($pageId <= 0) {
            return [];
        }

        $stmt = $this->pdo->prepare($this->baseSelect(
            'WHERE ln.page_id = :pageId ORDER BY ln.created_at ASC'
        ));
        $stmt->bindValue('pageId', $pageId, PDO::PARAM_INT);
        $stmt->execute();

        return array_map([$this, 'hydrate'], $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    private function baseSelect(string $suffix): string
    {
        return 'SELECT ln.*, p.slug AS page_slug, p.title AS page_title FROM landing_news ln '
            . 'JOIN pages p ON p.id = ln.page_id '
            . $suffix;
    }

    private function hydrate(array $row): LandingNews
    {
        $publishedAt = null;
        $publishedRaw = $row['published_at'] ?? null;
        if (is_string($publishedRaw) && $publishedRaw !== '') {
            $publishedAt = new DateTimeImmutable($publishedRaw);
        }

        $createdRaw = (string) ($row['created_at'] ?? 'now');
        $updatedRaw = (string) ($row['updated_at'] ?? $createdRaw);

        return new LandingNews(
            (int) $row['id'],
            (int) $row['page_id'],
            (string) $row['page_slug'],
            (string) $row['page_title'],
            (string) $row['slug'],
            (string) $row['title'],
            $row['excerpt'] !== null ? (string) $row['excerpt'] : null,
            (string) $row['content'],
            $publishedAt,
            (bool) $row['is_published'],
            new DateTimeImmutable($createdRaw),
            new DateTimeImmutable($updatedRaw)
        );
    }

    private function normalizePageId(int $pageId): int
    {
        if ($pageId <= 0) {
            throw new InvalidArgumentException('A landing page must be selected.');
        }

        return $pageId;
    }

    private function normalizeSlug(string $slug): string
    {
        $normalized = strtolower(trim($slug));
        if ($normalized === '') {
            throw new InvalidArgumentException('Slug is required.');
        }
        if (!preg_match('/^[a-z0-9][a-z0-9\-]{0,99}$/', $normalized)) {
            throw new InvalidArgumentException('Slug may only contain lowercase letters, numbers and hyphens.');
        }

        return $normalized;
    }

    private function normalizeTitle(string $title): string
    {
        $normalized = trim($title);
        if ($normalized === '') {
            throw new InvalidArgumentException('Title is required.');
        }

        return $normalized;
    }

    private function normalizePublicationDate(?DateTimeImmutable $dateTime, bool $isPublished): ?string
    {
        if ($dateTime === null) {
            if ($isPublished) {
                $dateTime = new DateTimeImmutable('now', new DateTimeZone('UTC'));
            } else {
                return null;
            }
        }

        return $dateTime->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:sP');
    }

    private function existsForPage(int $pageId, string $slug): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM landing_news WHERE page_id = :pageId AND slug = :slug LIMIT 1');
        $stmt->execute([
            'pageId' => $pageId,
            'slug' => $slug,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    private function findByComposite(int $pageId, string $slug): ?LandingNews
    {
        $stmt = $this->pdo->prepare($this->baseSelect(
            'WHERE ln.page_id = :pageId AND ln.slug = :slug LIMIT 1'
        ));
        $stmt->execute([
            'pageId' => $pageId,
            'slug' => $slug,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->hydrate($row) : null;
    }
}
