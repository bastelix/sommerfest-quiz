<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Application\Seo\PageSeoConfigService;
use App\Domain\Page;
use App\Infrastructure\Database;
use App\Repository\NamespaceRepository;
use App\Service\DomainService;
use App\Service\Marketing\PageAiPromptTemplateService;
use App\Service\MarketingMenuService;
use App\Service\MarketingSlugResolver;
use App\Service\NamespaceAccessService;
use App\Service\NamespaceService;
use App\Service\NamespaceResolver;
use App\Service\PageService;
use App\Service\ProjectSettingsService;
use App\Service\TenantService;
use App\Support\BasePathHelper;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;

class ProjectPagesController
{
    private const FIXED_MARKETING_SLUGS = [
        'landing',
        'calserver',
        'calhelp',
        'future-is-green',
        'labor',
        'fluke-metcal',
        'calserver-maintenance',
        'calserver-accessibility',
    ];

    private PageService $pageService;
    private PageSeoConfigService $seoService;
    private DomainService $domainService;
    private NamespaceResolver $namespaceResolver;
    private NamespaceRepository $namespaceRepository;
    private NamespaceService $namespaceService;
    private TenantService $tenantService;
    private PageAiPromptTemplateService $promptTemplateService;
    private ProjectSettingsService $projectSettings;
    private MarketingMenuService $marketingMenuService;

    public function __construct(
        ?PDO $pdo = null,
        ?PageService $pageService = null,
        ?PageSeoConfigService $seoService = null,
        ?DomainService $domainService = null,
        ?NamespaceResolver $namespaceResolver = null,
        ?NamespaceRepository $namespaceRepository = null,
        ?NamespaceService $namespaceService = null,
        ?TenantService $tenantService = null,
        ?PageAiPromptTemplateService $promptTemplateService = null,
        ?ProjectSettingsService $projectSettings = null,
        ?MarketingMenuService $marketingMenuService = null
    ) {
        $pdo = $pdo ?? Database::connectFromEnv();
        $this->pageService = $pageService ?? new PageService($pdo);
        $this->seoService = $seoService ?? new PageSeoConfigService($pdo);
        $this->domainService = $domainService ?? new DomainService($pdo);
        $this->namespaceResolver = $namespaceResolver ?? new NamespaceResolver();
        $this->namespaceRepository = $namespaceRepository ?? new NamespaceRepository($pdo);
        $this->namespaceService = $namespaceService ?? new NamespaceService($this->namespaceRepository);
        $this->tenantService = $tenantService ?? new TenantService($pdo);
        $this->promptTemplateService = $promptTemplateService ?? new PageAiPromptTemplateService();
        $this->projectSettings = $projectSettings ?? new ProjectSettingsService($pdo);
        $this->marketingMenuService = $marketingMenuService ?? new MarketingMenuService($pdo, $this->pageService);
    }

    public function content(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        [$availableNamespaces, $namespace] = $this->loadNamespaces($request);
        $basePath = BasePathHelper::normalize(RouteContext::fromRequest($request)->getBasePath());
        $pages = $this->pageService->getAllForNamespace($namespace);
        $pageList = array_map(
            fn (Page $page): array => [
                'id' => $page->getId(),
                'namespace' => $page->getNamespace(),
                'slug' => $page->getSlug(),
                'title' => $page->getTitle(),
                'content' => $page->getContent(),
                'preview_url' => $this->buildPreviewUrl($page, $namespace, $basePath),
            ],
            $pages
        );
        $pageNamespaceList = array_map(
            static fn (Page $page): array => [
                'id' => $page->getId(),
                'namespace' => $page->getNamespace(),
                'slug' => $page->getSlug(),
            ],
            $pages
        );
        $selectedSlug = $this->resolveSelectedSlug($pageList, $request->getQueryParams());
        $locale = (string) ($request->getAttribute('lang') ?? 'de');
        $startpageItem = $this->marketingMenuService->resolveStartpage($namespace, $locale, true);

        return $view->render($response, 'admin/pages/content.twig', [
            'role' => $_SESSION['user']['role'] ?? '',
            'currentPath' => $request->getUri()->getPath(),
            'domainType' => $request->getAttribute('domainType'),
            'available_namespaces' => $availableNamespaces,
            'pageNamespace' => $namespace,
            'pages' => $pageList,
            'page_namespace_list' => $pageNamespaceList,
            'selectedPageSlug' => $selectedSlug,
            'csrf_token' => $this->ensureCsrfToken(),
            'pageTab' => 'content',
            'tenant' => $this->resolveTenant($request),
            'prompt_templates' => $this->promptTemplateService->list(),
            'startpage_page_id' => $startpageItem?->getPageId(),
        ]);
    }

