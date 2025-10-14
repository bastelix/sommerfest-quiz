<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Domain\MarketingPageWikiArticle;
use App\Service\MarketingPageWikiArticleService;
use App\Service\MarketingPageWikiSettingsService;
use App\Service\PageService;
use App\Support\FeatureFlags;
use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;

final class MarketingPageWikiController
{
    private MarketingPageWikiSettingsService $settingsService;

    private MarketingPageWikiArticleService $articleService;

    private PageService $pageService;

    public function __construct(
        ?MarketingPageWikiSettingsService $settingsService = null,
        ?MarketingPageWikiArticleService $articleService = null,
        ?PageService $pageService = null
    ) {
        $this->settingsService = $settingsService ?? new MarketingPageWikiSettingsService();
        $this->articleService = $articleService ?? new MarketingPageWikiArticleService();
        $this->pageService = $pageService ?? new PageService();
    }

    public function index(Request $request, Response $response, array $args): Response
    {
        if (!FeatureFlags::wikiEnabled()) {
            return $response->withStatus(404);
        }

        $pageId = (int) ($args['pageId'] ?? 0);
        $page = $this->pageService->findById($pageId);
        if ($page === null) {
            return $response->withStatus(404);
        }

        $settings = $this->settingsService->getSettingsForPage($pageId);
        $articles = $this->articleService->getArticlesForPage($pageId);

        $payload = [
            'page' => [
                'id' => $page->getId(),
                'slug' => $page->getSlug(),
                'title' => $page->getTitle(),
            ],
            'settings' => [
                'active' => $settings->isActive(),
                'menuLabel' => $settings->getMenuLabel(),
                'updatedAt' => $settings->getUpdatedAt()?->format(DateTimeImmutable::ATOM),
            ],
            'articles' => array_map(static fn (MarketingPageWikiArticle $article): array => $article->jsonSerialize(), $articles),
        ];

        $response->getBody()->write(json_encode($payload));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function updateSettings(Request $request, Response $response, array $args): Response
    {
        if (!FeatureFlags::wikiEnabled()) {
            return $response->withStatus(404);
        }

        $pageId = (int) ($args['pageId'] ?? 0);

        $body = $request->getParsedBody();
        if ($request->getHeaderLine('Content-Type') === 'application/json') {
            $body = json_decode((string) $request->getBody(), true);
        }
        if (!is_array($body)) {
            return $response->withStatus(400);
        }

        $isActive = isset($body['active']) ? (bool) $body['active'] : false;
        $menuLabel = isset($body['menuLabel']) ? (string) $body['menuLabel'] : null;

        $settings = $this->settingsService->updateSettings($pageId, $isActive, $menuLabel);
        $payload = [
            'active' => $settings->isActive(),
            'menuLabel' => $settings->getMenuLabel(),
            'updatedAt' => $settings->getUpdatedAt()?->format(DateTimeImmutable::ATOM),
        ];

        $response->getBody()->write(json_encode($payload));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function saveArticle(Request $request, Response $response, array $args): Response
    {
        if (!FeatureFlags::wikiEnabled()) {
            return $response->withStatus(404);
        }

        $pageId = (int) ($args['pageId'] ?? 0);
        $contentType = strtolower($request->getHeaderLine('Content-Type'));
        if (str_contains($contentType, 'multipart/form-data')) {
            return $this->saveUploadedArticle($request, $response, $pageId);
        }

        $body = $request->getParsedBody();
        if ($request->getHeaderLine('Content-Type') === 'application/json') {
            $body = json_decode((string) $request->getBody(), true);
        }
        if (!is_array($body)) {
            return $response->withStatus(400);
        }

        $articleId = isset($body['id']) ? (int) $body['id'] : null;
        $locale = (string) ($body['locale'] ?? 'de');
        $slug = (string) ($body['slug'] ?? '');
        $title = (string) ($body['title'] ?? '');
        $excerpt = isset($body['excerpt']) ? (string) $body['excerpt'] : null;
        $status = isset($body['status']) ? (string) $body['status'] : MarketingPageWikiArticle::STATUS_DRAFT;
        $sortIndex = isset($body['sortIndex']) ? (int) $body['sortIndex'] : null;
        $isStartDocument = $this->normalizeBoolean($body['isStartDocument'] ?? false);
        $editorState = $this->decodeEditorState($body['editor'] ?? $body['editorState'] ?? null);

        try {
            $article = $this->articleService->saveArticle(
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
        } catch (RuntimeException $exception) {
            $response->getBody()->write(json_encode(['error' => $exception->getMessage()]));

            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $response->getBody()->write(json_encode($article->jsonSerialize()));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function updateStatus(Request $request, Response $response, array $args): Response
    {
        if (!FeatureFlags::wikiEnabled()) {
            return $response->withStatus(404);
        }

        $articleId = (int) ($args['articleId'] ?? 0);
        $body = $request->getParsedBody();
        if ($request->getHeaderLine('Content-Type') === 'application/json') {
            $body = json_decode((string) $request->getBody(), true);
        }
        if (!is_array($body)) {
            return $response->withStatus(400);
        }

        $status = isset($body['status']) ? (string) $body['status'] : MarketingPageWikiArticle::STATUS_DRAFT;
        try {
            $article = $this->articleService->updateStatus($articleId, $status);
        } catch (RuntimeException $exception) {
            $response->getBody()->write(json_encode(['error' => $exception->getMessage()]));

            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $response->getBody()->write(json_encode($article->jsonSerialize()));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function updateStartDocument(Request $request, Response $response, array $args): Response
    {
        if (!FeatureFlags::wikiEnabled()) {
            return $response->withStatus(404);
        }

        $articleId = (int) ($args['articleId'] ?? 0);
        $body = $request->getParsedBody();
        if ($request->getHeaderLine('Content-Type') === 'application/json') {
            $body = json_decode((string) $request->getBody(), true);
        }
        if (!is_array($body)) {
            return $response->withStatus(400);
        }

        $isStartDocument = $this->normalizeBoolean($body['isStartDocument'] ?? false);

        try {
            $article = $this->articleService->setStartDocument($articleId, $isStartDocument);
        } catch (RuntimeException $exception) {
            $response->getBody()->write(json_encode(['error' => $exception->getMessage()]));

            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $response->getBody()->write(json_encode($article->jsonSerialize()));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function showArticle(Request $request, Response $response, array $args): Response
    {
        if (!FeatureFlags::wikiEnabled()) {
            return $response->withStatus(404);
        }

        $articleId = (int) ($args['articleId'] ?? 0);
        $article = $this->articleService->getArticleById($articleId);
        if ($article === null) {
            return $response->withStatus(404);
        }

        $response->getBody()->write(json_encode($article->jsonSerialize()));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function download(Request $request, Response $response, array $args): Response
    {
        if (!FeatureFlags::wikiEnabled()) {
            return $response->withStatus(404);
        }

        $articleId = (int) ($args['articleId'] ?? 0);
        $article = $this->articleService->getArticleById($articleId);
        if ($article === null) {
            return $response->withStatus(404);
        }

        try {
            $markdown = $this->articleService->exportMarkdown($articleId);
        } catch (RuntimeException $exception) {
            $response->getBody()->write($exception->getMessage());

            return $response->withStatus(404);
        }

        $response->getBody()->write($markdown);

        return $response
            ->withHeader('Content-Type', 'text/markdown; charset=utf-8')
            ->withHeader('Content-Disposition', sprintf('attachment; filename="%s.md"', $article->getSlug()));
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        if (!FeatureFlags::wikiEnabled()) {
            return $response->withStatus(404);
        }

        $articleId = (int) ($args['articleId'] ?? 0);
        $this->articleService->deleteArticle($articleId);

        return $response->withStatus(204);
    }

    private function saveUploadedArticle(Request $request, Response $response, int $pageId): Response
    {
        $uploads = $request->getUploadedFiles();
        $file = $uploads['markdown'] ?? $uploads['file'] ?? null;
        if (!$file instanceof UploadedFileInterface) {
            return $this->jsonError($response, 'Markdown-Datei fehlt.');
        }

        if ($file->getError() !== UPLOAD_ERR_OK) {
            return $this->jsonError($response, 'Upload fehlgeschlagen.');
        }

        $clientFilename = (string) $file->getClientFilename();
        $extension = strtolower((string) pathinfo($clientFilename, PATHINFO_EXTENSION));
        if (!in_array($extension, ['md', 'markdown'], true)) {
            return $this->jsonError($response, 'Nur Markdown-Dateien (.md) werden unterstützt.');
        }

        $stream = $file->getStream();
        if ($stream->isSeekable()) {
            $stream->rewind();
        }
        $markdown = trim((string) $stream->getContents());
        if ($markdown === '') {
            return $this->jsonError($response, 'Die Markdown-Datei ist leer.');
        }

        $body = $request->getParsedBody();
        if (!is_array($body)) {
            $body = [];
        }

        $locale = isset($body['locale']) && is_string($body['locale']) ? strtolower(trim($body['locale'])) : 'de';
        if ($locale === '') {
            $locale = 'de';
        }
        $status = isset($body['status']) && is_string($body['status']) ? trim($body['status']) : MarketingPageWikiArticle::STATUS_DRAFT;
        if (!in_array($status, [
            MarketingPageWikiArticle::STATUS_DRAFT,
            MarketingPageWikiArticle::STATUS_PUBLISHED,
            MarketingPageWikiArticle::STATUS_ARCHIVED,
        ], true)) {
            $status = MarketingPageWikiArticle::STATUS_DRAFT;
        }

        $isStartDocument = $this->normalizeBoolean($body['isStartDocument'] ?? false);

        $slug = isset($body['slug']) && is_string($body['slug']) ? trim($body['slug']) : '';
        if ($slug === '') {
            $slug = $this->slugify((string) pathinfo($clientFilename, PATHINFO_FILENAME));
        }
        if ($slug === '') {
            try {
                $slug = 'article-' . bin2hex(random_bytes(4));
            } catch (\Throwable $exception) {
                $slug = 'article-' . time();
            }
        }

        $title = isset($body['title']) && is_string($body['title']) ? trim($body['title']) : '';
        if ($title === '') {
            $extracted = $this->extractTitleFromMarkdown($markdown);
            $title = $extracted ?? $this->titleFromSlug($slug);
        }

        $excerpt = isset($body['excerpt']) && is_string($body['excerpt']) ? trim($body['excerpt']) : null;
        if ($excerpt === null || $excerpt === '') {
            $excerpt = $this->extractExcerptFromMarkdown($markdown);
        }

        try {
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
        } catch (RuntimeException $exception) {
            return $this->jsonError($response, $exception->getMessage());
        }

        $response->getBody()->write(json_encode($article->jsonSerialize()));

        return $response->withHeader('Content-Type', 'application/json');
    }

    private function jsonError(Response $response, string $message, int $status = 400): Response
    {
        $response->getBody()->write(json_encode(['error' => $message]));

        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }

    private function slugify(string $value): string
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

        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        $value = trim($value, '-');
        $value = preg_replace('/-+/', '-', $value) ?? '';

        return $value;
    }

    private function titleFromSlug(string $slug): string
    {
        $normalized = str_replace('-', ' ', $slug);
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? '';
        $normalized = trim($normalized);

        if ($normalized === '') {
            return 'Artikel';
        }

        return ucwords($normalized);
    }

    private function extractTitleFromMarkdown(string $markdown): ?string
    {
        $lines = preg_split('/\r\n|\n|\r/', $markdown) ?: [];
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }
            if (preg_match('/^#{1,6}\s+(.*)$/', $trimmed, $matches)) {
                $title = trim((string) $matches[1]);
                if ($title !== '') {
                    return $title;
                }
            }
        }

        return null;
    }

    private function extractExcerptFromMarkdown(string $markdown): ?string
    {
        $normalized = preg_replace('/\r\n?/', "\n", $markdown) ?? '';
        $segments = preg_split('/\n{2,}/', $normalized) ?: [];
        foreach ($segments as $segment) {
            $trimmed = trim($segment);
            if ($trimmed === '') {
                continue;
            }
            if (preg_match('/^#{1,6}\s+/', $trimmed)) {
                continue;
            }
            if (str_starts_with($trimmed, '```')) {
                continue;
            }
            if (preg_match('/^[-*]\s+/', $trimmed) || preg_match('/^\d+\.\s+/', $trimmed)) {
                continue;
            }
            if (preg_match('/^>\s?/', $trimmed)) {
                continue;
            }

            $plain = preg_replace('/!\[[^\]]*\]\([^)]*\)/', '', $trimmed) ?? '';
            $plain = preg_replace('/\[[^\]]*\]\([^)]*\)/', '$1', $plain) ?? '';
            $plain = preg_replace('/[*_`>#-]+/', ' ', $plain) ?? '';
            $plain = preg_replace('/\s+/', ' ', $plain) ?? '';
            $plain = trim($plain);
            if ($plain === '') {
                continue;
            }
            if (mb_strlen($plain) > 280) {
                $plain = rtrim(mb_substr($plain, 0, 280));
            }

            return $plain;
        }

        return null;
    }

    public function duplicate(Request $request, Response $response, array $args): Response
    {
        if (!FeatureFlags::wikiEnabled()) {
            return $response->withStatus(404);
        }

        $pageId = (int) ($args['pageId'] ?? 0);
        $articleId = (int) ($args['articleId'] ?? 0);
        $body = $request->getParsedBody();
        if ($request->getHeaderLine('Content-Type') === 'application/json') {
            $body = json_decode((string) $request->getBody(), true);
        }
        if (!is_array($body)) {
            $body = [];
        }

        $slug = isset($body['slug']) ? (string) $body['slug'] : null;
        if ($slug !== null && trim($slug) === '') {
            $slug = null;
        }

        $title = isset($body['title']) ? (string) $body['title'] : null;
        if ($title !== null && trim($title) === '') {
            $title = null;
        }

        try {
            $duplicate = $this->articleService->duplicateArticle($pageId, $articleId, $slug, $title);
        } catch (RuntimeException $exception) {
            $response->getBody()->write(json_encode(['error' => $exception->getMessage()]));

            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $response->getBody()->write(json_encode($duplicate->jsonSerialize()));

        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }

    public function sort(Request $request, Response $response, array $args): Response
    {
        if (!FeatureFlags::wikiEnabled()) {
            return $response->withStatus(404);
        }

        $pageId = (int) ($args['pageId'] ?? 0);
        $body = $request->getParsedBody();
        if ($request->getHeaderLine('Content-Type') === 'application/json') {
            $body = json_decode((string) $request->getBody(), true);
        }
        if (!is_array($body) || !isset($body['order']) || !is_array($body['order'])) {
            return $response->withStatus(400);
        }

        $articleIds = [];
        foreach ($body['order'] as $item) {
            if (is_array($item) && isset($item['id'])) {
                $articleIds[] = (int) $item['id'];
                continue;
            }

            if (is_numeric($item)) {
                $articleIds[] = (int) $item;
            }
        }

        if ($articleIds === []) {
            return $response->withStatus(400);
        }

        try {
            $this->articleService->reorderArticles($pageId, $articleIds);
        } catch (RuntimeException $exception) {
            $response->getBody()->write(json_encode(['error' => $exception->getMessage()]));

            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        return $response->withStatus(204);
    }

    /**
     * @return array<string,mixed>
     */
    private function decodeEditorState(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }

        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return ['blocks' => []];
    }

    private function normalizeBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }
            if (in_array($normalized, ['0', 'false', 'no', 'off', ''], true)) {
                return false;
            }
        }

        return (bool) $value;
    }
}
