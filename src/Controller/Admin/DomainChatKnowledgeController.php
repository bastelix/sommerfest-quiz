<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Domain\MarketingPageWikiArticle;
use App\Domain\Page;
use App\Service\MarketingPageWikiArticleService;
use App\Service\MarketingSlugResolver;
use App\Service\PageService;
use App\Service\RagChat\DomainDocumentStorage;
use App\Service\RagChat\DomainIndexManager;
use App\Service\RagChat\DomainWikiSelectionService;
use App\Support\DomainNameHelper;
use DateTimeImmutable;
use InvalidArgumentException;
use JsonException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;

use function array_flip;
use function ctype_digit;
use function is_array;
use function is_int;
use function is_string;
use function json_decode;
use function str_contains;
use function strtolower;
use function trim;

final class DomainChatKnowledgeController
{
    private DomainDocumentStorage $storage;

    private DomainIndexManager $indexManager;

    private ?DomainWikiSelectionService $wikiSelection;

    private ?MarketingPageWikiArticleService $wikiArticles;

    private ?PageService $pageService;

    public function __construct(
        DomainDocumentStorage $storage,
        DomainIndexManager $indexManager,
        ?DomainWikiSelectionService $wikiSelection = null,
        ?MarketingPageWikiArticleService $wikiArticles = null,
        ?PageService $pageService = null
    ) {
        $this->storage = $storage;
        $this->indexManager = $indexManager;
        $this->wikiSelection = $wikiSelection;
        $this->wikiArticles = $wikiArticles;
        $this->pageService = $pageService;
    }

    public function list(Request $request, Response $response): Response
    {
        try {
            $domain = $this->extractDomain($request);
        } catch (InvalidArgumentException $exception) {
            return $this->json($response, ['error' => $exception->getMessage()], 400);
        }

        $documents = $this->storage->listDocuments($domain);

        return $this->json($response, [
            'domain' => $domain,
            'documents' => $documents,
            'wiki' => $this->buildWikiPayload($domain),
        ]);
    }

    public function upload(Request $request, Response $response): Response
    {
        try {
            $domain = $this->extractDomain($request);
        } catch (InvalidArgumentException $exception) {
            return $this->json($response, ['error' => $exception->getMessage()], 400);
        }

        $files = $request->getUploadedFiles();
        $file = $files['document'] ?? null;
        if (!$file instanceof UploadedFileInterface) {
            return $this->json($response, ['error' => 'missing-file'], 400);
        }

        try {
            $document = $this->storage->storeDocument($domain, $file);
        } catch (InvalidArgumentException $exception) {
            return $this->json($response, ['error' => $exception->getMessage()], 422);
        } catch (RuntimeException $exception) {
            return $this->json($response, ['error' => $exception->getMessage()], 500);
        }

        return $this->json($response, ['document' => $document], 201);
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        try {
            $domain = $this->extractDomain($request);
        } catch (InvalidArgumentException $exception) {
            return $this->json($response, ['error' => $exception->getMessage()], 400);
        }

        $id = isset($args['id']) ? (string) $args['id'] : '';
        if ($id === '') {
            return $this->json($response, ['error' => 'invalid-document'], 400);
        }

        try {
            $this->storage->deleteDocument($domain, $id);
        } catch (RuntimeException | InvalidArgumentException $exception) {
            return $this->json($response, ['error' => $exception->getMessage()], 404);
        }

        return $this->json($response, ['success' => true]);
    }

    public function download(Request $request, Response $response): Response
    {
        try {
            $domain = $this->extractDomain($request);
        } catch (InvalidArgumentException $exception) {
            return $this->json($response, ['error' => $exception->getMessage()], 400);
        }

        $indexPath = $this->storage->getIndexPath($domain);
        if (!is_file($indexPath)) {
            return $this->json($response, ['error' => 'index-not-found'], 404);
        }

        $contents = file_get_contents($indexPath);
        if ($contents === false) {
            return $this->json($response, ['error' => 'Failed to read index file.'], 500);
        }

        $safeDomain = preg_replace('/[^a-z0-9._-]+/i', '-', $domain) ?? $domain;
        $filename = sprintf('domain-index-%s.json', $safeDomain);

        $response->getBody()->write($contents);

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Content-Disposition', sprintf('attachment; filename="%s"', $filename));
    }

    public function rebuild(Request $request, Response $response): Response
    {
        try {
            $domain = $this->extractDomain($request);
        } catch (InvalidArgumentException $exception) {
            return $this->json($response, ['error' => $exception->getMessage()], 400);
        }

        try {
            $result = $this->indexManager->rebuild($domain);
        } catch (RuntimeException $exception) {
            $message = trim($exception->getMessage());
            if ($message === '') {
                $message = 'Domain index rebuild failed.';
            }

            return $this->json($response, ['error' => $message], 422);
        }

        return $this->json($response, $result);
    }