    public function seo(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        [$availableNamespaces, $namespace] = $this->loadNamespaces($request);
        $pages = $this->pageService->getAllForNamespace($namespace);
        $marketingPages = $this->filterMarketingPages($pages);
        $query = $request->getQueryParams();
        $selectedSeoSlug = isset($query['seoPage']) ? (string) $query['seoPage'] : '';
        $selectedSeoPage = $this->selectSeoPage($marketingPages, $selectedSeoSlug);
        $seoPages = $this->buildSeoPageData(
            $this->seoService,
            $marketingPages,
            $this->domainService,
            $request->getUri()->getHost()
        );
        $seoConfig = $selectedSeoPage !== null && isset($seoPages[$selectedSeoPage->getId()])
            ? $seoPages[$selectedSeoPage->getId()]['config']
            : [];

        return $view->render($response, 'admin/pages/seo.twig', [
            'role' => $_SESSION['user']['role'] ?? '',
            'currentPath' => $request->getUri()->getPath(),
            'domainType' => $request->getAttribute('domainType'),
            'available_namespaces' => $availableNamespaces,
            'pageNamespace' => $namespace,
            'seo_config' => $seoConfig,
            'seo_pages' => array_values($seoPages),
            'selectedSeoPageId' => $selectedSeoPage?->getId(),
            'csrf_token' => $this->ensureCsrfToken(),
            'pageTab' => 'seo',
            'tenant' => $this->resolveTenant($request),
        ]);
    }

    public function navigation(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        [$availableNamespaces, $namespace] = $this->loadNamespaces($request);
        $pages = $this->pageService->getAllForNamespace($namespace);
        $pageList = array_map(
            static fn (Page $page): array => [
                'id' => $page->getId(),
                'namespace' => $page->getNamespace(),
                'slug' => $page->getSlug(),
                'title' => $page->getTitle(),
                'content' => $page->getContent(),
            ],
            $pages
        );
        $pageNamespaceList = array_map(
            static fn (Page $page): array => [
                'id' => $page->getId(),
                'namespace' => $page->getNamespace(),
                'slug' => $page->getSlug(),
            ],
            $pages
        );
        $menuPages = array_map(
            static fn (Page $page): array => [
                'id' => $page->getId(),
                'namespace' => $page->getNamespace(),
                'slug' => $page->getSlug(),
                'title' => $page->getTitle(),
            ],
            $pages
        );
        $selectedSlug = $this->resolveSelectedSlug($pageList, $request->getQueryParams());

        return $view->render($response, 'admin/pages/navigation.twig', [
            'role' => $_SESSION['user']['role'] ?? '',
            'currentPath' => $request->getUri()->getPath(),
            'domainType' => $request->getAttribute('domainType'),
            'available_namespaces' => $availableNamespaces,
            'pageNamespace' => $namespace,
            'pages' => $pageList,
            'page_namespace_list' => $pageNamespaceList,
            'menu_pages' => $menuPages,
            'selectedPageSlug' => $selectedSlug,
            'csrf_token' => $this->ensureCsrfToken(),
            'pageTab' => 'navigation',
            'tenant' => $this->resolveTenant($request),
        ]);
    }

