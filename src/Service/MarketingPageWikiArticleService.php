<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\MarketingPageWikiArticle;
use App\Domain\MarketingPageWikiVersion;
use App\Infrastructure\Database;
use App\Service\Marketing\Wiki\EditorJsToMarkdown;
use App\Service\Marketing\Wiki\WikiPublisher;
use DateTimeImmutable;
use JsonException;
use PDO;
use PDOException;
use RuntimeException;

final class MarketingPageWikiArticleService
{
    private PDO $pdo;

    private EditorJsToMarkdown $converter;

    private ?WikiPublisher $publisher;

    public function __construct(?PDO $pdo = null, ?EditorJsToMarkdown $converter = null, ?WikiPublisher $publisher = null)
    {
        $this->pdo = $pdo ?? Database::connectFromEnv();
        $this->converter = $converter ?? new EditorJsToMarkdown();
        $this->publisher = $publisher ?? new WikiPublisher();
    }

    /**
     * @return MarketingPageWikiArticle[]
     */
    public function getArticlesForPage(int $pageId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM marketing_page_wiki_articles WHERE page_id = ? ORDER BY locale, sort_index ASC, id ASC');
        $stmt->execute([$pageId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map([$this, 'hydrateArticle'], $rows);
    }

    public function getArticleById(int $articleId): ?MarketingPageWikiArticle
    {
        $stmt = $this->pdo->prepare('SELECT * FROM marketing_page_wiki_articles WHERE id = ?');
        $stmt->execute([$articleId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        return $this->hydrateArticle($row);
    }

    /**
     * @return MarketingPageWikiArticle[]
     */
    public function getPublishedArticles(int $pageId, string $locale): array
    {
        $locale = strtolower(trim($locale));
        if ($locale === '') {
            $locale = 'de';
        }

        $stmt = $this->pdo->prepare('SELECT * FROM marketing_page_wiki_articles WHERE page_id = ? AND locale = ? AND status = ? ORDER BY sort_index ASC, (published_at IS NULL), published_at DESC, id ASC');
        $stmt->execute([$pageId, $locale, MarketingPageWikiArticle::STATUS_PUBLISHED]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map([$this, 'hydrateArticle'], $rows);
    }

    public function findPublishedArticle(int $pageId, string $locale, string $slug): ?MarketingPageWikiArticle
    {
        $stmt = $this->pdo->prepare('SELECT * FROM marketing_page_wiki_articles WHERE page_id = ? AND locale = ? AND slug = ? AND status = ?');
        $stmt->execute([$pageId, strtolower($locale), strtolower($slug), MarketingPageWikiArticle::STATUS_PUBLISHED]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        return $this->hydrateArticle($row);
    }

    /**
     * @param array<string,mixed> $editorState
     */
    public function saveArticle(
        int $pageId,
        string $locale,
        string $slug,
        string $title,
        ?string $excerpt,
        array $editorState,
        string $status = MarketingPageWikiArticle::STATUS_DRAFT,
        ?int $articleId = null,
        ?DateTimeImmutable $publishedAt = null,
        ?int $sortIndex = null
    ): MarketingPageWikiArticle {
        $normalizedLocale = strtolower(trim($locale)) ?: 'de';
        $normalizedSlug = strtolower(trim($slug));
        if ($normalizedSlug === '' || !preg_match('/^[a-z0-9][a-z0-9-]{0,127}$/', $normalizedSlug)) {
            throw new RuntimeException('Invalid article slug.');
        }

        $normalizedTitle = trim($title);
        if ($normalizedTitle === '') {
            throw new RuntimeException('Title is required.');
        }

        $normalizedExcerpt = $excerpt !== null ? trim($excerpt) : null;
        if ($normalizedExcerpt !== null && mb_strlen($normalizedExcerpt) > 300) {
            throw new RuntimeException('Excerpt must not exceed 300 characters.');
        }

        if (!in_array($status, [
            MarketingPageWikiArticle::STATUS_DRAFT,
            MarketingPageWikiArticle::STATUS_PUBLISHED,
            MarketingPageWikiArticle::STATUS_ARCHIVED,
        ], true)) {
            throw new RuntimeException('Invalid article status.');
        }

        $conversion = $this->converter->convert($editorState);
        $markdown = $conversion['markdown'];
        $html = $conversion['html'];

        if ($status === MarketingPageWikiArticle::STATUS_PUBLISHED && $publishedAt === null) {
            $publishedAt = new DateTimeImmutable();
        }
        if ($status !== MarketingPageWikiArticle::STATUS_PUBLISHED) {
            $publishedAt = null;
        }

        $this->pdo->beginTransaction();
        try {
            if ($articleId !== null) {
                $update = $this->pdo->prepare('UPDATE marketing_page_wiki_articles SET slug = ?, locale = ?, title = ?, excerpt = ?, editor_json = ?, content_md = ?, content_html = ?, status = ?, sort_index = COALESCE(?, sort_index), published_at = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
                $update->execute([
                    $normalizedSlug,
                    $normalizedLocale,
                    $normalizedTitle,
                    $normalizedExcerpt,
                    $this->encodeEditorState($editorState),
                    $markdown,
                    $html,
                    $status,
                    $sortIndex,
                    $publishedAt?->format('c'),
                    $articleId,
                ]);
                $id = $articleId;
            } else {
                $insert = $this->pdo->prepare('INSERT INTO marketing_page_wiki_articles (page_id, slug, locale, title, excerpt, editor_json, content_md, content_html, status, sort_index, published_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $insert->execute([
                    $pageId,
                    $normalizedSlug,
                    $normalizedLocale,
                    $normalizedTitle,
                    $normalizedExcerpt,
                    $this->encodeEditorState($editorState),
                    $markdown,
                    $html,
                    $status,
                    $sortIndex ?? 0,
                    $publishedAt?->format('c'),
                ]);
                $id = (int) $this->pdo->lastInsertId();
            }

            $versionInsert = $this->pdo->prepare('INSERT INTO marketing_page_wiki_versions (article_id, editor_json, content_md, created_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)');
            $versionInsert->execute([
                $id,
                $this->encodeEditorState($editorState),
                $markdown,
            ]);

            $this->pdo->commit();
        } catch (PDOException $exception) {
            $this->pdo->rollBack();
            throw new RuntimeException('Saving article failed: ' . $exception->getMessage(), 0, $exception);
        }

        $article = $this->getArticleById($id);
        if ($article === null) {
            throw new RuntimeException('Article could not be loaded after saving.');
        }

        if ($article->isPublished() && $this->publisher !== null) {
            $page = $this->resolvePageSlug($pageId);
            if ($page !== null) {
                $this->publisher->publish($page, $article);
            }
        }

        return $article;
    }

    public function updateStatus(int $articleId, string $status): MarketingPageWikiArticle
    {
        if (!in_array($status, [
            MarketingPageWikiArticle::STATUS_DRAFT,
            MarketingPageWikiArticle::STATUS_PUBLISHED,
            MarketingPageWikiArticle::STATUS_ARCHIVED,
        ], true)) {
            throw new RuntimeException('Invalid status');
        }

        $article = $this->getArticleById($articleId);
        if ($article === null) {
            throw new RuntimeException('Article not found');
        }

        $publishedAt = $article->getPublishedAt();
        if ($status === MarketingPageWikiArticle::STATUS_PUBLISHED && $publishedAt === null) {
            $publishedAt = new DateTimeImmutable();
        }
        if ($status !== MarketingPageWikiArticle::STATUS_PUBLISHED) {
            $publishedAt = null;
        }

        $stmt = $this->pdo->prepare('UPDATE marketing_page_wiki_articles SET status = ?, published_at = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([
            $status,
            $publishedAt?->format('c'),
            $articleId,
        ]);

        $updated = $this->getArticleById($articleId);
        if ($updated === null) {
            throw new RuntimeException('Failed to load updated article');
        }

        if ($updated->isPublished() && $this->publisher !== null) {
            $page = $this->resolvePageSlug($updated->getPageId());
            if ($page !== null) {
                $this->publisher->publish($page, $updated);
            }
        }

        if (!$updated->isPublished() && $this->publisher !== null) {
            $page = $this->resolvePageSlug($updated->getPageId());
            if ($page !== null) {
                $this->publisher->remove($page, $updated->getLocale(), $updated->getSlug());
            }
        }

        return $updated;
    }

    public function deleteArticle(int $articleId): void
    {
        $article = $this->getArticleById($articleId);
        if ($article === null) {
            return;
        }

        $stmt = $this->pdo->prepare('DELETE FROM marketing_page_wiki_articles WHERE id = ?');
        $stmt->execute([$articleId]);

        if ($this->publisher !== null) {
            $page = $this->resolvePageSlug($article->getPageId());
            if ($page !== null) {
                $this->publisher->remove($page, $article->getLocale(), $article->getSlug());
            }
        }
    }

    public function exportMarkdown(int $articleId): string
    {
        $article = $this->getArticleById($articleId);
        if ($article === null) {
            throw new RuntimeException('Article not found');
        }

        return $article->getContentMarkdown();
    }

    /**
     * @return MarketingPageWikiVersion[]
     */
    public function getVersions(int $articleId, int $limit = 10): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM marketing_page_wiki_versions WHERE article_id = ? ORDER BY created_at DESC, id DESC LIMIT ?');
        $stmt->bindValue(1, $articleId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map([$this, 'hydrateVersion'], $rows);
    }

    /**
     * @param array<string,mixed> $row
     */
    private function hydrateArticle(array $row): MarketingPageWikiArticle
    {
        $editorState = null;
        if (isset($row['editor_json']) && $row['editor_json'] !== null && $row['editor_json'] !== '') {
            $decoded = json_decode((string) $row['editor_json'], true);
            if (is_array($decoded)) {
                $editorState = $decoded;
            }
        }

        $publishedAt = isset($row['published_at']) && $row['published_at'] !== null
            ? new DateTimeImmutable((string) $row['published_at'])
            : null;
        $updatedAt = isset($row['updated_at']) && $row['updated_at'] !== null
            ? new DateTimeImmutable((string) $row['updated_at'])
            : null;

        return new MarketingPageWikiArticle(
            (int) $row['id'],
            (int) $row['page_id'],
            (string) $row['slug'],
            (string) $row['locale'],
            (string) $row['title'],
            isset($row['excerpt']) ? (string) $row['excerpt'] : null,
            $editorState,
            (string) $row['content_md'],
            (string) $row['content_html'],
            (string) $row['status'],
            (int) ($row['sort_index'] ?? 0),
            $publishedAt,
            $updatedAt
        );
    }

    /**
     * @param array<string,mixed> $row
     */
    private function hydrateVersion(array $row): MarketingPageWikiVersion
    {
        $editorState = null;
        if (isset($row['editor_json']) && $row['editor_json'] !== null && $row['editor_json'] !== '') {
            $decoded = json_decode((string) $row['editor_json'], true);
            if (is_array($decoded)) {
                $editorState = $decoded;
            }
        }

        return new MarketingPageWikiVersion(
            (int) $row['id'],
            (int) $row['article_id'],
            $editorState,
            (string) $row['content_md'],
            new DateTimeImmutable((string) $row['created_at']),
            isset($row['created_by']) ? (string) $row['created_by'] : null
        );
    }

    private function resolvePageSlug(int $pageId): ?string
    {
        $stmt = $this->pdo->prepare('SELECT slug FROM pages WHERE id = ?');
        $stmt->execute([$pageId]);
        $slug = $stmt->fetchColumn();

        if (!is_string($slug)) {
            return null;
        }

        return (string) $slug;
    }

    /**
     * @param array<string,mixed> $state
     */
    private function encodeEditorState(array $state): string
    {
        try {
            $json = json_encode($state, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $exception) {
            throw new RuntimeException('Invalid editor state payload.', 0, $exception);
        }

        return (string) $json;
    }
}
