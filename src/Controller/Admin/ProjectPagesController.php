<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Application\Seo\PageSeoConfigService;
use App\Domain\Page;
use App\Domain\MarketingPageMenuItem;
use App\Infrastructure\Database;
use App\Repository\NamespaceRepository;
use App\Service\DomainService;
use App\Service\Marketing\MarketingMenuAiErrorMapper;
use App\Service\Marketing\PageAiPromptTemplateService;
use App\Service\MarketingMenuService;
use App\Service\MarketingSlugResolver;
use App\Service\NamespaceAccessService;
use App\Service\NamespaceService;
use App\Service\NamespaceValidator;
use App\Service\NamespaceResolver;
use App\Service\DesignTokenService;
use App\Service\ConfigService;
use App\Service\EffectsPolicyService;
use App\Service\PageService;
use App\Service\ProjectSettingsService;
use App\Service\TenantService;
use App\Support\BasePathHelper;
use App\Support\DomainNameHelper;
use App\Support\FeatureFlags;
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
    private MarketingMenuService $marketingMenu;
    private ConfigService $configService;
    private DesignTokenService $designTokens;
    private EffectsPolicyService $effectsPolicy;

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
        ?MarketingMenuService $marketingMenu = null,
        ?ConfigService $configService = null,
        ?DesignTokenService $designTokens = null,
        ?EffectsPolicyService $effectsPolicy = null
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
        $this->marketingMenu = $marketingMenu ?? new MarketingMenuService($pdo, $this->pageService);
        $this->configService = $configService ?? new ConfigService($pdo);
        $this->designTokens = $designTokens ?? new DesignTokenService($pdo, $this->configService);
        $this->effectsPolicy = $effectsPolicy ?? new EffectsPolicyService($this->configService);
    }

    public function content(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        $namespaceValidator = new NamespaceValidator();
        $domainNamespace = $namespaceValidator->normalizeCandidate($request->getAttribute('domainNamespace'));
        [$availableNamespaces, $namespace] = $this->loadNamespaces($request);
        $domainNamespace = $domainNamespace ?? $this->resolveNamespaceFromDomains($namespace);
        $hasDomainNamespace = $domainNamespace !== null;
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
        $host = DomainNameHelper::normalize($request->getUri()->getHost(), stripAdmin: false);
        $domainOptions = $this->buildDomainOptionsForNamespace($namespace, $host);
        $startpageMap = $this->buildStartpageLookup($namespace, $locale, $domainOptions['options']);
        $selectedDomain = $this->resolveSelectedStartpageDomain($domainOptions, $startpageMap);
        $startpagePageId = $startpageMap[$selectedDomain] ?? null;
        $design = $this->loadDesign($namespace);

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
            'startpage_page_id' => $startpagePageId,
            'domainNamespace' => $domainNamespace,
            'hasDomainNamespace' => $hasDomainNamespace,
            'startpage_domain_options' => $domainOptions['options'],
            'startpage_selected_domain' => $selectedDomain,
            'startpage_map' => $startpageMap,
            'appearance' => $design['appearance'],
            'design' => $design,
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
            'use_navigation_tree' => FeatureFlags::marketingNavigationTreeEnabled(),
            'navigation_settings' => $this->projectSettings->getCookieConsentSettings($namespace),
        ]);
    }

    public function generateMenu(Request $request, Response $response, array $args): Response
    {
        $pageId = (int) ($args['pageId'] ?? 0);
        $namespace = $this->namespaceResolver->resolve($request)->getNamespace();

        $page = $this->pageService->findById($pageId);
        if ($page === null || $page->getNamespace() !== $namespace) {
            return $response->withStatus(404);
        }

        $payload = $this->parseJsonBody($request) ?? [];
        $locale = isset($payload['locale']) && is_string($payload['locale']) ? $payload['locale'] : null;
        $overwrite = $this->parseBooleanFlag($payload['overwrite'] ?? false);

        try {
            $items = $this->marketingMenu->generateMenuFromPage($page, $locale, $overwrite);
        } catch (\RuntimeException $exception) {
            $mapper = new MarketingMenuAiErrorMapper();
            $mapped = $mapper->map($exception);

            $response->getBody()->write(json_encode([
                'error' => $mapped['message'],
                'error_code' => $mapped['error_code'],
                'items' => [],
            ], JSON_PRETTY_PRINT));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus($mapped['status']);
        }

        $response->getBody()->write(json_encode([
            'items' => array_map(fn (MarketingPageMenuItem $item): array => $this->serializeMenuItem($item), $items),
        ], JSON_PRETTY_PRINT));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function translateMenu(Request $request, Response $response, array $args): Response
    {
        $pageId = (int) ($args['pageId'] ?? 0);
        $namespace = $this->namespaceResolver->resolve($request)->getNamespace();

        $page = $this->pageService->findById($pageId);
        if ($page === null || $page->getNamespace() !== $namespace) {
            return $response->withStatus(404);
        }

        $payload = $this->parseJsonBody($request) ?? [];
        $sourceLocale = isset($payload['sourceLocale']) && is_string($payload['sourceLocale'])
            ? $payload['sourceLocale']
            : 'de';
        $targetLocale = isset($payload['targetLocale']) && is_string($payload['targetLocale'])
            ? $payload['targetLocale']
            : '';

        if (trim($targetLocale) === '') {
            return $response->withStatus(422);
        }

        $overwrite = $this->parseBooleanFlag($payload['overwrite'] ?? true);

        try {
            $items = $this->marketingMenu->translateMenuFromLocale($page, $sourceLocale, $targetLocale, $overwrite);
        } catch (\RuntimeException $exception) {
            $mapper = new MarketingMenuAiErrorMapper();
            $mapped = $mapper->map($exception);

            $response->getBody()->write(json_encode([
                'error' => $mapped['message'],
                'error_code' => $mapped['error_code'],
                'items' => [],
            ], JSON_PRETTY_PRINT));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus($mapped['status']);
        }

        $response->getBody()->write(json_encode([
            'items' => array_map(fn (MarketingPageMenuItem $item): array => $this->serializeMenuItem($item), $items),
        ], JSON_PRETTY_PRINT));

        return $response->withHeader('Content-Type', 'application/json');
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

        $rawDomain = is_array($payload)
            ? ($payload['domain'] ?? $payload['startpage_domain'] ?? null)
            : null;
        $normalizedDomain = null;
        if (is_string($rawDomain) && trim($rawDomain) !== '') {
            $normalizedDomain = DomainNameHelper::normalize($rawDomain, stripAdmin: false);
            if ($normalizedDomain === '') {
                $normalizedDomain = null;
            }
        }

        try {
            if ($isStartpage) {
                $this->pageService->markAsStartpage($pageId, $namespace, $normalizedDomain);
            } else {
                $this->pageService->clearStartpageForNamespace($namespace, $normalizedDomain);
            }

            $current = $this->pageService->resolveStartpage($namespace, null, $normalizedDomain);
        } catch (\RuntimeException $exception) {
            $response->getBody()->write(json_encode([
                'error' => $exception->getMessage(),
            ], JSON_PRETTY_PRINT));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }

        $response->getBody()->write(json_encode([
            'startpagePageId' => $current?->getId(),
            'domain' => $normalizedDomain,
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
     * @return array{options: list<array<string, mixed>>, selected: string}
     */
    private function buildDomainOptionsForNamespace(string $namespace, string $host): array
    {
        $domainsByNamespace = $this->domainService->listDomainsByNamespace(includeInactive: true);
        $namespaceDomains = $domainsByNamespace[$namespace] ?? [];

        $options = [[
            'value' => '',
            'label' => 'Namespace-weit (Fallback)',
            'is_active' => true,
            'is_unassigned' => false,
        ]];

        $selected = '';
        foreach ($namespaceDomains as $domain) {
            $value = (string) $domain['normalized_host'];
            if ($value === '') {
                continue;
            }

            $options[] = [
                'value' => $value,
                'label' => (string) $domain['host'],
                'is_active' => (bool) $domain['is_active'],
                'is_unassigned' => false,
            ];

            if ($value === $host) {
                $selected = $value;
            }
        }

        if ($selected === '' && $host !== '') {
            $options[] = [
                'value' => $host,
                'label' => $host,
                'is_active' => true,
                'is_unassigned' => true,
            ];
            $selected = $host;
        }

        return [
            'options' => $options,
            'selected' => $selected,
        ];
    }

    /**
     * @param list<array<string, mixed>> $domainOptions
     *
     * @return array<string, int|null>
     */
    private function buildStartpageLookup(string $namespace, string $locale, array $domainOptions): array
    {
        $lookup = [];
        foreach ($domainOptions as $option) {
            $value = (string) ($option['value'] ?? '');
            $page = $this->pageService->resolveStartpage($namespace, $locale, $value !== '' ? $value : null);
            $lookup[$value] = $page?->getId();
        }

        if (!array_key_exists('', $lookup)) {
            $page = $this->pageService->resolveStartpage($namespace, $locale, null);
            $lookup[''] = $page?->getId();
        }

        return $lookup;
    }

    /**
     * @param array{options: list<array<string, mixed>>, selected: string} $domainOptions
     * @param array<string, int|null> $startpageMap
     */
    private function resolveSelectedStartpageDomain(array $domainOptions, array $startpageMap): string
    {
        $selectedDomain = $domainOptions['selected'];

        if (array_key_exists($selectedDomain, $startpageMap)) {
            return $selectedDomain;
        }

        foreach ($domainOptions['options'] as $option) {
            $value = (string) ($option['value'] ?? '');
            if (array_key_exists($value, $startpageMap) && $startpageMap[$value] !== null) {
                return $value;
            }
        }

        return $selectedDomain;
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: string}
     */
    private function loadNamespaces(Request $request, ?string $preferredNamespace = null): array
    {
        $namespace = $preferredNamespace ?? $this->namespaceResolver->resolve($request)->getNamespace();
        $role = $_SESSION['user']['role'] ?? null;
        $accessService = new NamespaceAccessService();
        $allowedNamespaces = $accessService->resolveAllowedNamespaces(is_string($role) ? $role : null);

        try {
            $availableNamespaces = $this->namespaceService->all();
        } catch (\RuntimeException $exception) {
            $availableNamespaces = [];
        }

        if (
            $accessService->shouldExposeNamespace(PageService::DEFAULT_NAMESPACE, $allowedNamespaces, $role)
            && !array_filter(
                $availableNamespaces,
                static fn (array $entry): bool => $entry['namespace'] === PageService::DEFAULT_NAMESPACE
            )
        ) {
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
        if (
            !$currentNamespaceExists
            && $accessService->shouldExposeNamespace($namespace, $allowedNamespaces, $role)
        ) {
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
                if (
                    !array_filter(
                        $availableNamespaces,
                        static fn (array $entry): bool => $entry['namespace'] === $allowedNamespace
                    )
                ) {
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

    private function resolveNamespaceFromDomains(string $namespace): ?string
    {
        if ($namespace === '') {
            return null;
        }

        $domains = $this->domainService->listDomainsByNamespace(includeInactive: true);
        $namespaceDomains = $domains[$namespace] ?? [];

        if ($namespaceDomains !== []) {
            return $namespace;
        }

        return null;
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

    private function parseJsonBody(Request $request): ?array
    {
        $contentType = $request->getHeaderLine('Content-Type');
        if (stripos($contentType, 'application/json') === false) {
            return null;
        }

        $rawBody = (string) $request->getBody();
        if ($rawBody === '') {
            return null;
        }

        $decoded = json_decode($rawBody, true);
        if (!is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    private function parseBooleanFlag(mixed $value): bool
    {
        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if ($normalized === 'true' || $normalized === '1' || $normalized === 'yes') {
                return true;
            }
            if ($normalized === 'false' || $normalized === '0' || $normalized === 'no') {
                return false;
            }
        }

        if (is_int($value)) {
            return $value === 1;
        }

        return (bool) $value;
    }

    private function serializeMenuItem(MarketingPageMenuItem $item): array
    {
        return [
            'id' => $item->getId(),
            'pageId' => $item->getPageId(),
            'namespace' => $item->getNamespace(),
            'parentId' => $item->getParentId(),
            'label' => $item->getLabel(),
            'href' => $item->getHref(),
            'icon' => $item->getIcon(),
            'layout' => $item->getLayout(),
            'detailTitle' => $item->getDetailTitle(),
            'detailText' => $item->getDetailText(),
            'detailSubline' => $item->getDetailSubline(),
            'position' => $item->getPosition(),
            'isExternal' => $item->isExternal(),
            'locale' => $item->getLocale(),
            'isActive' => $item->isActive(),
            'isStartpage' => $item->isStartpage(),
        ];
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
            $pageDomains = array_values(array_unique(array_filter($pageDomains)));

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

    /**
     * @return array{config: array<string,mixed>, appearance: array<string,mixed>, effects: array{effectsProfile: string, sliderProfile: string}, namespace: string}
     */
    private function loadDesign(string $namespace): array
    {
        $config = $this->configService->getConfigForEvent($namespace);

        if ($config === [] && $namespace !== PageService::DEFAULT_NAMESPACE) {
            $fallbackConfig = $this->configService->getConfigForEvent(PageService::DEFAULT_NAMESPACE);
            if ($fallbackConfig !== []) {
                $config = $fallbackConfig;
            }
        }

        $tokens = $this->designTokens->getTokensForNamespace($namespace);
        $appearance = [
            'tokens' => $tokens,
            'defaults' => $this->designTokens->getDefaults(),
            'colors' => [
                'primary' => $tokens['brand']['primary'] ?? null,
                'secondary' => $tokens['brand']['accent'] ?? null,
                'accent' => $tokens['brand']['accent'] ?? null,
            ],
        ];

        $effects = $this->effectsPolicy->getEffectsForNamespace($namespace);

        return [
            'config' => $config,
            'appearance' => $appearance,
            'effects' => $effects,
            'namespace' => $namespace,
        ];
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
