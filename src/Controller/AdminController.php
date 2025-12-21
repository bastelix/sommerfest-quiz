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
use App\Service\DomainStartPageService;
use App\Service\PageService;
use App\Service\ResultService;
use App\Service\SettingsService;
use App\Service\StripeService;
use App\Service\TeamService;
use App\Service\TenantService;
use App\Service\TranslationService;
use App\Service\UrlService;
use App\Service\UserService;
use App\Service\VersionService;
use App\Service\MediaLibraryService;
use App\Service\ImageUploadService;
use App\Service\LandingMediaReferenceService;
use App\Service\LandingNewsService;
use App\Service\MarketingNewsletterConfigService;
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

        if ($section === 'management') {
            $users = (new UserService($pdo))->getAll();
            $namespaceRepository = new NamespaceRepository($pdo);
            try {
                $availableNamespaces = $namespaceRepository->list();
            } catch (\RuntimeException $exception) {
                $availableNamespaces = [];
            }
            if (!array_filter(
                $availableNamespaces,
                static fn (array $entry): bool => ($entry['namespace'] ?? '') === PageService::DEFAULT_NAMESPACE
            )) {
                $availableNamespaces[] = [
                    'namespace' => PageService::DEFAULT_NAMESPACE,
                    'label' => null,
                    'is_active' => true,
                    'created_at' => null,
                    'updated_at' => null,
                ];
            }
        }

        $pageSvc = new PageService($pdo);
        $seoSvc = new PageSeoConfigService($pdo);
        $landingNewsService = new LandingNewsService($pdo);
        $landingReferenceService = new LandingMediaReferenceService($pageSvc, $seoSvc, $configSvc, $landingNewsService);
        $newsletterConfigService = new MarketingNewsletterConfigService($pdo);
        $namespace = (new NamespaceResolver())->resolve($request)->getNamespace();
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
        $translator = $request->getAttribute('translator');
        $translationService = $translator instanceof TranslationService ? $translator : null;
        $domainService = new DomainStartPageService($pdo);
        $uri = $request->getUri();
        $mainDomain = getenv('MAIN_DOMAIN')
            ?: getenv('DOMAIN')
            ?: $uri->getHost();
        $domainStartPageOptions = $domainService->getStartPageOptions($pageSvc);
        if ($translationService !== null) {
            $domainStartPageOptions['help'] = $translationService->translate('option_help_page');
            $domainStartPageOptions['events'] = $translationService->translate('option_events_page');
        }
        $coreOrder = ['help', 'events'];
        $orderedDomainOptions = [];
        foreach ($coreOrder as $slug) {
            if (isset($domainStartPageOptions[$slug])) {
                $orderedDomainOptions[$slug] = $domainStartPageOptions[$slug];
                unset($domainStartPageOptions[$slug]);
            }
        }
        $domainStartPageOptions = $orderedDomainOptions + $domainStartPageOptions;

        $marketingConfig = getenv('MARKETING_DOMAINS') ?: '';
        $domainChatDomains = $domainService->determineDomains($mainDomain, (string) $marketingConfig, $uri->getHost());

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

        $pageTab = $this->resolvePageTab($params);
        $landingNewsStatus = $this->normalizeLandingNewsStatus($params);
        if ($landingNewsStatus !== '') {
            $pageTab = 'landing-news';
        }

        $landingNewsEntries = [];
        if ($section === 'pages' && $role === Roles::ADMIN) {
            $landingNewsEntries = $landingNewsService->getAll();
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

        $selectedPageSlug = $selectedSeoPage?->getSlug() ?? '';
        if ($selectedPageSlug === '') {
            $selectedPageSlug = $pages[0]['slug'] ?? '';
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
              'available_namespaces' => $availableNamespaces ?? [],
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
              'domain_start_page_options' => $domainStartPageOptions,
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
              'mediaLandingSlugs' => $landingReferenceService->getLandingSlugs($namespace),
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
        DomainStartPageService $domainService,
        string $host
    ): array {
        $mappings = $domainService->getAllMappings();
        $domainsBySlug = [];
        foreach ($mappings as $domain => $config) {
            $slug = trim($config['start_page']);
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
}
