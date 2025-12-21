<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Application\Seo\PageSeoConfigService;
use App\Domain\LandingNews;
use App\Domain\MarketingPageWikiArticle;
use App\Domain\Page;
use App\Infrastructure\Database;
use App\Service\ConfigService;
use App\Service\LandingMediaReferenceService;
use App\Service\LandingNewsService;
use App\Service\MarketingNewsletterConfigService;
use App\Service\MarketingPageWikiArticleService;
use App\Service\PageService;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ProjectController
{
    private PageService $pageService;
    private MarketingNewsletterConfigService $newsletterService;
    private MarketingPageWikiArticleService $wikiService;
    private LandingNewsService $landingNewsService;
    private LandingMediaReferenceService $mediaReferenceService;

    public function __construct(
        ?PDO $pdo = null,
        ?PageService $pageService = null,
        ?MarketingNewsletterConfigService $newsletterService = null,
        ?MarketingPageWikiArticleService $wikiService = null,
        ?LandingNewsService $landingNewsService = null,
        ?LandingMediaReferenceService $mediaReferenceService = null
    ) {
        $pdo = $pdo ?? Database::connectFromEnv();
        $this->pageService = $pageService ?? new PageService($pdo);
        $this->newsletterService = $newsletterService ?? new MarketingNewsletterConfigService($pdo);
        $this->wikiService = $wikiService ?? new MarketingPageWikiArticleService($pdo);
        $this->landingNewsService = $landingNewsService ?? new LandingNewsService($pdo);
        $this->mediaReferenceService = $mediaReferenceService ?? new LandingMediaReferenceService(
            $this->pageService,
            new PageSeoConfigService($pdo),
            new ConfigService($pdo),
            $this->landingNewsService
        );
    }

    /**
     * Return structured project content data grouped by namespace for admin UI use.
     */
    public function tree(Request $request, Response $response): Response
    {
        $pageTree = $this->pageService->getTree();
        $treeByNamespace = [];
        $knownNamespaces = [];
        foreach ($pageTree as $section) {
            $namespace = $this->normalizeNamespace((string) ($section['namespace'] ?? ''));
            $treeByNamespace[$namespace] = $section['pages'] ?? [];
            $knownNamespaces[$namespace] = true;
        }

        $pagesByNamespace = [];
        $pages = $this->pageService->getAll();
        foreach ($pages as $page) {
            $namespace = $this->normalizeNamespace($page->getNamespace());
            if (!isset($pagesByNamespace[$namespace])) {
                $pagesByNamespace[$namespace] = [];
            }
            $pagesByNamespace[$namespace][] = $page;
            $knownNamespaces[$namespace] = true;
        }

        foreach ($this->newsletterService->getNamespaces() as $namespace) {
            $knownNamespaces[$this->normalizeNamespace($namespace)] = true;
        }

        $namespaces = array_keys($knownNamespaces);
        sort($namespaces);

        $payload = [];
        foreach ($namespaces as $namespace) {
            $namespacePages = $pagesByNamespace[$namespace] ?? [];
            $payload[] = [
                'namespace' => $namespace,
                'pages' => array_values($treeByNamespace[$namespace] ?? []),
                'wiki' => $this->buildWikiEntries($namespacePages),
                'landingNews' => $this->buildLandingNewsEntries($namespacePages),
                'newsletterSlugs' => $this->buildNewsletterSlugs($namespace),
                'mediaReferences' => $this->buildMediaReferences($namespace),
            ];
        }

        $response->getBody()->write(json_encode(['namespaces' => $payload], JSON_PRETTY_PRINT));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * @param Page[] $pages
     *
     * @return list<array{page:array{id:int,slug:string,title:string},articles:list<array{id:int,slug:string,title:string,locale:string,status:string,isStartDocument:bool}>}>
     */
    private function buildWikiEntries(array $pages): array
    {
        $entries = [];
        foreach ($pages as $page) {
            $articles = $this->wikiService->getArticlesForPage($page->getId());
            if ($articles === []) {
                continue;
            }

            $entries[] = [
                'page' => $this->mapPage($page),
                'articles' => array_map([$this, 'mapWikiArticle'], $articles),
            ];
        }

        return $entries;
    }

    /**
     * @param Page[] $pages
     *
     * @return list<array{page:array{id:int,slug:string,title:string},items:list<array{id:int,slug:string,title:string,isPublished:bool,publishedAt:?string}>}>
     */
    private function buildLandingNewsEntries(array $pages): array
    {
        $entries = [];
        foreach ($pages as $page) {
            $newsItems = $this->landingNewsService->getAll($page->getId());
            if ($newsItems === []) {
                continue;
            }

            $entries[] = [
                'page' => $this->mapPage($page),
                'items' => array_map([$this, 'mapLandingNews'], $newsItems),
            ];
        }

        return $entries;
    }

    /**
     * @return list<string>
     */
    private function buildNewsletterSlugs(string $namespace): array
    {
        $newsletterConfigs = $this->newsletterService->getAllGrouped($namespace);
        $slugs = array_keys($newsletterConfigs);
        sort($slugs);

        return array_values($slugs);
    }

    /**
     * @return array{files:list<array{path:string,count:int,references:list<array<string,mixed>>}>,missing:list<array<string,mixed>>}
     */
    private function buildMediaReferences(string $namespace): array
    {
        $references = $this->mediaReferenceService->collect($namespace);
        $files = [];
        foreach (($references['files'] ?? []) as $path => $items) {
            if (!is_array($items)) {
                continue;
            }
            $files[] = [
                'path' => (string) $path,
                'count' => count($items),
                'references' => array_values($items),
            ];
        }
        usort($files, static fn (array $left, array $right): int => strcmp($left['path'], $right['path']));

        return [
            'files' => $files,
            'missing' => array_values($references['missing'] ?? []),
        ];
    }

    /**
     * @return array{id:int,slug:string,title:string}
     */
    private function mapPage(Page $page): array
    {
        return [
            'id' => $page->getId(),
            'slug' => $page->getSlug(),
            'title' => $page->getTitle(),
        ];
    }

    /**
     * @return array{id:int,slug:string,title:string,locale:string,status:string,isStartDocument:bool}
     */
    private function mapWikiArticle(MarketingPageWikiArticle $article): array
    {
        return [
            'id' => $article->getId(),
            'slug' => $article->getSlug(),
            'title' => $article->getTitle(),
            'locale' => $article->getLocale(),
            'status' => $article->getStatus(),
            'isStartDocument' => $article->isStartDocument(),
        ];
    }

    /**
     * @return array{id:int,slug:string,title:string,isPublished:bool,publishedAt:?string}
     */
    private function mapLandingNews(LandingNews $news): array
    {
        return [
            'id' => $news->getId(),
            'slug' => $news->getSlug(),
            'title' => $news->getTitle(),
            'isPublished' => $news->isPublished(),
            'publishedAt' => $news->getPublishedAt()?->format(DATE_ATOM),
        ];
    }

    private function normalizeNamespace(string $namespace): string
    {
        $normalized = trim($namespace);
        return $normalized !== '' ? $normalized : PageService::DEFAULT_NAMESPACE;
    }
}