    public function updateStartpage(Request $request, Response $response, array $args): Response
    {
        $pageId = (int) ($args['pageId'] ?? 0);
        if ($pageId <= 0) {
            return $response->withStatus(400);
        }

        $namespace = $this->namespaceResolver->resolve($request)->getNamespace();
        $page = $this->pageService->findById($pageId);
        if ($page === null || $page->getNamespace() !== $namespace) {
            return $response->withStatus(404);
        }

        $payload = $request->getParsedBody();
        if (!is_array($payload)) {
            $contentType = $request->getHeaderLine('Content-Type');
            $rawBody = (string) $request->getBody();
            if (stripos($contentType, 'application/json') !== false && $rawBody !== '') {
                $payload = json_decode($rawBody, true);
            }
        }

        $rawFlag = is_array($payload)
            ? ($payload['is_startpage'] ?? $payload['isStartpage'] ?? $payload['startpage'] ?? null)
            : null;
        $isStartpage = filter_var($rawFlag, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($isStartpage === null) {
            $isStartpage = $rawFlag === 1 || $rawFlag === '1';
        }

        try {
            if ($isStartpage) {
                $this->marketingMenuService->markPageAsStartpage($pageId);
            } else {
                $this->marketingMenuService->clearStartpagesForNamespace($namespace);
            }

            $current = $this->marketingMenuService->resolveStartpage($namespace, null, true);
        } catch (\RuntimeException $exception) {
            $response->getBody()->write(json_encode([
                'error' => $exception->getMessage(),
            ], JSON_PRETTY_PRINT));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }

        $response->getBody()->write(json_encode([
            'startpagePageId' => $current?->getPageId(),
        ], JSON_PRETTY_PRINT));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function wiki(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        [$availableNamespaces, $namespace] = $this->loadNamespaces($request);
        $pages = $this->pageService->getAllForNamespace($namespace);
        $pageList = array_map(
            static fn (Page $page): array => [
                'id' => $page->getId(),
                'namespace' => $page->getNamespace(),
                'slug' => $page->getSlug(),
                'title' => $page->getTitle(),
            ],
            $pages
        );
        $selectedPageId = $this->resolveSelectedPageId($pages, $request->getQueryParams());

        return $view->render($response, 'admin/pages/wiki.twig', [
            'role' => $_SESSION['user']['role'] ?? '',
            'currentPath' => $request->getUri()->getPath(),
            'domainType' => $request->getAttribute('domainType'),
            'available_namespaces' => $availableNamespaces,
            'pageNamespace' => $namespace,
            'pages' => $pageList,
            'page_namespace_list' => $pageList,
            'selectedWikiPageId' => $selectedPageId,
            'csrf_token' => $this->ensureCsrfToken(),
            'pageTab' => 'wiki',
            'tenant' => $this->resolveTenant($request),
        ]);
    }

    public function cookies(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        [$availableNamespaces, $namespace] = $this->loadNamespaces($request);
        $cookieSettings = $this->projectSettings->getCookieConsentSettings($namespace);

        return $view->render($response, 'admin/pages/cookies.twig', [
            'role' => $_SESSION['user']['role'] ?? '',
            'currentPath' => $request->getUri()->getPath(),
            'domainType' => $request->getAttribute('domainType'),
            'available_namespaces' => $availableNamespaces,
            'pageNamespace' => $namespace,
            'cookie_settings' => $cookieSettings,
            'csrf_token' => $this->ensureCsrfToken(),
            'pageTab' => 'cookies',
            'tenant' => $this->resolveTenant($request),
        ]);
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: string}
     */
    private function loadNamespaces(Request $request): array
    {
        $namespace = $this->namespaceResolver->resolve($request)->getNamespace();
        $role = $_SESSION['user']['role'] ?? null;
        $accessService = new NamespaceAccessService();
        $allowedNamespaces = $accessService->resolveAllowedNamespaces(is_string($role) ? $role : null);

        try {
            $availableNamespaces = $this->namespaceService->all();
        } catch (\RuntimeException $exception) {
            $availableNamespaces = [];
        }

        if ($accessService->shouldExposeNamespace(PageService::DEFAULT_NAMESPACE, $allowedNamespaces, $role)
            && !array_filter(
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
        if (!$currentNamespaceExists
            && $accessService->shouldExposeNamespace($namespace, $allowedNamespaces, $role)) {
            $availableNamespaces[] = [
                'namespace' => $namespace,
                'label' => 'nicht gespeichert',
                'is_active' => false,
                'created_at' => null,
                'updated_at' => null,
            ];
        }

        if ($allowedNamespaces !== []) {
            foreach ($allowedNamespaces as $allowedNamespace) {
                if (!array_filter(
                    $availableNamespaces,
                    static fn (array $entry): bool => $entry['namespace'] === $allowedNamespace
                )) {
                    $availableNamespaces[] = [
                        'namespace' => $allowedNamespace,
                        'label' => 'nicht gespeichert',
                        'is_active' => false,
                        'created_at' => null,
                        'updated_at' => null,
                    ];
                }
            }
        }

        $availableNamespaces = $accessService->filterNamespaceEntries($availableNamespaces, $allowedNamespaces, $role);

        return [$availableNamespaces, $namespace];
    }

    /**
     * @param array<int,array<string, mixed>> $pages
     */
    private function resolveSelectedSlug(array $pages, array $params): string
    {
        $requestedSlug = '';
        if (isset($params['pageSlug']) || isset($params['slug'])) {
            $requestedSlug = trim((string) ($params['pageSlug'] ?? $params['slug'] ?? ''));
        }

        $pageSlugs = array_values(array_filter(array_map(
            static fn (array $page): string => (string) ($page['slug'] ?? ''),
            $pages
        )));

        if ($requestedSlug !== '' && in_array($requestedSlug, $pageSlugs, true)) {
            return $requestedSlug;
        }

        return $pageSlugs[0] ?? '';
    }

    /**
     * @param Page[] $pages
     */
    private function resolveSelectedPageId(array $pages, array $params): int
    {
        if ($pages === []) {
            return 0;
        }

        $requestedId = isset($params['pageId']) ? (int) $params['pageId'] : 0;
        if ($requestedId > 0) {
            foreach ($pages as $page) {
                if ($page->getId() === $requestedId) {
                    return $requestedId;
                }
            }
        }

        $requestedSlug = isset($params['slug']) ? (string) $params['slug'] : '';
        if ($requestedSlug !== '') {
            foreach ($pages as $page) {
                if ($page->getSlug() === $requestedSlug) {
                    return $page->getId();
                }
            }
        }

        return $pages[0]->getId();
    }

    /**
     * @param Page[] $pages
     * @return Page[]
     */
    private function filterMarketingPages(array $pages): array
    {
        return array_values(array_filter(
            $pages,
            static fn (Page $page): bool => !in_array($page->getSlug(), LandingpageController::EXCLUDED_SLUGS, true)
        ));
    }

    /**
     * @param Page[] $pages
     */
    private function selectSeoPage(array $pages, string $slug): ?Page
    {
        if ($pages === []) {
            return null;
        }

        foreach ($pages as $page) {
            if ($slug !== '' && $page->getSlug() === $slug) {
                return $page;
            }
        }

        return $pages[0];
    }

    /**
     * @param Page[] $pages
     * @return array<int,array{id:int,slug:string,title:string,config:array<string,mixed>}> keyed by page id
     */
    private function buildSeoPageData(
        PageSeoConfigService $service,
        array $pages,
        DomainService $domainService,
        string $host
    ): array {
        $mainDomain = $domainService->normalizeDomain((string) getenv('MAIN_DOMAIN'));
        $currentHost = $domainService->normalizeDomain($host);
        $fallbackHost = $currentHost !== '' ? $currentHost : $mainDomain;

        $result = [];
        foreach ($pages as $page) {
            $pageDomains = [];
            if ($page->getSlug() === 'landing' && $mainDomain !== '') {
                $pageDomains[] = $mainDomain;
            }
            if ($pageDomains === [] && $fallbackHost !== '') {
                $pageDomains[] = $fallbackHost;
            }
            $pageDomains = array_values(
                array_unique(
                    array_filter($pageDomains, static fn ($value): bool => $value !== '')
                )
            );

            $config = $service->load($page->getId());
            $configData = $config ? $config->jsonSerialize() : $service->defaultConfig($page->getId());

            if (($configData['domain'] ?? null) !== null) {
                $domainValue = (string) $configData['domain'];
                if ($domainValue !== '' && !in_array($domainValue, $pageDomains, true)) {
                    array_unshift($pageDomains, $domainValue);
                }
            } elseif ($pageDomains !== []) {
                $configData['domain'] = $pageDomains[0];
            }

            $result[$page->getId()] = [
                'id' => $page->getId(),
                'namespace' => $page->getNamespace(),
                'slug' => $page->getSlug(),
                'title' => $page->getTitle(),
                'domains' => $pageDomains,
                'config' => $configData,
            ];
        }

        return $result;
    }

    private function buildPreviewUrl(Page $page, string $namespace, string $basePath): string
    {
        $slug = trim($page->getSlug());
        if ($slug === '') {
            return '';
        }

        $baseSlug = MarketingSlugResolver::resolveBaseSlug($slug);
        $path = $this->resolvePreviewPath($slug, $baseSlug);
        $query = http_build_query(['namespace' => $namespace]);

        return $basePath . $path . ($query !== '' ? '?' . $query : '');
    }

    private function resolvePreviewPath(string $slug, string $baseSlug): string
    {
        if (in_array($baseSlug, self::FIXED_MARKETING_SLUGS, true)) {
            return '/' . $baseSlug;
        }

        return '/m/' . $slug;
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

    private function resolveTenant(Request $request): ?array
    {
        $domainType = (string) $request->getAttribute('domainType');
        if ($domainType === 'main') {
            return $this->tenantService->getMainTenant();
        }

        $host = $request->getUri()->getHost();
        $subdomain = explode('.', $host)[0];
        if ($subdomain === '') {
            return null;
        }

        return $this->tenantService->getBySubdomain($subdomain);
    }
}
