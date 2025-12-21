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
use App\Support\BasePathHelper;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;

use function http_build_query;
use function is_array;
use function rawurlencode;

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
        $basePath = BasePathHelper::normalize(RouteContext::fromRequest($request)->getBasePath());
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

        $namespaces = array_keys($knownNamespaces);
        sort($namespaces);

        $payload = [];
        foreach ($namespaces as $namespace) {
            $namespacePages = $pagesByNamespace[$namespace] ?? [];
            $payload[] = [
                'namespace' => $namespace,
                'pages' => $this->mapTreeNodes($treeByNamespace[$namespace] ?? [], $basePath, $namespace),
                'wiki' => $this->buildWikiEntries($namespacePages, $basePath, $namespace),
                'landingNews' => $this->buildLandingNewsEntries($namespacePages, $basePath, $namespace),
                'newsletterSlugs' => $this->buildNewsletterSlugs($namespace, $basePath),
                'mediaReferences' => $this->buildMediaReferences($namespace),
            ];
        }

        $response->getBody()->write(json_encode(['namespaces' => $payload], JSON_PRETTY_PRINT));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * @param Page[] $pages
     *
     * @return list<array{page:array{id:int,slug:string,title:string,editUrl:?string},articles:list<array{id:int,slug:string,title:string,locale:string,status:string,isStartDocument:bool,editUrl:?string}>}>
     */
    private function buildWikiEntries(array $pages, string $basePath, string $namespace): array
    {
        $entries = [];
        foreach ($pages as $page) {
            $articles = $this->wikiService->getArticlesForPage($page->getId());
            if ($articles === []) {
                continue;
            }

            $entries[] = [
                'page' => $this->mapPage($page, $basePath, $namespace),
                'articles' => array_map(
                    fn (MarketingPageWikiArticle $article): array => $this->mapWikiArticle(
                        $article,
                        $basePath,
                        $namespace,
                        $page->getId()
                    ),
                    $articles
                ),
            ];
        }

        return $entries;
    }

    /**
     * @param Page[] $pages
     *
     * @return list<array{page:array{id:int,slug:string,title:string,editUrl:?string},items:list<array{id:int,slug:string,title:string,isPublished:bool,publishedAt:?string,editUrl:?string}>}>
     */
    private function buildLandingNewsEntries(array $pages, string $basePath, string $namespace): array
    {
        $entries = [];
        foreach ($pages as $page) {
            $newsItems = $this->landingNewsService->getAll($page->getId());
            if ($newsItems === []) {
                continue;
            }

            $entries[] = [
                'page' => $this->mapPage($page, $basePath, $namespace),
                'items' => array_map(
                    fn (LandingNews $news): array => $this->mapLandingNews($news, $basePath, $namespace),
                    $newsItems
                ),
            ];
        }

        return $entries;
    }

    /**
     * @return list<array{slug:string,editUrl:string}>
     */
    private function buildNewsletterSlugs(string $namespace, string $basePath): array
    {
        $newsletterConfigs = $this->newsletterService->getAllGrouped($namespace);
        $slugs = array_keys($newsletterConfigs);
        sort($slugs);

        return array_values(array_map(
            fn (string $slug): array => [
                'slug' => $slug,
                'editUrl' => $this->buildAdminUrl(
                    $basePath,
                    '/admin/management',
                    $namespace,
                    [],
                    'marketingNewsletterConfigSection'
                ),
            ],
            $slugs
        ));
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
     * @return array{id:int,slug:string,title:string,editUrl:?string}
     */
    private function mapPage(Page $page, string $basePath, string $namespace): array
    {
        $slug = $page->getSlug();
        $editUrl = $slug !== ''
            ? $this->buildAdminUrl(
                $basePath,
                '/admin/pages/' . rawurlencode($slug),
                $namespace
            )
            : null;

        return [
            'id' => $page->getId(),
            'slug' => $slug,
            'title' => $page->getTitle(),
            'editUrl' => $editUrl,
        ];
    }

    /**
     * @return array{id:int,slug:string,title:string,locale:string,status:string,isStartDocument:bool,editUrl:?string}
     */
    private function mapWikiArticle(
        MarketingPageWikiArticle $article,
        string $basePath,
        string $namespace,
        int $pageId
    ): array {
        $editUrl = $this->buildAdminUrl(
            $basePath,
            '/admin/pages',
            $namespace,
            [
                'pageTab' => 'wiki',
                'wikiPageId' => $pageId,
                'wikiArticleId' => $article->getId(),
            ]
        );

        return [
            'id' => $article->getId(),
            'slug' => $article->getSlug(),
            'title' => $article->getTitle(),
            'locale' => $article->getLocale(),
            'status' => $article->getStatus(),
            'isStartDocument' => $article->isStartDocument(),
            'editUrl' => $editUrl,
        ];
    }

    /**
     * @return array{id:int,slug:string,title:string,isPublished:bool,publishedAt:?string,editUrl:?string}
     */
    private function mapLandingNews(LandingNews $news, string $basePath, string $namespace): array
    {
        return [
            'id' => $news->getId(),
            'slug' => $news->getSlug(),
            'title' => $news->getTitle(),
            'isPublished' => $news->isPublished(),
            'publishedAt' => $news->getPublishedAt()?->format(DATE_ATOM),
            'editUrl' => $this->buildAdminUrl(
                $basePath,
                '/admin/landing-news/' . $news->getId(),
                $namespace
            ),
        ];
    }

    /**
     * @param list<array<string, mixed>> $nodes
     *
     * @return list<array<string, mixed>>
     */
    private function mapTreeNodes(array $nodes, string $basePath, string $namespace): array
    {
        $mapped = [];
        foreach ($nodes as $node) {
            if (!is_array($node)) {
                continue;
            }
            $slug = isset($node['slug']) ? (string) $node['slug'] : '';
            $editUrl = $slug !== ''
                ? $this->buildAdminUrl(
                    $basePath,
                    '/admin/pages/' . rawurlencode($slug),
                    $namespace
                )
                : null;
            $children = [];
            if (isset($node['children']) && is_array($node['children'])) {
                $children = $this->mapTreeNodes($node['children'], $basePath, $namespace);
            }
            $mappedNode = $node;
            $mappedNode['editUrl'] = $editUrl;
            $mappedNode['children'] = $children;
            $mapped[] = $mappedNode;
        }

        return $mapped;
    }

    /**
     * @param array<string, string|int> $query
     */
    private function buildAdminUrl(
        string $basePath,
        string $path,
        string $namespace,
        array $query = [],
        string $fragment = ''
    ): string {
        $params = $query;
        if ($namespace !== '') {
            $params = array_merge(['namespace' => $namespace], $params);
        }
        $url = $basePath . $path;
        if ($params !== []) {
            $url .= '?' . http_build_query($params);
        }
        if ($fragment !== '') {
            $url .= '#' . ltrim($fragment, '#');
        }

        return $url;
    }

    private function normalizeNamespace(string $namespace): string
    {
        $normalized = trim($namespace);
        return $normalized !== '' ? $normalized : PageService::DEFAULT_NAMESPACE;
    }
}
