<?php

declare(strict_types=1);

namespace App\Controller;

use App\Application\Seo\PageSeoConfigService;
use App\Controller\Admin\LandingpageController as LandingpageSeoController;
use App\Domain\Page;
use App\Domain\Roles;
use App\Infrastructure\Database;
use App\Repository\NamespaceRepository;
use App\Service\CatalogService;
use App\Service\ConfigService;
use App\Service\EventService;
use App\Service\DomainService;
use App\Service\PageService;
use App\Service\ResultService;
use App\Service\SettingsService;
use App\Service\StripeService;
use App\Service\TeamService;
use App\Service\TenantService;
use App\Service\UrlService;
use App\Service\UserService;
use App\Service\VersionService;
use App\Service\MediaLibraryService;
use App\Service\ImageUploadService;
use App\Service\LandingMediaReferenceService;
use App\Service\LandingNewsService;
use App\Service\MarketingNewsletterConfigService;
use App\Service\NamespaceAccessService;
use App\Service\NamespaceResolver;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/**
 * Shows the main administration dashboard.
 */
class AdminController
{
    private const CHAT_SECRET_PLACEHOLDER = '__SECRET_PRESENT__';

    /**
     * Render the admin dashboard page.
     */
    public function __invoke(Request $request, Response $response): Response {
        $view = Twig::fromRequest($request);
        $csrf = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(16));
        $_SESSION['csrf_token'] = $csrf;
        $role = $_SESSION['user']['role'] ?? null;
        $pdo = $request->getAttribute('pdo');
        if (!$pdo instanceof PDO) {
            $pdo = Database::connectFromEnv();
        }
        $cfgSvc = new ConfigService($pdo);
        $eventSvc = new EventService($pdo);
        $events = $eventSvc->getAll();
        $settingsSvc = new SettingsService($pdo);
        $settings = $settingsSvc->getAll();
        $settingsForView = $settings;
        $token = isset($settingsForView['rag_chat_service_token'])
            ? trim((string) $settingsForView['rag_chat_service_token'])
            : '';
        $settingsForView['rag_chat_service_token_present'] = $token !== '' ? '1' : '0';
        if ($token !== '') {
            $settingsForView['rag_chat_service_token'] = self::CHAT_SECRET_PLACEHOLDER;
        }
        $versionSvc = new VersionService();
        $version = $versionSvc->getCurrentVersion();

        $params = $request->getQueryParams();
        if (array_key_exists('event', $params)) {
            $uid = (string) $params['event'];
            if ($eventSvc->getByUid($uid) === null) {
                return $response->withStatus(404);
            }
            $cfgSvc->setActiveEventUid($uid);
        } else {
            $uid = (string) $cfgSvc->getActiveEventUid();
        }

        if ($uid === '') {
            $cfg   = [];
            $event = null;
        } else {
            $cfg   = $cfgSvc->getConfigForEvent($uid);
            $event = $eventSvc->getByUid($uid);
        }
        $context = \Slim\Routing\RouteContext::fromRequest($request);
        $route   = $context->getRoute();
        $section = 'dashboard';
        if ($route !== null) {
            $pattern = $route->getPattern();
            $section = ltrim(substr($pattern, strlen('/admin')), '/');
            if ($section === '') {
                $section = 'dashboard';
            }
        }

        $results   = [];
        $catalogs  = [];
        $teams     = [];
        $users     = [];
        $pages     = [];
        $tenant    = null;
        $tenantSvc = null;
        $sub       = '';
        $initialTenantListHtml = '';

        $configSvc = new ConfigService($pdo);
        if (
            $uid !== ''
            && in_array($section, ['results', 'catalogs', 'questions', 'summary'], true)
        ) {
            $catalogSvc   = new CatalogService($pdo, $configSvc, null, '', $uid);
            $catalogsJson = $catalogSvc->read('catalogs.json');
            if ($catalogsJson !== null) {
                $catalogs = json_decode($catalogsJson, true) ?? [];
            }
        }

        if ($section === 'results' && $uid !== '') {
            $results  = (new ResultService($pdo))->getAll($uid);
            $catMap   = [];
            foreach ($catalogs as $c) {
                $name = $c['name'] ?? '';
                if (isset($c['uid'])) {
                    $catMap[$c['uid']] = $name;
                }
                if (isset($c['sort_order'])) {
                    $catMap[$c['sort_order']] = $name;
                }
                if (isset($c['slug'])) {
                    $catMap[$c['slug']] = $name;
                }
            }
            foreach ($results as &$row) {
                $cat = $row['catalog'] ?? '';
                if (isset($catMap[$cat])) {
                    $row['catalogName'] = $catMap[$cat];
                }
            }
            unset($row);
        }

