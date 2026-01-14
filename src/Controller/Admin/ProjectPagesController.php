<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Application\Seo\PageSeoConfigService;
use App\Domain\Page;
use App\Domain\CmsPageMenuItem;
use App\Infrastructure\Database;
use App\Repository\NamespaceRepository;
use App\Service\DomainService;
use App\Service\Marketing\MarketingMenuAiErrorMapper;
use App\Service\Marketing\PageAiPromptTemplateService;
use App\Service\CmsPageMenuService;
use App\Service\NamespaceAccessService;
use App\Service\NamespaceService;
use App\Service\NamespaceValidator;
use App\Service\NamespaceResolver;
use App\Service\ConfigService;
use App\Service\EffectsPolicyService;
use App\Service\NamespaceAppearanceService;
use App\Service\PageService;
use App\Service\ProjectSettingsService;
use App\Service\TenantService;
use App\Support\BasePathHelper;
use App\Support\DomainNameHelper;
use App\Support\FeatureFlags;
use App\Support\PageAnchorExtractor;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;

class ProjectPagesController
{
    private const SECTION_LAYOUTS = ['normal', 'full', 'card'];
    private const SECTION_INTENTS = ['content', 'feature', 'highlight', 'hero'];
    private const BACKGROUND_TOKENS = ['surface', 'muted', 'primary', 'secondary', 'accent'];

