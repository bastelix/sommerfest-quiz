<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Application\Seo\PageSeoConfigService;
use App\Domain\LandingNews;
use App\Domain\MarketingPageWikiArticle;
use App\Domain\Page;
use App\Infrastructure\Database;
use App\Repository\NamespaceRepository;
use App\Service\ConfigService;
use App\Service\LandingMediaReferenceService;
use App\Service\LandingNewsService;
use App\Service\MarketingNewsletterConfigService;
use App\Service\MarketingPageWikiArticleService;
use App\Service\NamespaceResolver;
use App\Service\NamespaceValidator;
use App\Service\PageService;
use App\Service\ProjectSettingsService;
use App\Support\BasePathHelper;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;

use function http_build_query;
use function is_array;
use function json_decode;
use function json_encode;
use function rawurlencode;

class ProjectController
{
    private PageService $pageService;
    private MarketingNewsletterConfigService $newsletterService;
    private MarketingPageWikiArticleService $wikiService;
    private LandingNewsService $landingNewsService;
    private LandingMediaReferenceService $mediaReferenceService;
    private NamespaceRepository $namespaceRepository;
    private ProjectSettingsService $projectSettings;

    public function __construct(
        ?PDO $pdo = null,
        ?PageService $pageService = null,
        ?MarketingNewsletterConfigService $newsletterService = null,
        ?MarketingPageWikiArticleService $wikiService = null,
        ?LandingNewsService $landingNewsService = null,
        ?LandingMediaReferenceService $mediaReferenceService = null,
        ?NamespaceRepository $namespaceRepository = null,
        ?ProjectSettingsService $projectSettings = null
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
        $this->namespaceRepository = $namespaceRepository ?? new NamespaceRepository($pdo);
        $this->projectSettings = $projectSettings ?? new ProjectSettingsService($pdo);
    }

    /**
     * Render the project overview page for admin UI.
     */
    public function index(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        [$availableNamespaces, $namespace] = $this->loadNamespaces($request);
        $role = $_SESSION['user']['role'] ?? '';
        $cookieSettings = $this->projectSettings->getCookieConsentSettings($namespace);

        return $view->render($response, 'admin/projects.twig', [
            'role' => $role,
            'currentPath' => $request->getUri()->getPath(),
            'domainType' => $request->getAttribute('domainType'),
            'available_namespaces' => $availableNamespaces,
            'pageNamespace' => $namespace,
            'cookie_settings' => $cookieSettings,
            'csrf_token' => $this->ensureCsrfToken(),
        ]);
    }