        if (in_array($section, ['teams', 'summary'], true)) {
            $teams = (new TeamService($pdo, $configSvc))->getAll();
        }

        if (in_array($section, ['management', 'logins'], true)) {
            $users = (new UserService($pdo))->getAll();
        }

        $namespace = (new NamespaceResolver())->resolve($request)->getNamespace();
        $requestedScope = trim((string) ($request->getQueryParams()['scope'] ?? ''));
        $mediaScope = $role === Roles::ADMIN && $requestedScope === MediaLibraryService::SCOPE_PROJECT
            ? MediaLibraryService::SCOPE_PROJECT
            : ($role === Roles::ADMIN ? MediaLibraryService::SCOPE_GLOBAL : MediaLibraryService::SCOPE_PROJECT);
        $mediaNamespace = $mediaScope === MediaLibraryService::SCOPE_PROJECT ? $namespace : '';
        $uri = $request->getUri();
        $mainDomain = getenv('MAIN_DOMAIN')
            ?: getenv('DOMAIN')
            ?: $uri->getHost();
        $availableNamespaces = [];
        $pageContents = [];
        $domainChatDomains = [];
        $domainChatPages = [];
        $marketingNewsletterConfigs = [];
        $marketingNewsletterSlugs = [];
        $marketingNewsletterStyles = [];
        $landingNewsEntries = [];
        $pageTab = '';
        $landingNewsStatus = '';
        $selectedPageSlug = '';
        $seoPages = [];
        $seoConfig = [];
        $selectedSeoPage = null;
        $mediaLandingSlugs = [];

