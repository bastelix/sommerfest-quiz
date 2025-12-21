<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Application\Seo\PageSeoConfigService;
use App\Domain\Page;
use App\Infrastructure\Database;
use App\Repository\NamespaceRepository;
use App\Service\DomainStartPageService;
use App\Service\NamespaceResolver;
use App\Service\PageService;
use App\Service\TenantService;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class ProjectPagesController
{
    private PageService $pageService;
    private PageSeoConfigService $seoService;
    private DomainStartPageService $domainService;
    private NamespaceResolver $namespaceResolver;
    private NamespaceRepository $namespaceRepository;
    private TenantService $tenantService;

    public function __construct(
        ?PDO $pdo = null,
        ?PageService $pageService = null,
        ?PageSeoConfigService $seoService = null,
        ?DomainStartPageService $domainService = null,
        ?NamespaceResolver $namespaceResolver = null,
        ?NamespaceRepository $namespaceRepository = null,
        ?TenantService $tenantService = null
    ) {
        $pdo = $pdo ?? Database::connectFromEnv();
        $this->pageService = $pageService ?? new PageService($pdo);
        $this->seoService = $seoService ?? new PageSeoConfigService($pdo);
        $this->domainService = $domainService ?? new DomainStartPageService($pdo);
        $this->namespaceResolver = $namespaceResolver ?? new NamespaceResolver();
        $this->namespaceRepository = $namespaceRepository ?? new NamespaceRepository($pdo);
        $this->tenantService = $tenantService ?? new TenantService($pdo);
    }

    public function content(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        [$availableNamespaces, $namespace] = $this->loadNamespaces($request);
        $pages = $this->pageService->getAllForNamespace($namespace);
        $pageList = array_map(
            static fn (Page $page): array => [
                'id' => $page->getId(),
                'slug' => $page->getSlug(),
                'title' => $page->getTitle(),
                'content' => $page->getContent(),
            ],
            $pages
        );
        $menuPages = array_map(
            static fn (Page $page): array => [
                'id' => $page->getId(),
                'slug' => $page->getSlug(),
                'title' => $page->getTitle(),
            ],
            $pages
        );
        $selectedSlug = $this->resolveSelectedSlug($pageList, $request->getQueryParams());

        return $view->render($response, 'admin/pages/content.twig', [
            'role' => $_SESSION['user']['role'] ?? '',
            'currentPath' => $request->getUri()->getPath(),
            'domainType' => $request->getAttribute('domainType'),
            'available_namespaces' => $availableNamespaces,
            'pageNamespace' => $namespace,
            'pages' => $pageList,
            'menu_pages' => $menuPages,
            'selectedPageSlug' => $selectedSlug,
            'csrf_token' => $this->ensureCsrfToken(),
            'pageTab' => 'content',
            'tenant' => $this->resolveTenant($request),
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

    public function wiki(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        [$availableNamespaces, $namespace] = $this->loadNamespaces($request);
        $pages = $this->pageService->getAllForNamespace($namespace);
        $pageList = array_map(
            static fn (Page $page): array => [
                'id' => $page->getId(),
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
            'selectedWikiPageId' => $selectedPageId,
            'csrf_token' => $this->ensureCsrfToken(),
            'pageTab' => 'wiki',
            'tenant' => $this->resolveTenant($request),
        ]);
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: string}
     */
    private function loadNamespaces(Request $request): array
    {
        $namespace = $this->namespaceResolver->resolve($request)->getNamespace();

        try {
            $availableNamespaces = $this->namespaceRepository->list();
        } catch (\RuntimeException $exception) {
            $availableNamespaces = [];
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

        if (!array_filter(
            $availableNamespaces,
            static fn (array $entry): bool => $entry['namespace'] === $namespace
        )) {
            $availableNamespaces[] = [
                'namespace' => $namespace,
                'label' => null,
                'is_active' => true,
                'created_at' => null,
                'updated_at' => null,
            ];
        }

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
        DomainStartPageService $domainService,
        string $host
    ): array {
        $mappings = $domainService->getAllMappings();
        $domainsBySlug = [];
        foreach ($mappings as $domain => $config) {
            $parsed = $domainService->parseStartPageKey($config['start_page']);
            $slug = $parsed['slug'];
            if ($slug === '') {
                continue;
            }
            $domainsBySlug[$slug][] = $domain;
        }

        $mainDomain = $domainService->normalizeDomain((string) getenv('MAIN_DOMAIN'));
        if ($mainDomain !== '') {
            $domainsBySlug['landing'][] = $mainDomain;
        }

        $currentHost = $domainService->normalizeDomain($host);
        $fallbackHost = $currentHost !== '' ? $currentHost : $mainDomain;

        $result = [];
        foreach ($pages as $page) {
            $pageDomains = $domainsBySlug[$page->getSlug()] ?? [];
            if ($pageDomains === [] && $page->getSlug() === 'landing' && $mainDomain !== '') {
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
                'slug' => $page->getSlug(),
                'title' => $page->getTitle(),
                'domains' => $pageDomains,
                'config' => $configData,
            ];
        }

        return $result;
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
