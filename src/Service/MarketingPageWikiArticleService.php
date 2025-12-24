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

    private WikiPublisher $publisher;

    public function __construct(
        ?PDO $pdo = null,
        ?EditorJsToMarkdown $converter = null,
        ?WikiPublisher $publisher = null
    ) {
        $this->pdo = $pdo ?? Database::connectFromEnv();
        $this->converter = $converter ?? new EditorJsToMarkdown();
        $this->publisher = $publisher ?? new WikiPublisher();
    }

    /**
     * @return MarketingPageWikiArticle[]
     */
    public function getArticlesForPage(int $pageId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM marketing_page_wiki_articles WHERE page_id = ? '
            . 'ORDER BY locale, is_start_document DESC, sort_index ASC, id ASC'
        );
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

        $stmt = $this->pdo->prepare(
            'SELECT * FROM marketing_page_wiki_articles WHERE page_id = ? AND locale = ? AND status = ? '
            . 'ORDER BY is_start_document DESC, sort_index ASC, (published_at IS NULL), published_at DESC, id ASC'
        );
        $stmt->execute([$pageId, $locale, MarketingPageWikiArticle::STATUS_PUBLISHED]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map([$this, 'hydrateArticle'], $rows);
    }

    public function findPublishedArticle(int $pageId, string $locale, string $slug): ?MarketingPageWikiArticle
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM marketing_page_wiki_articles WHERE page_id = ? AND locale = ? AND slug = ? AND status = ?'
        );
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
        ?int $sortIndex = null,
        bool $isStartDocument = false
    ): MarketingPageWikiArticle {
        $normalizedLocale = strtolower(trim($locale)) ?: 'de';
        $normalizedSlug = $this->normalizeSlug($slug);
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

        if (
            !in_array($status, [
            MarketingPageWikiArticle::STATUS_DRAFT,
            MarketingPageWikiArticle::STATUS_PUBLISHED,
            MarketingPageWikiArticle::STATUS_ARCHIVED,
            ], true)
        ) {
            throw new RuntimeException('Invalid article status.');
        }

        if ($this->slugExists($pageId, $normalizedLocale, $normalizedSlug, $articleId)) {
            throw new RuntimeException('Slug already exists for this locale.');
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

        if ($sortIndex === null) {
            $sortIndex = $this->determineNextSortIndex($pageId, $normalizedLocale);
        }

        $this->pdo->beginTransaction();
        try {
            if ($articleId !== null) {
                $update = $this->pdo->prepare(
                    'UPDATE marketing_page_wiki_articles '
                    . 'SET slug = ?, locale = ?, title = ?, excerpt = ?, editor_json = ?, '
                    . 'content_md = ?, content_html = ?, status = ?, '
                    . 'sort_index = COALESCE(?, sort_index), published_at = ?, '
                    . 'updated_at = CURRENT_TIMESTAMP WHERE id = ?'
                );
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
                $insert = $this->pdo->prepare(
                    'INSERT INTO marketing_page_wiki_articles '
                    . '(page_id, slug, locale, title, excerpt, editor_json, content_md, '
                    . 'content_html, status, sort_index, published_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
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
                    $sortIndex,
                    $publishedAt?->format('c'),
                ]);
                $id = (int) $this->pdo->lastInsertId();
            }

            $versionInsert = $this->pdo->prepare(
                'INSERT INTO marketing_page_wiki_versions (article_id, editor_json, content_md, created_at) '
                . 'VALUES (?, ?, ?, CURRENT_TIMESTAMP)'
            );
            $versionInsert->execute([
                $id,
                $this->encodeEditorState($editorState),
                $markdown,
            ]);

            $this->applyStartDocumentState($pageId, $normalizedLocale, $id, $isStartDocument);

            $this->pdo->commit();
        } catch (PDOException $exception) {
            $this->pdo->rollBack();
            throw new RuntimeException('Saving article failed: ' . $exception->getMessage(), 0, $exception);
        }

        $article = $this->getArticleById($id);
        if ($article === null) {
            throw new RuntimeException('Article could not be loaded after saving.');
        }

        if ($article->isPublished()) {
            $page = $this->resolvePageSlug($pageId);
            if ($page !== null) {
                $this->publisher->publish($page, $article);
            }
        }

        return $article;
    }

    public function saveArticleFromMarkdown(
        int $pageId,
        string $locale,
        string $slug,
        string $title,
        string $markdown,
        ?string $excerpt = null,
        string $status = MarketingPageWikiArticle::STATUS_DRAFT,
        ?int $articleId = null,
        ?int $sortIndex = null,
        bool $isStartDocument = false
    ): MarketingPageWikiArticle {
        $editorState = $this->convertMarkdownToEditorState($markdown);

        return $this->saveArticle(
            $pageId,
            $locale,
            $slug,
            $title,
            $excerpt,
            $editorState,
            $status,
            $articleId,
            null,
            $sortIndex,
            $isStartDocument
        );
    }

    public function updateStatus(int $articleId, string $status): MarketingPageWikiArticle
    {
        if (
            !in_array($status, [
            MarketingPageWikiArticle::STATUS_DRAFT,
            MarketingPageWikiArticle::STATUS_PUBLISHED,
            MarketingPageWikiArticle::STATUS_ARCHIVED,
            ], true)
        ) {
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

        $stmt = $this->pdo->prepare(
            'UPDATE marketing_page_wiki_articles SET status = ?, published_at = ?, '
            . 'updated_at = CURRENT_TIMESTAMP WHERE id = ?'
        );
        $stmt->execute([
            $status,
            $publishedAt?->format('c'),
            $articleId,
        ]);

        $updated = $this->getArticleById($articleId);
        if ($updated === null) {
            throw new RuntimeException('Failed to load updated article');
        }

        if ($updated->isPublished()) {
            $page = $this->resolvePageSlug($updated->getPageId());
            if ($page !== null) {
                $this->publisher->publish($page, $updated);
            }
        }

        if (!$updated->isPublished()) {
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

        $page = $this->resolvePageSlug($article->getPageId());
        if ($page !== null) {
            $this->publisher->remove($page, $article->getLocale(), $article->getSlug());
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

    public function duplicateArticle(
        int $pageId,
        int $articleId,
        ?string $desiredSlug = null,
        ?string $titleOverride = null
    ): MarketingPageWikiArticle {
        $article = $this->getArticleById($articleId);
        if ($article === null || $article->getPageId() !== $pageId) {
            throw new RuntimeException('Article not found');
        }

        $locale = $article->getLocale();
        $slug = $desiredSlug !== null && trim($desiredSlug) !== ''
            ? strtolower(trim($desiredSlug))
            : $this->generateDuplicateSlug($pageId, $locale, $article->getSlug());

        $title = $titleOverride !== null && trim($titleOverride) !== ''
            ? trim($titleOverride)
            : $article->getTitle();

        $editorState = $article->getEditorState() ?? ['blocks' => []];
        $sortIndex = $this->determineNextSortIndex($pageId, $locale);

        return $this->saveArticle(
            $pageId,
            $locale,
            $slug,
            $title,
            $article->getExcerpt(),
            $editorState,
            MarketingPageWikiArticle::STATUS_DRAFT,
            null,
            null,
            $sortIndex,
            false
        );
    }

    public function setStartDocument(int $articleId, bool $isStartDocument): MarketingPageWikiArticle
    {
        $article = $this->getArticleById($articleId);
        if ($article === null) {
            throw new RuntimeException('Article not found');
        }

        $pageId = $article->getPageId();
        $locale = $article->getLocale();

        $this->pdo->beginTransaction();

        try {
            $this->applyStartDocumentState($pageId, $locale, $articleId, $isStartDocument);
            $this->pdo->commit();
        } catch (PDOException $exception) {
            $this->pdo->rollBack();
            throw new RuntimeException('Updating start document failed: ' . $exception->getMessage(), 0, $exception);
        }

        $updated = $this->getArticleById($articleId);
        if ($updated === null) {
            throw new RuntimeException('Article could not be loaded after updating start document.');
        }

        return $updated;
    }

    /**
     * @param int[] $orderedIds
     */
    public function reorderArticles(int $pageId, array $orderedIds): void
    {
        $orderedIds = array_values(array_unique(array_map('intval', $orderedIds)));

        $existingIds = $this->getArticleIdsForPage($pageId);
        if ($existingIds === []) {
            return;
        }

        $missing = array_diff($orderedIds, $existingIds);
        if ($missing !== []) {
            throw new RuntimeException('Unknown article id(s) for page: ' . implode(', ', $missing));
        }

        $this->pdo->beginTransaction();

        try {
                $update = $this->pdo->prepare(
                    'UPDATE marketing_page_wiki_articles SET sort_index = ? WHERE page_id = ? AND id = ?'
                );
            $position = 0;

            foreach ($orderedIds as $orderedId) {
                $update->execute([$position, $pageId, $orderedId]);
                $position++;
            }

            foreach ($existingIds as $articleId) {
                if (in_array($articleId, $orderedIds, true)) {
                    continue;
                }

                $update->execute([$position, $pageId, $articleId]);
                $position++;
            }

            $this->pdo->commit();
        } catch (PDOException $exception) {
            $this->pdo->rollBack();
            throw new RuntimeException('Updating article order failed: ' . $exception->getMessage(), 0, $exception);
        }
    }

    /**
     * @return MarketingPageWikiVersion[]
     */
    public function getVersions(int $articleId, int $limit = 10): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM marketing_page_wiki_versions WHERE article_id = ? '
            . 'ORDER BY created_at DESC, id DESC LIMIT ?'
        );
        $stmt->bindValue(1, $articleId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map([$this, 'hydrateVersion'], $rows);
    }

    private function determineNextSortIndex(int $pageId, string $locale): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT MAX(sort_index) FROM marketing_page_wiki_articles WHERE page_id = ? AND locale = ?'
        );
        $stmt->execute([$pageId, strtolower($locale)]);
        $max = $stmt->fetchColumn();

        if (!is_numeric($max)) {
            return 0;
        }

        return ((int) $max) + 1;
    }

    private function generateDuplicateSlug(int $pageId, string $locale, string $baseSlug): string
    {
        $normalizedBase = $this->normalizeSlug($baseSlug);
        if ($normalizedBase === '') {
            $normalizedBase = 'article';
        }

        $candidate = $normalizedBase . '-copy';
        $suffix = 2;

        while ($this->slugExists($pageId, $locale, $candidate)) {
            $candidate = sprintf('%s-copy-%d', $normalizedBase, $suffix);
            $suffix++;
        }

        return $candidate;
    }

    private function slugExists(int $pageId, string $locale, string $slug, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT id FROM marketing_page_wiki_articles WHERE page_id = ? AND locale = ? AND slug = ?';
        $params = [$pageId, strtolower($locale), strtolower($slug)];

        if ($ignoreId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $ignoreId;
        }

        $sql .= ' LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * @return int[]
     */
    private function getArticleIdsForPage(int $pageId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM marketing_page_wiki_articles WHERE page_id = ? ORDER BY sort_index ASC, id ASC'
        );
        $stmt->execute([$pageId]);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        return array_map(static fn ($id) => (int) $id, $ids);
    }

    /**
     * @param array<string,mixed> $row
     */
    private function hydrateArticle(array $row): MarketingPageWikiArticle
    {
        $editorState = null;
        if (isset($row['editor_json']) && $row['editor_json'] !== '') {
            $decoded = json_decode((string) $row['editor_json'], true);
            if (is_array($decoded)) {
                $editorState = $decoded;
            }
        }

        $publishedAt = isset($row['published_at'])
            ? new DateTimeImmutable((string) $row['published_at'])
            : null;
        $updatedAt = isset($row['updated_at'])
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
            (bool) ($row['is_start_document'] ?? false),
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
        if (isset($row['editor_json']) && $row['editor_json'] !== '') {
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

    private function applyStartDocumentState(int $pageId, string $locale, int $articleId, bool $isStartDocument): void
    {
        $normalizedLocale = strtolower(trim($locale));
        if ($normalizedLocale === '') {
            $normalizedLocale = 'de';
        }

        if ($isStartDocument) {
            $clear = $this->pdo->prepare(
                'UPDATE marketing_page_wiki_articles SET is_start_document = FALSE '
                . 'WHERE page_id = ? AND locale = ? AND id <> ?'
            );
            $clear->execute([$pageId, $normalizedLocale, $articleId]);

            $set = $this->pdo->prepare(
                'UPDATE marketing_page_wiki_articles SET is_start_document = TRUE WHERE id = ?'
            );
            $set->execute([$articleId]);

            return;
        }

        $reset = $this->pdo->prepare(
            'UPDATE marketing_page_wiki_articles SET is_start_document = FALSE WHERE id = ?'
        );
        $reset->execute([$articleId]);
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

    private function normalizeSlug(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (function_exists('iconv')) {
            $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if (is_string($transliterated) && $transliterated !== '') {
                $value = $transliterated;
            }
        }

        $value = str_replace('ß', 'ss', $value);
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        $value = trim($value, '-');
        $value = preg_replace('/-+/', '-', $value) ?? '';

        return $value;
    }

    /**
     * @return array{blocks:list<array<string,mixed>>}
     */
    private function convertMarkdownToEditorState(string $markdown): array
    {
        $normalized = preg_replace('/\r\n?/', "\n", $markdown) ?? '';
        $lines = explode("\n", $normalized);
        /** @var list<array<string,mixed>> $blocks */
        $blocks = [];
        /** @var list<string> $paragraphLines */
        $paragraphLines = [];
        /** @var list<string> $listItems */
        $listItems = [];
        /** @var 'ordered'|'unordered'|null $listType */
        $listType = null;
        /** @var list<string> $quoteLines */
        $quoteLines = [];
        /** @var list<string> $codeLines */
        $codeLines = [];
        $inCode = false;

        foreach ($lines as $line) {
            $raw = rtrim($line, "\r");
            $trimmed = ltrim($raw);

            if ($inCode) {
                if (preg_match('/^```/', $trimmed)) {
                    $blocks[] = [
                        'type' => 'code',
                        'data' => ['code' => implode("\n", $codeLines)],
                    ];
                    $codeLines = [];
                    $inCode = false;
                    continue;
                }
                $codeLines[] = $raw;
                continue;
            }

            if (preg_match('/^```/', $trimmed)) {
                $this->flushParagraphLines($paragraphLines, $blocks);
                $this->flushListItems($listItems, $listType, $blocks);
                $this->flushQuoteLines($quoteLines, $blocks);
                $inCode = true;
                $codeLines = [];
                continue;
            }

            if (trim($trimmed) === '') {
                $this->flushParagraphLines($paragraphLines, $blocks);
                $this->flushListItems($listItems, $listType, $blocks);
                $this->flushQuoteLines($quoteLines, $blocks);
                continue;
            }

            if (preg_match('/^(#{1,6})\s+(.*)$/', $trimmed, $matches)) {
                $this->flushParagraphLines($paragraphLines, $blocks);
                $this->flushListItems($listItems, $listType, $blocks);
                $this->flushQuoteLines($quoteLines, $blocks);
                $level = strlen($matches[1]);
                $level = max(1, min(6, $level));
                $text = $this->renderInlineContent(trim((string) $matches[2]));
                $blocks[] = [
                    'type' => 'header',
                    'data' => ['level' => $level, 'text' => $text],
                ];
                continue;
            }

            if (preg_match('/^[-*]\s+(.*)$/', $trimmed, $matches)) {
                $this->flushParagraphLines($paragraphLines, $blocks);
                $this->flushQuoteLines($quoteLines, $blocks);
                if ($listType === 'ordered') {
                    $this->flushListItems($listItems, $listType, $blocks);
                }
                $listType = 'unordered';
                $listItems[] = $this->renderInlineContent(trim((string) $matches[1]));
                continue;
            }

            if (preg_match('/^\d+\.\s+(.*)$/', $trimmed, $matches)) {
                $this->flushParagraphLines($paragraphLines, $blocks);
                $this->flushQuoteLines($quoteLines, $blocks);
                if ($listType === 'unordered') {
                    $this->flushListItems($listItems, $listType, $blocks);
                }
                $listType = 'ordered';
                $listItems[] = $this->renderInlineContent(trim((string) $matches[1]));
                continue;
            }

            if (preg_match('/^>\s?(.*)$/', $trimmed, $matches)) {
                $this->flushParagraphLines($paragraphLines, $blocks);
                $this->flushListItems($listItems, $listType, $blocks);
                $quoteLines[] = trim((string) $matches[1]);
                continue;
            }

            if ($quoteLines !== []) {
                $this->flushQuoteLines($quoteLines, $blocks);
            }

            $paragraphLines[] = trim($raw);
        }

        if ($inCode) {
            $blocks[] = [
                'type' => 'code',
                'data' => ['code' => implode("\n", $codeLines)],
            ];
        }

        $this->flushParagraphLines($paragraphLines, $blocks);
        $this->flushListItems($listItems, $listType, $blocks);
        $this->flushQuoteLines($quoteLines, $blocks);

        if ($blocks === []) {
            $text = htmlspecialchars(trim($normalized), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5);
            $blocks[] = [
                'type' => 'paragraph',
                'data' => ['text' => $text],
            ];
        }

        return ['blocks' => $blocks];
    }

    /**
     * @param list<string> $paragraphLines
     * @param list<array<string,mixed>> $blocks
     */
    private function flushParagraphLines(array &$paragraphLines, array &$blocks): void
    {
        if ($paragraphLines === []) {
            return;
        }

        $text = implode("\n", $paragraphLines);
        $text = $this->renderInlineContent($text, true);
        $blocks[] = [
            'type' => 'paragraph',
            'data' => ['text' => $text],
        ];
        $paragraphLines = [];
    }

    /**
     * @param list<string> $listItems
     * @param 'ordered'|'unordered'|null $listType
     * @param list<array<string,mixed>> $blocks
     */
    private function flushListItems(array &$listItems, ?string &$listType, array &$blocks): void
    {
        if ($listItems === []) {
            return;
        }

        $blocks[] = [
            'type' => 'list',
            'data' => [
                'style' => $listType === 'ordered' ? 'ordered' : 'unordered',
                'items' => $listItems,
            ],
        ];
        $listItems = [];
        $listType = null;
    }

    /**
     * @param list<string> $quoteLines
     * @param list<array<string,mixed>> $blocks
     */
    private function flushQuoteLines(array &$quoteLines, array &$blocks): void
    {
        if ($quoteLines === []) {
            return;
        }

        $text = implode("\n", $quoteLines);
        $text = $this->renderInlineContent($text, true);
        $blocks[] = [
            'type' => 'quote',
            'data' => ['text' => $text],
        ];
        $quoteLines = [];
    }

    private function renderInlineContent(
        string $text,
        bool $preserveLineBreaks = false,
        bool $allowLinks = true
    ): string {
        $html = $this->parseInlineMarkdown($text, $allowLinks);

        if ($preserveLineBreaks) {
            $html = str_replace("\n", '<br>', $html);
        }

        return $html;
    }

    private function parseInlineMarkdown(string $text, bool $allowLinks = true): string
    {
        if ($text === '') {
            return '';
        }

        $codePlaceholders = [];
        $processed = preg_replace_callback(
            '/`([^`]+)`/',
            function (array $matches) use (&$codePlaceholders): string {
                $key = sprintf('<<CODE-%d>>', count($codePlaceholders));
                $codePlaceholders[$key] = sprintf(
                    '<code>%s</code>',
                    htmlspecialchars($matches[1], ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5)
                );

                return $key;
            },
            $text
        );

        if (!is_string($processed)) {
            $processed = $text;
        }

        $linkPlaceholders = [];
        if ($allowLinks) {
            $processed = preg_replace_callback(
                '/\[(.*?)\]\((.*?)\)/',
                function (array $matches) use (&$linkPlaceholders): string {
                    $href = $this->resolveMarkdownLink((string) $matches[2]);
                    if ($href === null) {
                        return (string) $matches[1];
                    }

                    $label = $this->parseInlineMarkdown((string) $matches[1], false);
                    $key = sprintf('<<LINK-%d>>', count($linkPlaceholders));
                    $linkPlaceholders[$key] = sprintf(
                        '<a href="%s">%s</a>',
                        htmlspecialchars($href, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5),
                        $label
                    );

                    return $key;
                },
                $processed
            );

            if (!is_string($processed)) {
                $processed = $text;
            }
        }

        $escaped = htmlspecialchars($processed, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5);

        $patterns = [
            '/(?<!\*)\*\*(?!\s)(.+?)(?<!\s)\*\*(?!\*)/s' => '<strong>$1</strong>',
            '/(?<!_)__(?!\s)(.+?)(?<!\s)__(?!_)/s' => '<strong>$1</strong>',
            '/(?<!\*)\*(?!\s)(.+?)(?<!\s)\*(?!\*)/s' => '<em>$1</em>',
            '/(?<!_)_(?!\s)(.+?)(?<!\s)_(?!_)/s' => '<em>$1</em>',
            '/(?<!~)~~(?!\s)(.+?)(?<!\s)~~(?!~)/s' => '<del>$1</del>',
        ];

        foreach ($patterns as $pattern => $replacement) {
            $escaped = preg_replace($pattern, $replacement, $escaped) ?? $escaped;
        }

        foreach ($codePlaceholders as $placeholder => $html) {
            $escaped = str_replace(
                htmlspecialchars($placeholder, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5),
                $html,
                $escaped
            );
        }

        foreach ($linkPlaceholders as $placeholder => $html) {
            $escaped = str_replace(
                htmlspecialchars($placeholder, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5),
                $html,
                $escaped
            );
        }

        return $escaped;
    }

    private function resolveMarkdownLink(string $rawUrl): ?string
    {
        $href = trim($rawUrl);
        if ($href === '') {
            return null;
        }

        if (str_starts_with($href, '#') || str_starts_with($href, '/')) {
            return $href;
        }

        if (str_starts_with($href, './') || str_starts_with($href, '../')) {
            return $href;
        }

        if (preg_match('/^[a-zA-Z][a-zA-Z0-9+.-]*:/', $href, $matches) === 1) {
            $scheme = strtolower(rtrim($matches[0], ':'));
            if (!in_array($scheme, ['http', 'https', 'mailto'], true)) {
                return null;
            }

            return $href;
        }

        if (preg_match('/^\/\//', $href) === 1) {
            return null;
        }

        if (preg_match('/\s/', $href) === 1) {
            return null;
        }

        return $href;
    }
}