    public function updateWikiSelection(Request $request, Response $response): Response
    {
        if ($this->wikiSelection === null || $this->wikiArticles === null || $this->pageService === null) {
            return $this->json($response, ['error' => 'wiki-selection-unavailable'], 503);
        }

        $body = $this->parseRequestBody($request);

        try {
            $domain = $this->extractDomain($request, $body);
        } catch (InvalidArgumentException $exception) {
            return $this->json($response, ['error' => $exception->getMessage()], 400);
        }

        $articles = $body['articles'] ?? $body['articleIds'] ?? null;
        if (!is_array($articles)) {
            return $this->json($response, ['error' => 'invalid-payload'], 400);
        }

        $page = $this->resolveWikiPage($domain);
        if ($page === null) {
            if ($articles !== []) {
                return $this->json($response, ['error' => 'wiki-not-available'], 422);
            }

            $this->wikiSelection->clearSelection($domain);

            return $this->json($response, [
                'success' => true,
                'wiki' => $this->buildWikiPayload($domain),
            ]);
        }

        $pageId = $page->getId();
        $validIds = [];
        foreach ($articles as $raw) {
            if (is_int($raw)) {
                $articleId = $raw;
            } elseif (is_string($raw) && ctype_digit($raw)) {
                $articleId = (int) $raw;
            } else {
                return $this->json($response, ['error' => 'invalid-articles'], 422);
            }

            if ($articleId <= 0) {
                return $this->json($response, ['error' => 'invalid-articles'], 422);
            }

            $article = $this->wikiArticles->getArticleById($articleId);
            if ($article === null || $article->getPageId() !== $pageId || !$article->isPublished()) {
                return $this->json($response, ['error' => 'article-unavailable'], 422);
            }

            $validIds[] = $articleId;
        }

        try {
            $this->wikiSelection->replaceSelection($domain, $validIds);
        } catch (InvalidArgumentException $exception) {
            return $this->json($response, ['error' => $exception->getMessage()], 422);
        } catch (RuntimeException $exception) {
            return $this->json($response, ['error' => $exception->getMessage()], 500);
        }

        return $this->json($response, [
            'success' => true,
            'wiki' => $this->buildWikiPayload($domain),
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function buildWikiPayload(string $domain): array
    {
        if ($this->wikiSelection === null || $this->wikiArticles === null || $this->pageService === null) {
            return [
                'enabled' => false,
                'available' => false,
                'articles' => [],
                'pageSlug' => null,
            ];
        }

        $page = $this->resolveWikiPage($domain);
        if ($page === null) {
            return [
                'enabled' => true,
                'available' => false,
                'articles' => [],
                'pageSlug' => null,
            ];
        }

        $selectedIds = $this->wikiSelection->getSelectedArticleIds($domain);
        $selectedLookup = $selectedIds !== [] ? array_flip($selectedIds) : [];

        /** @var list<MarketingPageWikiArticle> $articles */
        $articles = $this->wikiArticles->getArticlesForPage($page->getId());

        $entries = [];
        foreach ($articles as $article) {
            if (!$article->isPublished()) {
                continue;
            }

            $entries[] = [
                'id' => $article->getId(),
                'title' => $article->getTitle(),
                'slug' => $article->getSlug(),
                'locale' => $article->getLocale(),
                'excerpt' => $article->getExcerpt(),
                'publishedAt' => $article->getPublishedAt()?->format(DateTimeImmutable::ATOM),
                'isStartDocument' => $article->isStartDocument(),
                'selected' => isset($selectedLookup[$article->getId()]),
            ];
        }

        return [
            'enabled' => true,
            'available' => true,
            'pageSlug' => $page->getSlug(),
            'articles' => $entries,
        ];
    }

    private function resolveWikiPage(string $domain): ?Page
    {
        if ($this->pageService === null) {
            return null;
        }

        $page = $this->pageService->findByKey(PageService::DEFAULT_NAMESPACE, $domain);
        if ($page instanceof Page) {
            return $page;
        }

        $baseSlug = MarketingSlugResolver::resolveBaseSlug($domain);
        if ($baseSlug === $domain) {
            return null;
        }

        return $this->pageService->findByKey(PageService::DEFAULT_NAMESPACE, $baseSlug);
    }

    /**
     * @return array<string,mixed>
     */
    private function parseRequestBody(Request $request): array
    {
        $body = $request->getParsedBody();
        if (is_array($body)) {
            return $body;
        }

        $contentType = strtolower($request->getHeaderLine('Content-Type'));
        if (str_contains($contentType, 'application/json')) {
            $raw = (string) $request->getBody();
            if ($raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        return [];
    }

    private function extractDomain(Request $request, ?array $body = null): string
    {
        $domain = '';
        $params = $request->getQueryParams();
        if (isset($params['domain'])) {
            $domain = (string) $params['domain'];
        }

        if ($domain === '') {
            $bodyData = $body ?? $this->parseRequestBody($request);
            if (isset($bodyData['domain'])) {
                $domain = (string) $bodyData['domain'];
            }
        }

        $candidate = trim($domain);
        if ($candidate === '') {
            throw new InvalidArgumentException('Invalid domain parameter.');
        }

        if ($this->pageService !== null) {
            $page = $this->pageService->findByKey(PageService::DEFAULT_NAMESPACE, $candidate);
            if ($page instanceof Page) {
                return $page->getSlug();
            }
        }

        $normalized = DomainNameHelper::canonicalizeSlug($candidate);
        if ($normalized === '') {
            throw new InvalidArgumentException('Invalid domain parameter.');
        }

        return $normalized;
    }

    private function json(Response $response, array $payload, int $status = 200): Response
    {
        try {
            $response->getBody()->write(json_encode($payload, JSON_THROW_ON_ERROR));
        } catch (JsonException $exception) {
            throw new RuntimeException('Failed to encode response.', 0, $exception);
        }

        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json');
    }
}