    private PageService $pageService;
    private PageSeoConfigService $seoService;
    private DomainService $domainService;
    private NamespaceResolver $namespaceResolver;
    private NamespaceRepository $namespaceRepository;
    private NamespaceService $namespaceService;
    private TenantService $tenantService;
    private PageAiPromptTemplateService $promptTemplateService;
    private ProjectSettingsService $projectSettings;
    private CmsPageMenuService $cmsMenu;
    private ConfigService $configService;
    private NamespaceAppearanceService $namespaceAppearance;
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
        ?CmsPageMenuService $cmsMenu = null,
        ?ConfigService $configService = null,
        ?NamespaceAppearanceService $namespaceAppearance = null,
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
        $this->cmsMenu = $cmsMenu ?? new CmsPageMenuService($pdo, $this->pageService);
        $this->configService = $configService ?? new ConfigService($pdo);
        $this->namespaceAppearance = $namespaceAppearance ?? new NamespaceAppearanceService();
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
                'type' => $page->getType(),
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
        $pageTypeConfig = $this->normalizePageTypeConfig($design['config']['pageTypes'] ?? []);
        $pageTypeDefaults = $this->buildPageTypeDefaults($pages, $pageTypeConfig);
        $pageTypeFlash = $_SESSION['page_types_flash'] ?? null;
        unset($_SESSION['page_types_flash']);

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
            'pageTypeDefaults' => $pageTypeDefaults,
            'pageTypeLayoutOptions' => self::SECTION_LAYOUTS,
            'pageTypeIntentOptions' => self::SECTION_INTENTS,
            'pageTypeBackgroundTokens' => self::BACKGROUND_TOKENS,
            'pageTypeFlash' => $pageTypeFlash,
        ]);
    }

    public function savePageTypes(Request $request, Response $response): Response
    {
        $parsedBody = $request->getParsedBody();
        if (!is_array($parsedBody)) {
            return $response->withStatus(400);
        }

        $validator = new NamespaceValidator();
        $namespace = $validator->normalizeCandidate((string) ($parsedBody['namespace'] ?? ''));
        if ($namespace === null) {
            return $response->withStatus(400);
        }

        $pageTypesPayload = $parsedBody['pageTypes'] ?? [];
        if (!is_array($pageTypesPayload)) {
            $pageTypesPayload = [];
        }

        $config = $this->configService->getConfigForEvent($namespace);
        $existingPageTypes = $this->normalizePageTypeConfig($config['pageTypes'] ?? []);
        $normalizedPageTypes = $this->normalizePageTypePayload($pageTypesPayload, $existingPageTypes);

        $this->configService->ensureConfigForEvent($namespace);
        $this->configService->saveConfig([
            'event_uid' => $namespace,
            'pageTypes' => $normalizedPageTypes,
        ]);

        $_SESSION['page_types_flash'] = [
            'type' => 'success',
            'message' => 'Page-Type-Defaults gespeichert.',
        ];

        return $response
            ->withHeader('Location', $this->buildContentRedirectUrl($request, $namespace))
            ->withStatus(303);
    }

    public function seo(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        [$availableNamespaces, $namespace] = $this->loadNamespaces($request);
        $pages = $this->pageService->getAllForNamespace($namespace);
        $marketingPages = $this->filterCmsPages($pages);
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
        $mode = (string) ($request->getQueryParams()['mode'] ?? 'cms');
        $isCmsMode = $mode === 'cms';
        $allPages = $this->pageService->getAllForNamespace($namespace);
        $pages = $isCmsMode
            ? $allPages
            : $this->filterCmsPages($allPages);
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
        $internalLinks = $this->buildInternalLinks($allPages);
        $selectedSlug = $this->resolveSelectedSlug($pageList, $request->getQueryParams());
        $navigationVariants = [
            ['value' => 'footer_columns_2', 'label' => 'Footer (2 Spalten)', 'columns' => 2],
            ['value' => 'footer_columns_3', 'label' => 'Footer (3 Spalten)', 'columns' => 3],
        ];
        $selectedVariant = (string) ($request->getQueryParams()['variant'] ?? $navigationVariants[0]['value']);

        return $view->render($response, 'admin/pages/navigation.twig', [
            'role' => $_SESSION['user']['role'] ?? '',
            'currentPath' => $request->getUri()->getPath(),
            'domainType' => $request->getAttribute('domainType'),
            'available_namespaces' => $availableNamespaces,
            'pageNamespace' => $namespace,
            'pages' => $pageList,
            'page_namespace_list' => $pageNamespaceList,
            'menu_pages' => $menuPages,
            'internal_links' => $internalLinks,
            'selectedPageSlug' => $selectedSlug,
            'csrf_token' => $this->ensureCsrfToken(),
            'pageTab' => 'navigation',
            'tenant' => $this->resolveTenant($request),
            'use_navigation_tree' => FeatureFlags::marketingNavigationTreeEnabled(),
            'navigation_settings' => $this->projectSettings->getCookieConsentSettings($namespace),
            'navigation_mode' => $isCmsMode ? 'cms' : 'marketing',
            'navigation_variants' => $navigationVariants,
            'selected_navigation_variant' => $selectedVariant,
        ]);
    }

    /**
     * @param array<int, Page> $pages
     * @return array<int, array{value:string,label:string,group:string}>
     */
    private function buildInternalLinks(array $pages): array
    {
        $extractor = new PageAnchorExtractor();
        $pagePaths = [];
        $anchors = [];
        $pageAnchors = [];

        foreach ($pages as $page) {
            $slug = $page->getSlug();
            if ($slug === '') {
                continue;
            }
            $path = '/' . ltrim($slug, '/');
            $pagePaths[$path] = $path;

            $anchorIds = $extractor->extractAnchorIds($page->getContent());
            foreach ($anchorIds as $anchorId) {
                $anchors[$anchorId] = $anchorId;
                $pageAnchors[$path . '#' . $anchorId] = $anchorId;
            }
        }

        $options = [];
        foreach (array_values($pagePaths) as $path) {
            $options[] = [
                'value' => $path,
                'label' => $path,
                'group' => 'Seitenpfade',
            ];
        }

        $anchorList = array_keys($anchors);
        sort($anchorList, SORT_STRING);
        foreach ($anchorList as $anchorId) {
            $options[] = [
                'value' => '#' . $anchorId,
                'label' => '#' . $anchorId,
                'group' => 'Anker',
            ];
        }

        $pageAnchorList = array_keys($pageAnchors);
        sort($pageAnchorList, SORT_STRING);
        foreach ($pageAnchorList as $value) {
            $options[] = [
                'value' => $value,
                'label' => $value,
                'group' => 'Seiten + Anker',
            ];
        }

        return $options;
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
            $items = $this->cmsMenu->generateMenuFromPage($page, $locale, $overwrite);
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
            'items' => array_map(fn (CmsPageMenuItem $item): array => $this->serializeMenuItem($item), $items),
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
            $items = $this->cmsMenu->translateMenuFromLocale($page, $sourceLocale, $targetLocale, $overwrite);
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
            'items' => array_map(fn (CmsPageMenuItem $item): array => $this->serializeMenuItem($item), $items),
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

    private function serializeMenuItem(CmsPageMenuItem $item): array
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
    private function filterCmsPages(array $pages): array
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

        $appearance = $this->namespaceAppearance->load($namespace);

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

        $path = $this->resolvePreviewPath($slug);
        $query = http_build_query(['namespace' => $namespace]);

        return $basePath . $path . ($query !== '' ? '?' . $query : '');
    }

    private function resolvePreviewPath(string $slug): string
    {
        return '/cms/pages/' . $slug;
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

    private function buildContentRedirectUrl(Request $request, string $namespace): string
    {
        $basePath = RouteContext::fromRequest($request)->getBasePath();
        $query = http_build_query(['namespace' => $namespace]);

        return $basePath . '/admin/pages/content' . ($query !== '' ? '?' . $query : '');
    }

    /**
     * @param array<string, mixed> $pageTypeConfig
     *
     * @return array<string, array<string, mixed>>
     */
    private function normalizePageTypeConfig(array $pageTypeConfig): array
    {
        $normalized = [];
        foreach ($pageTypeConfig as $type => $config) {
            $key = trim((string) $type);
            if ($key === '' || !is_array($config)) {
                continue;
            }
            $normalized[$key] = $config;
        }

        return $normalized;
    }

    /**
     * @param array<int|string, mixed> $pageTypesPayload
     * @param array<string, array<string, mixed>> $existingPageTypes
     *
     * @return array<string, array<string, mixed>>
     */
    private function normalizePageTypePayload(array $pageTypesPayload, array $existingPageTypes): array
    {
        $normalized = [];

        foreach ($pageTypesPayload as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $type = trim((string) ($entry['type'] ?? ''));
            if ($type === '') {
                continue;
            }

            $layout = $this->normalizeSectionLayout($entry['layout'] ?? null);
            $intent = $this->normalizeSectionIntent($entry['intent'] ?? null);
            $background = $this->normalizeSectionBackground($entry['backgroundToken'] ?? null);

            $sectionStyleDefaults = [];
            if ($layout !== null) {
                $sectionStyleDefaults['layout'] = $layout;
            }
            if ($intent !== null) {
                $sectionStyleDefaults['intent'] = $intent;
            }
            if ($background !== null) {
                $sectionStyleDefaults['background'] = $background;
            }

            $baseConfig = $existingPageTypes[$type] ?? [];
            $entryConfig = $baseConfig;
            if ($sectionStyleDefaults !== []) {
                $entryConfig['sectionStyleDefaults'] = $sectionStyleDefaults;
            } else {
                unset($entryConfig['sectionStyleDefaults']);
            }

            if ($entryConfig !== []) {
                $normalized[$type] = $entryConfig;
            }
        }

        return $normalized;
    }

    private function normalizeSectionLayout(mixed $value): ?string
    {
        $candidate = is_string($value) ? trim($value) : '';
        if ($candidate === 'fullwidth') {
            $candidate = 'full';
        }

        return in_array($candidate, self::SECTION_LAYOUTS, true) ? $candidate : null;
    }

    private function normalizeSectionIntent(mixed $value): ?string
    {
        $candidate = is_string($value) ? trim($value) : '';

        return in_array($candidate, self::SECTION_INTENTS, true) ? $candidate : null;
    }

    /**
     * @return array<string, string>|null
     */
    private function normalizeSectionBackground(mixed $value): ?array
    {
        $candidate = is_string($value) ? trim($value) : '';
        if ($candidate === '') {
            return null;
        }
        if ($candidate === 'none') {
            return ['mode' => 'none'];
        }

        if (!in_array($candidate, self::BACKGROUND_TOKENS, true)) {
            return null;
        }

        return [
            'mode' => 'color',
            'colorToken' => $candidate,
        ];
    }

    /**
     * @param list<Page> $pages
     * @param array<string, array<string, mixed>> $pageTypeConfig
     *
     * @return list<array{type: string, sectionStyleDefaults: array<string, mixed>}>
     */
    private function buildPageTypeDefaults(array $pages, array $pageTypeConfig): array
    {
        $types = [];
        foreach ($pages as $page) {
            $pageType = $page->getType();
            if ($pageType !== null && $pageType !== '') {
                $types[$pageType] = true;
            }
        }

        foreach (array_keys($pageTypeConfig) as $type) {
            if ($type !== '') {
                $types[$type] = true;
            }
        }

        $result = [];
        $sortedTypes = array_keys($types);
        sort($sortedTypes);

        foreach ($sortedTypes as $type) {
            $entry = $pageTypeConfig[$type] ?? [];
            $sectionDefaults = $entry['sectionStyleDefaults'] ?? [];
            $result[] = [
                'type' => $type,
                'sectionStyleDefaults' => is_array($sectionDefaults) ? $sectionDefaults : [],
            ];
        }

        return $result;
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