    public function updateSettings(Request $request, Response $response): Response
    {
        $payload = json_decode((string) $request->getBody(), true);
        if (!is_array($payload)) {
            return $response->withStatus(400);
        }

        $validator = new NamespaceValidator();
        $namespace = $validator->normalizeCandidate((string) ($payload['namespace'] ?? ''));
        if ($namespace === null) {
            return $response->withStatus(400);
        }

        $enabled = filter_var($payload['cookieConsentEnabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $storageKey = isset($payload['cookieStorageKey']) ? (string) $payload['cookieStorageKey'] : null;
        $bannerText = isset($payload['cookieBannerText']) ? (string) $payload['cookieBannerText'] : null;

        $settings = $this->projectSettings->saveCookieConsentSettings($namespace, $enabled, $storageKey, $bannerText);

        $response->getBody()->write(json_encode([
            'namespace' => $namespace,
            'settings' => $settings,
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Return structured project content data grouped by namespace for admin UI use.
     */
    public function tree(Request $request, Response $response): Response
    {
        $basePath = BasePathHelper::normalize(RouteContext::fromRequest($request)->getBasePath());
        $queryParams = $request->getQueryParams();
        $requestedNamespace = '';
        if (isset($queryParams['namespace']) && trim((string) $queryParams['namespace']) !== '') {
            $requestedNamespace = $this->normalizeNamespace((string) $queryParams['namespace']);
        }
        $pageTree = $this->pageService->getTree();
        $treeByNamespace = [];
        $knownNamespaces = [];
        foreach ($pageTree as $section) {
            $namespace = $this->normalizeNamespace((string) $section['namespace']);
            if ($requestedNamespace !== '' && $namespace !== $requestedNamespace) {
                continue;
            }
            $treeByNamespace[$namespace] = $section['pages'] ?? [];
            $knownNamespaces[$namespace] = true;
        }

        $pagesByNamespace = [];
        $pages = $this->pageService->getAll();
        foreach ($pages as $page) {
            $namespace = $this->normalizeNamespace($page->getNamespace());
            if ($requestedNamespace !== '' && $namespace !== $requestedNamespace) {
                continue;
            }
            if (!isset($pagesByNamespace[$namespace])) {
                $pagesByNamespace[$namespace] = [];
            }
            $pagesByNamespace[$namespace][] = $page;
            $knownNamespaces[$namespace] = true;
        }

        foreach ($this->newsletterService->getNamespaces() as $namespace) {
            $normalizedNamespace = $this->normalizeNamespace($namespace);
            if ($requestedNamespace !== '' && $normalizedNamespace !== $requestedNamespace) {
                continue;
            }
            $knownNamespaces[$normalizedNamespace] = true;
        }

        $namespaceEntries = $this->namespaceRepository->list();
        foreach ($namespaceEntries as $namespace) {
            $normalizedNamespace = $this->normalizeNamespace((string) $namespace['namespace']);
            if ($requestedNamespace !== '' && $normalizedNamespace !== $requestedNamespace) {
                continue;
            }
            $knownNamespaces[$normalizedNamespace] = true;
        }

        $namespaces = $requestedNamespace !== '' ? [$requestedNamespace] : array_keys($knownNamespaces);
        sort($namespaces);

        $namespaceInfo = [];
        foreach ($namespaceEntries as $entry) {
            $entryNamespace = $this->normalizeNamespace((string) $entry['namespace']);
            if ($entryNamespace === '') {
                continue;
            }
            if ($requestedNamespace !== '' && $entryNamespace !== $requestedNamespace) {
                continue;
            }
            $namespaceInfo[$entryNamespace] = [
                'label' => $entry['label'] ?? null,
                'is_active' => (bool) $entry['is_active'],
                'is_default' => $entryNamespace === PageService::DEFAULT_NAMESPACE,
            ];
        }

        $payload = [];
        foreach ($namespaces as $namespace) {
            $namespacePages = $pagesByNamespace[$namespace] ?? [];
            $info = $namespaceInfo[$namespace] ?? [
                'label' => null,
                'is_active' => true,
                'is_default' => $namespace === PageService::DEFAULT_NAMESPACE,
            ];
            $payload[] = [
                'namespace' => $namespace,
                'namespaceInfo' => $info,
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
     * @return array{0: list<array<string, mixed>>, 1: string}
     */
    private function loadNamespaces(Request $request): array
    {
        $namespace = $this->normalizeNamespace((new NamespaceResolver())->resolve($request)->getNamespace());

        try {
            $availableNamespaces = $this->namespaceRepository->list();
        } catch (\RuntimeException $exception) {
            $availableNamespaces = [];
        }

        foreach ($availableNamespaces as $index => $entry) {
            $entry['namespace'] = $this->normalizeNamespace((string) $entry['namespace']);
            $availableNamespaces[$index] = $entry;
        }

        if (!array_filter(
            $availableNamespaces,
            static fn (array $entry): bool => $entry['namespace'] === PageService::DEFAULT_NAMESPACE
        )) {
            $availableNamespaces[] = [
                'namespace' => PageService::DEFAULT_NAMESPACE,
                'label' => null,
                'is_active' => true,
                'created_at' => null,
                'updated_at' => null,
            ];
        }

        $currentNamespaceExists = array_filter(
            $availableNamespaces,
            static fn (array $entry): bool => $entry['namespace'] === $namespace
        );
        if (!$currentNamespaceExists) {
            $availableNamespaces[] = [
                'namespace' => $namespace,
                'label' => 'nicht gespeichert',
                'is_active' => false,
                'created_at' => null,
                'updated_at' => null,
            ];
        }

        return [$availableNamespaces, $namespace];
    }

    private function ensureCsrfToken(): string
    {
        $token = $_SESSION['csrf_token'] ?? '';
        if ($token === '') {
            $token = bin2hex(random_bytes(16));
            $_SESSION['csrf_token'] = $token;
        }

        return $token;
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
        foreach ($references['files'] as $path => $items) {
            $files[] = [
                'path' => (string) $path,
                'count' => count($items),
                'references' => array_values($items),
            ];
        }
        usort($files, static fn (array $left, array $right): int => strcmp($left['path'], $right['path']));

        return [
            'files' => $files,
            'missing' => array_values($references['missing']),
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
                '/admin/pages/content',
                $namespace,
                ['pageSlug' => $slug]
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
            '/admin/pages/wiki',
            $namespace,
            [
                'pageId' => $pageId,
                'articleId' => $article->getId(),
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
            $slug = isset($node['slug']) ? (string) $node['slug'] : '';
            $editUrl = $slug !== ''
                ? $this->buildAdminUrl(
                    $basePath,
                    '/admin/pages/content',
                    $namespace,
                    ['pageSlug' => $slug]
                )
                : null;
            $children = [];
            if (isset($node['children'])) {
                $children = $this->mapTreeNodes((array) $node['children'], $basePath, $namespace);
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
        $validator = new NamespaceValidator();
        $normalized = $validator->normalizeCandidate($namespace);

        return $normalized ?? PageService::DEFAULT_NAMESPACE;
    }
}
