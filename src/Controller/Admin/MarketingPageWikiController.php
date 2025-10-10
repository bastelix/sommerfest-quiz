<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Domain\MarketingPageWikiArticle;
use App\Service\MarketingPageWikiArticleService;
use App\Service\MarketingPageWikiSettingsService;
use App\Service\PageService;
use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
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
        $pageId = (int) ($args['pageId'] ?? 0);
        $body = $request->getParsedBody();
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
        $pageId = (int) ($args['pageId'] ?? 0);
        $body = $request->getParsedBody();
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
                $sortIndex
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
        $articleId = (int) ($args['articleId'] ?? 0);
        $body = $request->getParsedBody();
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

    public function showArticle(Request $request, Response $response, array $args): Response
    {
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
        $articleId = (int) ($args['articleId'] ?? 0);
        try {
            $markdown = $this->articleService->exportMarkdown($articleId);
        } catch (RuntimeException $exception) {
            $response->getBody()->write($exception->getMessage());

            return $response->withStatus(404);
        }

        $response->getBody()->write($markdown);

        return $response
            ->withHeader('Content-Type', 'text/markdown; charset=utf-8')
            ->withHeader('Content-Disposition', 'attachment; filename="article.md"');
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $articleId = (int) ($args['articleId'] ?? 0);
        $this->articleService->deleteArticle($articleId);

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
}