        $loadMarketingData = $section === 'pages' && $role === Roles::ADMIN;
        $loadNamespaces = $section === 'management' || $loadMarketingData;
        $loadMediaLandingSlugs = $section === 'media';
        if ($loadNamespaces) {
            $namespaceRepository = new NamespaceRepository($pdo);
            try {
                $availableNamespaces = $namespaceRepository->list();
            } catch (\RuntimeException $exception) {
                $availableNamespaces = [];
            }
            if (
                !array_filter(
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
        }

        if ($loadMarketingData || $loadMediaLandingSlugs) {
            $pageSvc = new PageService($pdo);
            $seoSvc = new PageSeoConfigService($pdo);
            $landingNewsService = new LandingNewsService($pdo);
            if ($section === 'media') {
                $landingReferenceService = new LandingMediaReferenceService(
                    $pageSvc,
                    $seoSvc,
                    $configSvc,
                    $landingNewsService
                );
                $mediaLandingSlugs = $landingReferenceService->getLandingSlugs($namespace);
            }
            if ($loadMarketingData) {
                $newsletterConfigService = new MarketingNewsletterConfigService($pdo);
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
                $marketingNewsletterConfigs = $newsletterConfigService->getAllGrouped($namespace);
                $marketingNewsletterSlugs = array_keys($marketingNewsletterConfigs);
                sort($marketingNewsletterSlugs);
                $marketingNewsletterStyles = $newsletterConfigService->getAllowedStyles();
                $pages = [];
                $pageContents = [];
                $allPages = $pageSvc->getAllForNamespace($namespace);
                foreach ($allPages as $page) {
                    $pages[] = [
                        'id' => $page->getId(),
                        'slug' => $page->getSlug(),
                        'title' => $page->getTitle(),
                        'content' => $page->getContent(),
                    ];
                    $pageContents[$page->getSlug()] = $page->getContent();
                }

                $marketingPages = $this->filterMarketingPages($allPages);
                $domainService = new DomainService($pdo);
                $domainChatDomains = $domainService->listDomains(includeInactive: true);

                $domainChatPages = [];
                $seenPageSlugs = [];
                foreach ($marketingPages as $page) {
                    $slug = $page->getSlug();
                    if ($slug === '' || isset($seenPageSlugs[$slug])) {
                        continue;
                    }

                    $seenPageSlugs[$slug] = true;
                    $domainChatPages[] = [
                        'slug' => $slug,
                        'title' => $page->getTitle(),
                        'type' => 'marketing',
                    ];
                }

                $pageTab = $this->resolvePageTab($params);
                $landingNewsStatus = $this->normalizeLandingNewsStatus($params);
                if ($landingNewsStatus !== '') {
                    $pageTab = 'landing-news';
                }

                $landingNewsEntries = $landingNewsService->getAll();
                if ($landingNewsEntries !== []) {
                    $allowedPageIds = [];
                    foreach ($allPages as $page) {
                        $allowedPageIds[$page->getId()] = true;
                    }
                    $landingNewsEntries = array_values(array_filter(
                        $landingNewsEntries,
                        static fn ($entry): bool => isset($allowedPageIds[$entry->getPageId()])
                    ));
                }

                $selectedSeoSlug = isset($params['seoPage']) ? (string) $params['seoPage'] : '';
                $selectedSeoPage = $this->selectSeoPage($marketingPages, $selectedSeoSlug);
                $seoPages = $this->buildSeoPageData(
                    $seoSvc,
                    $marketingPages,
                    $domainService,
                    $request->getUri()->getHost()
                );
                $seoConfig = $selectedSeoPage !== null && isset($seoPages[$selectedSeoPage->getId()])
                    ? $seoPages[$selectedSeoPage->getId()]['config']
                    : [];

                $requestedPageSlug = '';
                if (isset($params['pageSlug']) || isset($params['slug'])) {
                    $requestedPageSlug = trim((string) ($params['pageSlug'] ?? $params['slug'] ?? ''));
                }

                $selectedPageSlug = $selectedSeoPage?->getSlug() ?? '';
                $pageSlugs = array_values(array_filter(array_map(
                    static fn (array $page): string => $page['slug'],
                    $pages
                )));
                if ($requestedPageSlug !== '' && in_array($requestedPageSlug, $pageSlugs, true)) {
                    $selectedPageSlug = $requestedPageSlug;
                }
                if ($selectedPageSlug === '') {
                    $selectedPageSlug = $pages !== [] ? $pages[0]['slug'] : '';
                }
            }
        }

        $namespaceAccess = new NamespaceAccessService();
        $allowedNamespaces = $namespaceAccess->resolveAllowedNamespaces(is_string($role) ? $role : null);
        if ($availableNamespaces === []) {
            $namespaceRepository = new NamespaceRepository($pdo);
            try {
                $availableNamespaces = $namespaceRepository->list();
            } catch (\RuntimeException $exception) {
                $availableNamespaces = [];
            }
        }

        foreach ($availableNamespaces as $index => $entry) {
            $entry['namespace'] = strtolower(trim((string) $entry['namespace']));
            $availableNamespaces[$index] = $entry;
        }

        if (
            $namespaceAccess->shouldExposeNamespace(PageService::DEFAULT_NAMESPACE, $allowedNamespaces, $role)
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

        if (
            $namespaceAccess->shouldExposeNamespace($namespace, $allowedNamespaces, $role)
            && !array_filter(
                $availableNamespaces,
                static fn (array $entry): bool => $entry['namespace'] === $namespace
            )
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

        $availableNamespaces = $namespaceAccess->filterNamespaceEntries($availableNamespaces, $allowedNamespaces, $role);

        $domainType = $request->getAttribute('domainType');
        if ($domainType === 'main') {
            $base = Database::connectFromEnv();
            $tenantSvc = new TenantService($base);
            $tenant = $tenantSvc->getMainTenant();
        } else {
            $host = $request->getUri()->getHost();
            $sub  = explode('.', $host)[0];
            $base = Database::connectFromEnv();
            $tenantSvc = new TenantService($base);
            $tenant = $tenantSvc->getBySubdomain($sub);
        }

        $stripeSandbox = filter_var(getenv('STRIPE_SANDBOX'), FILTER_VALIDATE_BOOLEAN);
        $tenantSyncState = $tenantSvc->getSyncState();
        if (
            $section === 'tenants'
            && $domainType === 'main'
        ) {
            $tenants = $tenantSvc->getAll();
            $initialTenantListHtml = $view->fetch('admin/tenant_list.twig', [
                'tenants' => $tenants,
                'main_domain' => $mainDomain,
                'stripe_dashboard' => $stripeSandbox
                    ? 'https://dashboard.stripe.com/test'
                    : 'https://dashboard.stripe.com',
                'tenant_sync' => $tenantSyncState,
            ]);
        }

        if (
            $section === 'subscription'
            && $tenant !== null
            && ($tenant['stripe_customer_id'] ?? '') === ''
            && ($tenant['imprint_email'] ?? '') !== ''
            && StripeService::isConfigured()['ok']
        ) {
            $service = new StripeService();
            try {
                $cid = $service->findCustomerIdByEmail((string) $tenant['imprint_email']);
                if ($cid === null) {
                    $cid = $service->createCustomer(
                        (string) $tenant['imprint_email'],
                        $tenant['imprint_name'] ?? null
                    );
                }
                $tenant['stripe_customer_id'] = $cid;
                if ($sub !== '') {
                    $tenantSvc->updateProfile($sub, ['stripe_customer_id' => $cid]);
                } else {
                    $tenantSvc->updateProfile('main', ['stripe_customer_id' => $cid]);
                }
            } catch (\Throwable $e) {
                // ignore errors; admin page should still render
            }
        }

        $baseUrl = UrlService::determineBaseUrl($request);
        $eventUrl = $uid !== '' ? $baseUrl . '/?event=' . rawurlencode($uid) : $baseUrl;
        $resultsUrl = $baseUrl . '/summary';
        if ($uid !== '') {
            $resultsUrl .= '?event=' . rawurlencode($uid) . '&results=1';
        } else {
            $resultsUrl .= '?results=1';
        }

        $mediaService = new MediaLibraryService($configSvc, new ImageUploadService());
        $mediaLimits = $mediaService->getLimits();

          return $view->render($response, 'admin.twig', [
              'config' => $cfg,
              'settings' => $settingsForView,
              'results' => $results,
              'catalogs' => $catalogs,
              'teams' => $teams,
              'users' => $users,
              'available_namespaces' => $availableNamespaces,
              'default_namespace' => PageService::DEFAULT_NAMESPACE,
              'events' => $events,
              'roles' => Roles::ALL,
              'baseUrl' => $baseUrl,
              'eventUrl' => $eventUrl,
              'resultsUrl' => $resultsUrl,
              'main_domain' => $mainDomain,
              'event' => $event,
              'role' => $role,
              'pages' => $pages,
              'page_contents' => $pageContents,
              'seo_config' => $seoConfig,
              'seo_pages' => array_values($seoPages),
              'selectedSeoPageId' => $selectedSeoPage?->getId(),
              'selectedPageSlug' => $selectedPageSlug,
              'landingNewsEntries' => $landingNewsEntries,
              'landingNewsStatus' => $landingNewsStatus,
              'pageTab' => $pageTab,
              'domain_chat_domains' => $domainChatDomains,
              'domain_chat_pages' => $domainChatPages,
              'marketingNewsletterConfigs' => $marketingNewsletterConfigs,
              'marketingNewsletterSlugs' => $marketingNewsletterSlugs,
              'marketingNewsletterStyles' => $marketingNewsletterStyles,
              'marketingNewsletterNamespace' => $namespace,
              'pageNamespace' => $namespace,
              'domainType' => $request->getAttribute('domainType'),
              'tenant' => $tenant,
              'tenant_sync' => $tenantSyncState,
              'stripe_configured' => StripeService::isConfigured()['ok'],
              'stripe_sandbox' => $stripeSandbox,
              'initialTenantListHtml' => $initialTenantListHtml,
              'currentPath' => $request->getUri()->getPath(),
              'username' => $_SESSION['user']['username'] ?? '',
              'csrf_token' => $csrf,
              'version' => $version,
              'mediaLimits' => $mediaLimits,
              'mediaLandingSlugs' => $mediaLandingSlugs,
              'mediaScope' => $mediaScope,
              'mediaNamespace' => $mediaNamespace,
              'ragChatSecretPlaceholder' => self::CHAT_SECRET_PLACEHOLDER,
          ]);
    }

    /**
     * @param Page[] $pages
     * @return Page[]
     */
    private function filterMarketingPages(array $pages): array {
        return array_values(array_filter(
            $pages,
            static fn (Page $page): bool => !in_array($page->getSlug(), LandingpageSeoController::EXCLUDED_SLUGS, true)
        ));
    }

    /**
     * @param Page[] $pages
     */
    private function selectSeoPage(array $pages, string $slug): ?Page {
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
     * Determine the active tab within the pages section.
     */
    private function resolvePageTab(array $params): string
    {
        $default = 'seo';
        if (!isset($params['pageTab'])) {
            return $default;
        }

        $candidate = (string) $params['pageTab'];
        $allowed = ['seo', 'content', 'landing-news', 'wiki'];

        return in_array($candidate, $allowed, true) ? $candidate : $default;
    }

    /**
     * Normalize the status flag for landing news operations.
     */
    private function normalizeLandingNewsStatus(array $params): string
    {
        if (!isset($params['landingNewsStatus'])) {
            return '';
        }

        $status = (string) $params['landingNewsStatus'];
        $allowed = ['created', 'updated', 'deleted'];

        return in_array($status, $allowed, true) ? $status : '';
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
                'slug' => $page->getSlug(),
                'title' => $page->getTitle(),
                'domains' => $pageDomains,
                'config' => $configData,
            ];
        }

        return $result;
    }
}
