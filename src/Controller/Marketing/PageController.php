<?php

declare(strict_types=1);

namespace App\Controller\Marketing;

use App\Application\Seo\PageSeoConfigService;
use App\Domain\Page;
use App\Service\CmsMenuService;
use App\Service\CmsPageMenuService;
use App\Service\CmsPageWikiArticleService;
use App\Service\CmsPageWikiSettingsService;
use App\Service\ConfigService;
use App\Service\EffectsPolicyService;
use App\Service\NamespaceAppearanceService;
use App\Service\NamespaceRenderContextService;
use App\Service\NamespaceResolver;
use App\Service\PageContentLoader;
use App\Service\PageModuleService;
use App\Service\PageService;
use App\Service\ProvenExpertRatingService;
use App\Service\ProjectSettingsService;
use App\Service\LandingNewsService;
use App\Service\MailService;
use App\Service\MarketingSlugResolver;
use App\Service\TurnstileConfig;
use App\Infrastructure\Database;
use App\Support\BasePathHelper;
use App\Support\FeatureFlags;
use DateTimeImmutable;
use DateTimeZone;
use IntlDateFormatter;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;

use function dirname;
use function file_get_contents;
use function html_entity_decode;
use function htmlspecialchars;
use function in_array;
use function is_array;
use function is_numeric;
use function is_readable;
use function json_decode;
use function json_encode;
use function max;
use function preg_match;
use function preg_replace;
use function rawurlencode;
use function str_contains;
use function str_starts_with;
use function strlen;
use function strpos;
use function substr;
use function trim;

class PageController
{
    private PageService $pages;
    private PageSeoConfigService $seo;
    private ?string $slug;
    private PageContentLoader $contentLoader;
    private PageModuleService $pageModules;
    private NamespaceAppearanceService $namespaceAppearance;
    private NamespaceRenderContextService $namespaceRenderContext;
    private NamespaceResolver $namespaceResolver;
    private ProjectSettingsService $projectSettings;
    private ConfigService $configService;
    private EffectsPolicyService $effectsPolicy;
    private CmsPageMenuService $cmsMenu;
    private TurnstileConfig $turnstileConfig;
    private ProvenExpertRatingService $provenExpert;
    private LandingNewsService $landingNews;
    private CmsPageWikiSettingsService $wikiSettings;
    private CmsPageWikiArticleService $wikiArticles;

    public function __construct(
        ?string $slug = null,
        ?PageService $pages = null,
        ?PageSeoConfigService $seo = null,
        ?TurnstileConfig $turnstileConfig = null,
        ?ProvenExpertRatingService $provenExpert = null,
        ?LandingNewsService $landingNews = null,
        ?CmsPageWikiSettingsService $wikiSettings = null,
        ?CmsPageWikiArticleService $wikiArticles = null,
        ?PageContentLoader $contentLoader = null,
        ?PageModuleService $pageModules = null,
        ?NamespaceAppearanceService $namespaceAppearance = null,
        ?NamespaceRenderContextService $namespaceRenderContext = null,
        ?NamespaceResolver $namespaceResolver = null,
        ?ProjectSettingsService $projectSettings = null,
        ?ConfigService $configService = null,
        ?EffectsPolicyService $effectsPolicy = null,
        ?CmsPageMenuService $cmsMenu = null
    ) {
        $this->slug = $slug;
        $pdo = Database::connectFromEnv();
        $this->pages = $pages ?? new PageService($pdo);
        $this->seo = $seo ?? new PageSeoConfigService($pdo);
        $this->turnstileConfig = $turnstileConfig ?? TurnstileConfig::fromEnv();
        $this->provenExpert = $provenExpert ?? new ProvenExpertRatingService();
        $this->landingNews = $landingNews ?? new LandingNewsService($pdo);
        $this->wikiSettings = $wikiSettings ?? new CmsPageWikiSettingsService($pdo);
        $this->wikiArticles = $wikiArticles ?? new CmsPageWikiArticleService($pdo);
        $this->contentLoader = $contentLoader ?? new PageContentLoader();
        $this->pageModules = $pageModules ?? new PageModuleService();
        $this->namespaceAppearance = $namespaceAppearance ?? new NamespaceAppearanceService();
        $this->namespaceRenderContext = $namespaceRenderContext ?? new NamespaceRenderContextService();
        $this->namespaceResolver = $namespaceResolver ?? new NamespaceResolver();
        $this->projectSettings = $projectSettings ?? new ProjectSettingsService($pdo);
        $this->configService = $configService ?? new ConfigService($pdo);
        $this->effectsPolicy = $effectsPolicy ?? new EffectsPolicyService($this->configService);
        $this->cmsMenu = $cmsMenu ?? new CmsPageMenuService($pdo, $this->pages);
    }

    public function __invoke(Request $request, Response $response, array $args = []): Response
    {
        error_log('CMS CONTROLLER HIT: ' . ($args['slug'] ?? 'no-slug'));
        $templateSlug = $this->slug ?? (string) ($args['slug'] ?? '');
        if ($templateSlug === '' || !preg_match('/^[a-z0-9-]+$/', $templateSlug)) {
            return $response->withStatus(404);
        }

        $locale = (string) $request->getAttribute('lang');
        $contentSlug = $this->resolveLocalizedSlug($templateSlug, $locale);

        $pdo = $request->getAttribute('pdo');
        if (!$pdo instanceof PDO) {
            $pdo = Database::connectFromEnv();
        }

        $this->pages = new PageService($pdo);
        $this->seo = new PageSeoConfigService($pdo, null, null, null, null, $this->pages);
        $this->pageModules = new PageModuleService($pdo, $this->pages);
        $this->cmsMenu = new CmsPageMenuService($pdo, $this->pages);

        $resolvedNamespace = (string) ($request->getAttribute('namespace') ?? '');
        if ($resolvedNamespace === '') {
            $namespaceContext = $this->namespaceResolver->resolve($request);
            $resolvedNamespace = $namespaceContext->getNamespace();
        }

        $page = $this->pages->findByKey($resolvedNamespace, $contentSlug);
        if ($page === null && $contentSlug !== $templateSlug) {
            $page = $this->pages->findByKey($resolvedNamespace, $templateSlug);
            $contentSlug = $templateSlug;
        }
        if ($page === null) {
            return $response->withStatus(404);
        }

        $contentNamespace = $page->getNamespace();
        $pageNamespace = $contentNamespace !== ''
            ? $contentNamespace
            : ($resolvedNamespace !== '' ? $resolvedNamespace : PageService::DEFAULT_NAMESPACE);

        $html = $this->contentLoader->load($page);
        $basePath = BasePathHelper::normalize(RouteContext::fromRequest($request)->getBasePath());
        $html = str_replace('{{ basePath }}', $basePath, $html);

        $csrf = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(16));
        $_SESSION['csrf_token'] = $csrf;
        $html = str_replace('{{ csrf_token }}', $csrf, $html);

        $design = $this->loadDesign($pageNamespace);
        $pageType = $page->getType();
        $pageFeatures = $this->resolvePageFeatures($page, $templateSlug, $design);
        $marketingPayload = $this->applyMarketingFeatures(
            $request,
            $page,
            $templateSlug,
            $pageNamespace,
            $locale,
            $basePath,
            $html,
            $pageFeatures
        );
        $html = $marketingPayload['html'];

        $pageBlocks = $this->extractPageBlocks($html);

        $renderContext = $this->namespaceRenderContext->build($pageNamespace);
        $theme = is_string($renderContext['design']['theme'] ?? null)
            ? (string) $renderContext['design']['theme']
            : 'light';

        $design['theme'] = $theme;
        $view = Twig::fromRequest($request);
        $config = $this->seo->load($page->getId());
        $globals = $view->getEnvironment()->getGlobals();
        $canonicalFallback = isset($globals['canonicalUrl']) ? (string) $globals['canonicalUrl'] : null;
        $canonicalUrl = $config?->getCanonicalUrl() ?? $canonicalFallback;

        $cmsMenuItems = $this->cmsMenu->getMenuTreeForSlug(
            $pageNamespace,
            $page->getSlug(),
            $locale,
            true
        );

        if (is_array($marketingPayload['featureData']['wikiMenu'] ?? null)) {
            $wikiMenu = $marketingPayload['featureData']['wikiMenu'];
            $cmsMenuItems = $this->appendWikiMenuItem(
                $cmsMenuItems,
                (string) ($wikiMenu['label'] ?? 'Dokumentation'),
                (string) ($wikiMenu['url'] ?? '#')
            );
        }

        $cookieSettings = $this->projectSettings->getCookieConsentSettings($pageNamespace);
        $cookieConsentConfig = $this->buildCookieConsentConfig($cookieSettings, $locale);
        $privacyUrl = $this->projectSettings->resolvePrivacyUrlForSettings($cookieSettings, $locale, $basePath);
        $headerConfig = $this->buildHeaderConfig($cookieSettings);
        $headerLogo = $this->buildHeaderLogoSettings($cookieSettings, $basePath);

        $cmsMenuService = new CmsMenuService($pdo, $this->cmsMenu);
        $menu = $cmsMenuService->getMenuForNamespace($pageNamespace, $locale);

        $navigation = $this->loadNavigationSections(
            $pageNamespace,
            $page->getSlug(),
            $locale,
            $basePath,
            $cmsMenuItems,
            $menu
        );
        $cmsMainNavigation = $navigation['main'];

        if ($menu === [] && $cmsMenuItems !== []) {
            $menu = $cmsMenuItems;
        }

        $pageTypeConfig = $design['config']['pageTypes'] ?? [];
        $sectionStyleDefaults = [];
        if ($pageType !== null && isset($pageTypeConfig[$pageType]) && is_array($pageTypeConfig[$pageType])) {
            $sectionStyleDefaults = $pageTypeConfig[$pageType]['sectionStyleDefaults'] ?? [];
        }

        $pageJson = [
            'namespace' => $pageNamespace,
            'contentNamespace' => $pageNamespace,
            'slug' => $page->getSlug(),
            'type' => $pageType,
            'sectionStyleDefaults' => $sectionStyleDefaults,
            'blocks' => $pageBlocks ?? [],
            'features' => $pageFeatures,
            'featureData' => $marketingPayload['featureData'],
            'design' => $design,
            'renderContext' => $renderContext,
        ];

        if ($this->wantsJson($request)) {
            return $this->renderJsonPage($response, [
                'namespace' => $pageNamespace,
                'contentNamespace' => $pageNamespace,
                'slug' => $page->getSlug(),
                'blocks' => $pageBlocks ?? [],
                'design' => $design,
                'renderContext' => $renderContext,
                'content' => $html,
                'pageType' => $pageType,
                'sectionStyleDefaults' => $sectionStyleDefaults,
                'menu' => $menu,
                'mainNavigation' => $cmsMainNavigation,
                'navigation' => $navigation,
                'featureFlags' => $pageFeatures,
                'featureData' => $marketingPayload['featureData'],
            ]);
        }

        $data = [
            'content' => $html,
            'pageBlocks' => $pageBlocks,
            'pageJson' => $pageJson,
            'pageFavicon' => $config?->getFaviconPath(),
            'metaTitle' => $config?->getMetaTitle(),
            'metaDescription' => $config?->getMetaDescription(),
            'canonicalUrl' => $canonicalUrl,
            'robotsMeta' => $config?->getRobotsMeta(),
            'ogTitle' => $config?->getOgTitle(),
            'ogDescription' => $config?->getOgDescription(),
            'ogImage' => $config?->getOgImage(),
            'schemaJson' => $config?->getSchemaJson(),
            'hreflang' => $config?->getHreflang(),
            'csrf_token' => $csrf,
            'cmsSlug' => $templateSlug,
            'pageModules' => $this->pageModules->getModulesByPosition($page->getId()),
            'cookieConsentConfig' => $cookieConsentConfig,
            'privacyUrl' => $privacyUrl,
            'namespace' => $pageNamespace,
            'pageNamespace' => $pageNamespace,
            'contentNamespace' => $pageNamespace,
            'config' => $design['config'],
            'headerConfig' => $headerConfig,
            'headerLogo' => $headerLogo,
            'appearance' => $design['appearance'],
            'design' => $design,
            'renderContext' => $renderContext,
            'pageType' => $pageType,
            'sectionStyleDefaults' => $sectionStyleDefaults,
            'pageTheme' => $theme,
            'menu' => $menu,
            'cmsMainNavigation' => $cmsMainNavigation,
            'cmsFooterNavigation' => $navigation['footer'],
            'cmsLegalNavigation' => $navigation['legal'],
            'cmsSidebarNavigation' => $navigation['sidebar'],
            'cmsChatEndpoint' => $marketingPayload['featureData']['chatEndpoint'] ?? null,
            'landingNews' => $marketingPayload['featureData']['landingNews'],
            'landingNewsBasePath' => $marketingPayload['featureData']['landingNewsBasePath'],
            'landingNewsIndexUrl' => $marketingPayload['featureData']['landingNewsBasePath'] !== null
                ? $basePath . $marketingPayload['featureData']['landingNewsBasePath']
                : null,
            'calhelpModules' => $marketingPayload['featureData']['calhelpModules'],
            'calhelpUsecases' => $marketingPayload['featureData']['calhelpUsecases'],
            'calhelpNewsPlaceholder' => $marketingPayload['featureData']['calhelpNewsPlaceholder'],
            'calhelpNewsPlaceholderActive' => $marketingPayload['featureData']['calhelpNewsPlaceholderActive'],
            'cmsWikiMenu' => $marketingPayload['featureData']['wikiMenu'],
            'cmsWikiArticles' => $marketingPayload['featureData']['wikiArticles'],
            'provenExpertRating' => $marketingPayload['featureData']['provenExpertRating'],
            'maintenanceWindowLabel' => $marketingPayload['featureData']['maintenanceWindowLabel'],
            'laborAssets' => $marketingPayload['featureData']['laborAssets'],
            'turnstileEnabled' => $marketingPayload['featureData']['turnstileEnabled'],
            'turnstileSiteKey' => $marketingPayload['featureData']['turnstileSiteKey'],
            'featureFlags' => $pageFeatures,
        ];

        return $view->render($response, 'pages/render.twig', $data);
    }

    /**
     * Determine whether the caller explicitly requested a JSON payload.
     */
    private function wantsJson(Request $request): bool
    {
        $query = $request->getQueryParams();
        $format = isset($query['format']) ? strtolower((string) $query['format']) : '';
        $jsonFlag = isset($query['json']) ? strtolower((string) $query['json']) : '';

        if ($format === 'json') {
            return true;
        }

        if (in_array($jsonFlag, ['1', 'true'], true)) {
            return true;
        }

        $accept = strtolower($request->getHeaderLine('Accept'));
        return str_contains($accept, 'application/json');
    }

    /**
     * Render a CMS page payload without embedding it into the DOM.
     *
     * @param array{namespace: string, contentNamespace: string, slug: string, blocks: array<int, mixed>, design: array<string,mixed>, content: string, menu?: array<int, mixed>, navigation?: array<string, mixed>, mainNavigation?: array<int, mixed>, pageType?: ?string, sectionStyleDefaults?: array<string, mixed>, renderContext?: array<string, mixed>, featureFlags?: array<string, bool>, featureData?: array<string, mixed>} $data
     */
    private function renderJsonPage(Response $response, array $data): Response
    {
        [
            'namespace' => $namespace,
            'contentNamespace' => $contentNamespace,
            'slug' => $slug,
            'blocks' => $blocks,
            'design' => $design,
            'content' => $content,
            'pageType' => $pageType,
            'sectionStyleDefaults' => $sectionStyleDefaults,
            'menu' => $menu,
            'navigation' => $navigation,
            'mainNavigation' => $mainNavigation,
            'renderContext' => $renderContext,
            'featureFlags' => $featureFlags,
            'featureData' => $featureData,
        ] = $data + ['menu' => [], 'navigation' => [], 'mainNavigation' => [], 'pageType' => null, 'sectionStyleDefaults' => [], 'renderContext' => [], 'featureFlags' => [], 'featureData' => []];

        $payload = [
            'namespace' => $namespace,
            'contentNamespace' => $contentNamespace,
            'slug' => $slug,
            'blocks' => $blocks,
            'design' => $design,
            'renderContext' => $renderContext,
            'content' => $content,
            'pageType' => $pageType,
            'sectionStyleDefaults' => $sectionStyleDefaults,
            'menu' => $menu,
            'mainNavigation' => $mainNavigation,
            'navigation' => $navigation,
            'featureFlags' => $featureFlags,
            'featureData' => $featureData,
        ];

        $response->getBody()->write(json_encode($payload, JSON_PRETTY_PRINT));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * @return array<string, bool>
     */
    private function resolvePageFeatures(Page $page, string $templateSlug, array $design): array
    {
        $features = $this->defaultFeaturesForSlug($templateSlug);

        $pageType = $page->getType();
        if ($pageType !== null) {
            $pageTypeConfig = $design['config']['pageTypes'][$pageType]['features'] ?? [];
            if (is_array($pageTypeConfig)) {
                foreach ($pageTypeConfig as $feature => $enabled) {
                    if ($feature === '' || (!is_bool($enabled) && !is_numeric($enabled))) {
                        continue;
                    }

                    $features[$feature] = (bool) $enabled;
                }
            }
        }

        return $features;
    }

    /**
     * @return array<string, bool>
     */
    private function defaultFeaturesForSlug(string $templateSlug): array
    {
        $defaults = [
            'contactTurnstile' => false,
            'chatEndpoint' => false,
            'landingNews' => false,
            'calhelpContent' => false,
            'wikiMenu' => false,
            'provenExpert' => false,
            'maintenanceWindow' => false,
            'laborAssets' => false,
        ];

        $baseSlug = MarketingSlugResolver::resolveBaseSlug($templateSlug);

        if (in_array($baseSlug, ['calserver', 'calhelp', 'landing', 'future-is-green'], true)) {
            $defaults['contactTurnstile'] = true;
            $defaults['landingNews'] = true;
        }

        if (in_array($baseSlug, ['calserver', 'calhelp'], true)) {
            $defaults['chatEndpoint'] = true;
        }

        if ($baseSlug === 'calhelp') {
            $defaults['calhelpContent'] = true;
            $defaults['wikiMenu'] = true;
        }

        if (in_array($baseSlug, ['calserver', 'landing'], true)) {
            $defaults['provenExpert'] = true;
        }

        if ($baseSlug === 'calserver-maintenance') {
            $defaults['maintenanceWindow'] = true;
        }

        if ($baseSlug === 'labor') {
            $defaults['laborAssets'] = true;
        }

        return $defaults;
    }

    /**
     * @param array<string, bool> $pageFeatures
     * @return array{html: string, featureData: array<string, mixed>}
     */
    private function applyMarketingFeatures(
        Request $request,
        Page $page,
        string $templateSlug,
        string $contentNamespace,
        string $locale,
        string $basePath,
        string $html,
        array $pageFeatures
    ): array {
        $data = [
            'chatEndpoint' => null,
            'landingNews' => [],
            'landingNewsBasePath' => null,
            'calhelpModules' => null,
            'calhelpUsecases' => null,
            'calhelpNewsPlaceholder' => null,
            'calhelpNewsPlaceholderActive' => false,
            'wikiMenu' => null,
            'wikiArticles' => null,
            'provenExpertRating' => null,
            'maintenanceWindowLabel' => null,
            'laborAssets' => null,
            'turnstileEnabled' => false,
            'turnstileSiteKey' => null,
        ];

        if (($pageFeatures['chatEndpoint'] ?? false) === true) {
            $chatPath = sprintf('/%s/chat', $templateSlug);
            if (str_starts_with($request->getUri()->getPath(), '/m/')) {
                $chatPath = sprintf('/m/%s/chat', $templateSlug);
            }

            $data['chatEndpoint'] = $basePath . $chatPath;
            $html = str_replace('data-calserver-chat-open', 'data-marketing-chat-open', $html);
            $html = str_replace('aria-controls="calserver-chat-modal"', 'aria-controls="marketing-chat-modal"', $html);
        }

        $calhelpNewsPlaceholderActive = false;
        if (($pageFeatures['calhelpContent'] ?? false) === true) {
            $newsReplacement = $this->replaceCalhelpNewsSection($html, '__CALHELP_NEWS_SECTION__');
            $html = $newsReplacement['html'];
            $calhelpNewsPlaceholderActive = $newsReplacement['replaced'];

            $moduleExtraction = $this->extractCalhelpModules($html);
            $html = $moduleExtraction['html'];
            $data['calhelpModules'] = $moduleExtraction['data'];

            $usecaseExtraction = $this->extractCalhelpUsecases($html);
            $html = $usecaseExtraction['html'];
            $data['calhelpUsecases'] = $usecaseExtraction['data'];
        }

        if (($pageFeatures['landingNews'] ?? false) === true) {
            $landingNewsOwnerSlug = $page->getSlug();
            $landingNews = $this->landingNews->getPublishedForPage($page->getId(), 3);

            if ($landingNews === []) {
                $baseSlug = MarketingSlugResolver::resolveBaseSlug($landingNewsOwnerSlug);
                if ($baseSlug !== $landingNewsOwnerSlug) {
                    $basePage = $this->pages->findByKey($contentNamespace, $baseSlug);
                    if ($basePage !== null) {
                        $fallbackNews = $this->landingNews->getPublishedForPage($basePage->getId(), 3);
                        if ($fallbackNews !== []) {
                            $landingNews = $fallbackNews;
                            $landingNewsOwnerSlug = $baseSlug;
                        }
                    }
                }
            }

            if ($landingNews !== []) {
                $data['landingNews'] = $landingNews;
                $data['landingNewsBasePath'] = $this->buildNewsBasePath($request, $landingNewsOwnerSlug);
            }
        }

        if (($pageFeatures['contactTurnstile'] ?? false) === true) {
            $placeholderToken = '{{ turnstile_widget }}';
            $hadPlaceholder = str_contains($html, $placeholderToken);

            $widgetMarkup = '';
            if ($this->turnstileConfig->isEnabled()) {
                $siteKey = $this->turnstileConfig->getSiteKey() ?? '';
                $widgetMarkup = sprintf(
                    '<div class="cf-turnstile" data-sitekey="%s" data-callback="contactTurnstileSuccess" '
                    . 'data-error-callback="contactTurnstileError" data-expired-callback="contactTurnstileExpired"></div>',
                    htmlspecialchars($siteKey, ENT_QUOTES)
                );
                $data['turnstileEnabled'] = true;
                $data['turnstileSiteKey'] = $this->turnstileConfig->getSiteKey();
            }

            $html = str_replace($placeholderToken, $widgetMarkup, $html);

            if (!MailService::isConfigured()) {
                $html = preg_replace(
                    '/<form id="contact-form"[\s\S]*?<\/form>/',
                    '<p class="uk-text-center">Kontaktformular derzeit nicht verfügbar.</p>',
                    $html
                ) ?? $html;
            } else {
                $html = $this->ensureTurnstileMarkup($html, $widgetMarkup, $hadPlaceholder);
                $html = $this->ensureNewsletterOptIn($html, $templateSlug, $locale);
            }
        }

        $data['calhelpNewsPlaceholder'] = $calhelpNewsPlaceholderActive ? '__CALHELP_NEWS_SECTION__' : null;
        $data['calhelpNewsPlaceholderActive'] = $calhelpNewsPlaceholderActive;

        if (($pageFeatures['wikiMenu'] ?? false) === true) {
            $wikiSlug = $page->getSlug();
            $wikiPage = $page;
            $baseWikiSlug = MarketingSlugResolver::resolveBaseSlug($wikiSlug);
            if ($baseWikiSlug !== $wikiSlug) {
                $baseWikiPage = $this->pages->findByKey($contentNamespace, $baseWikiSlug);
                if ($baseWikiPage !== null) {
                    $wikiPage = $baseWikiPage;
                    $wikiSlug = $baseWikiSlug;
                }
            }

            $wikiSettings = $this->wikiSettings->getSettingsForPage($wikiPage->getId());
            if (FeatureFlags::wikiEnabled() && $wikiSettings->isActive()) {
                $wikiArticles = $this->wikiArticles->getPublishedArticles($wikiPage->getId(), $locale);
                if ($wikiArticles !== []) {
                    $label = $wikiSettings->getMenuLabelForLocale($locale) ?? 'Dokumentation';
                    $wikiUrl = sprintf('%s/pages/%s/wiki', $basePath, $wikiSlug);
                    $data['wikiMenu'] = [
                        'label' => $label,
                        'url' => $wikiUrl,
                    ];
                    $data['wikiArticles'] = $wikiArticles;
                }
            }
        }

        if (($pageFeatures['provenExpert'] ?? false) === true) {
            $data['provenExpertRating'] = $this->provenExpert->getAggregateRatingMarkup();
        }

        if (($pageFeatures['maintenanceWindow'] ?? false) === true) {
            $data['maintenanceWindowLabel'] = $this->buildMaintenanceWindowLabel($locale);
        }

        if (($pageFeatures['laborAssets'] ?? false) === true) {
            $data['laborAssets'] = $this->buildLaborAssetUrls($basePath);
        }

        return ['html' => $html, 'featureData' => $data];
    }

    /**
     * @return array{main: array<int, array<string, mixed>>, footer: array<int, array<string, mixed>>, legal: array<int, array<string, mixed>>, sidebar: array<int, array<string, mixed>>}
     */
    private function loadNavigationSections(
        string $namespace,
        string $slug,
        string $locale,
        string $basePath,
        array $cmsMenuItems,
        array $menu
    ): array {
        $navigation = $this->loadNavigationFromContent($namespace, $slug, $locale, $basePath);

        $mainNavigation = $navigation['main'];
        if ($mainNavigation === []) {
            $mainNavigation = $this->mapMenuItemsToLinks($menu, $basePath);
        }

        $footerNavigation = $navigation['footer'];
        if ($footerNavigation === []) {
            $footerNavigation = $this->mapMenuItemsToLinks($cmsMenuItems, $basePath);
        }

        $legalNavigation = $navigation['legal'];

        $sidebarNavigation = $navigation['sidebar'];
        if ($sidebarNavigation === []) {
            $sidebarNavigation = $this->mapMenuItemsToLinks($cmsMenuItems, $basePath);
        }

        return [
            'main' => $mainNavigation,
            'footer' => $footerNavigation,
            'legal' => $legalNavigation,
            'sidebar' => $sidebarNavigation,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $menuItems
     * @return array<int, array<string, mixed>>
     */
    private function mapMenuItemsToLinks(array $menuItems, string $basePath): array
    {
        $links = [];
        foreach ($menuItems as $item) {
            $label = isset($item['label']) ? trim((string) $item['label']) : '';
            $href = isset($item['href']) ? trim((string) $item['href']) : '';
            if ($label === '' || $href === '') {
                continue;
            }

            $children = [];
            if (isset($item['children']) && is_array($item['children'])) {
                $children = $this->mapMenuItemsToLinks($item['children'], $basePath);
            }

            $links[] = [
                'label' => $label,
                'href' => $this->normalizeMenuHref($href, $basePath),
                'isExternal' => (bool) ($item['isExternal'] ?? false),
                'children' => $children,
            ];
        }

        return $links;
    }

    private function normalizeMenuHref(string $href, string $basePath): string
    {
        $normalizedBase = rtrim($basePath, '/');
        $normalizedHref = trim($href);

        if (str_starts_with($normalizedHref, 'http://') || str_starts_with($normalizedHref, 'https://')) {
            return $normalizedHref;
        }

        if ($normalizedHref === '') {
            return $basePath;
        }

        return ($normalizedBase !== '' ? $normalizedBase : '') . '/' . ltrim($normalizedHref, '/');
    }

    /**
     * @return array{main: array<int, array<string, mixed>>, footer: array<int, array<string, mixed>>, legal: array<int, array<string, mixed>>, sidebar: array<int, array<string, mixed>>}
     */
    private function loadNavigationFromContent(
        string $namespace,
        string $slug,
        string $locale,
        string $basePath
    ): array {
        $baseDir = dirname(__DIR__, 3) . '/content/navigation';
        $normalizedSlug = trim($slug);
        $normalizedLocale = trim($locale) !== '' ? trim($locale) : 'de';

        $candidates = [
            sprintf('%s/%s/%s.%s.json', $baseDir, $namespace, $normalizedSlug, $normalizedLocale),
            sprintf('%s/%s/%s.json', $baseDir, $namespace, $normalizedSlug),
            sprintf('%s/%s.%s.json', $baseDir, $normalizedSlug, $normalizedLocale),
            sprintf('%s/%s.json', $baseDir, $normalizedSlug),
        ];

        foreach ($candidates as $path) {
            if (!is_readable($path)) {
                continue;
            }

            $content = file_get_contents($path);
            if ($content === false) {
                continue;
            }

            $decoded = json_decode($content, true);
            if (!is_array($decoded)) {
                continue;
            }

            return $this->normalizeNavigationPayload($decoded, $basePath);
        }

        return [
            'main' => [],
            'footer' => [],
            'legal' => [],
            'sidebar' => [],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{main: array<int, array<string, mixed>>, footer: array<int, array<string, mixed>>, legal: array<int, array<string, mixed>>, sidebar: array<int, array<string, mixed>>}
     */
    private function normalizeNavigationPayload(array $payload, string $basePath): array
    {
        $normalized = [
            'main' => [],
            'footer' => [],
            'legal' => [],
            'sidebar' => [],
        ];

        foreach (['main', 'primary', 'menu'] as $mainKey) {
            if (isset($payload[$mainKey]) && is_array($payload[$mainKey])) {
                $normalized['main'] = $this->normalizeMenuEntries($payload[$mainKey], $basePath);
                break;
            }
        }

        foreach (['footer', 'legal', 'sidebar'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                $normalized[$key] = $this->normalizeMenuEntries($payload[$key], $basePath);
            }
        }

        return $normalized;
    }

    /**
     * @param array<int, mixed> $items
     * @return array<int, array<string, mixed>>
     */
    private function normalizeMenuEntries(array $items, string $basePath): array
    {
        $normalized = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $label = trim((string) ($item['label'] ?? ''));
            $href = trim((string) ($item['href'] ?? ''));
            if ($label === '' || $href === '') {
                continue;
            }

            $children = [];
            if (isset($item['children']) && is_array($item['children'])) {
                $children = $this->normalizeMenuEntries($item['children'], $basePath);
            }

            $normalized[] = [
                'label' => $label,
                'href' => $this->normalizeMenuHref($href, $basePath),
                'isExternal' => (bool) ($item['isExternal'] ?? false),
                'children' => $children,
            ];
        }

        return $normalized;
    }

    /**
     * @param array{cookie_consent_enabled?:bool,cookie_storage_key?:string,cookie_banner_text_de?:string,cookie_banner_text_en?:string,cookie_vendor_flags?:array<array-key, mixed>} $settings
     * @return array<string, mixed>
     */
    private function buildCookieConsentConfig(array $settings, string $locale): array
    {
        $storageKey = trim((string) ($settings['cookie_storage_key'] ?? ''));
        if ($storageKey === '') {
            $storageKey = 'calserverCookieChoices';
        }

        $bannerText = $this->resolveBannerText($settings, $locale);

        return [
            'enabled' => (bool) ($settings['cookie_consent_enabled'] ?? false),
            'storageKey' => $storageKey,
            'bannerText' => $bannerText,
            'vendorFlags' => $settings['cookie_vendor_flags'] ?? [],
            'eventName' => 'marketing:cookie-preference-changed',
            'selectors' => [
                'banner' => '[data-marketing-cookie-banner]',
                'trigger' => '[data-marketing-cookie-open]',
                'accept' => '[data-marketing-cookie-accept]',
                'necessary' => '[data-marketing-cookie-necessary]',
                'video' => '[data-calserver-video]',
                'videoConsent' => '[data-calserver-video-consent]',
                'proSeal' => '[data-calserver-proseal]',
                'proSealTarget' => '[data-proseal-target]',
                'proSealPlaceholder' => '[data-calserver-proseal-placeholder]',
                'proSealConsent' => '[data-calserver-proseal-consent]',
                'proSealError' => '[data-calserver-proseal-error]',
                'moduleVideo' => '.calserver-module-figure__video',
                'moduleFigure' => '.calserver-module-figure',
            ],
            'classes' => [
                'bannerVisible' => 'marketing-cookie-banner--visible',
                'triggerActive' => 'marketing-cookie-trigger--active',
            ],
        ];
    }

    /**
     * @param array{cookie_banner_text_de?:string,cookie_banner_text_en?:string} $settings
     */
    private function resolveBannerText(array $settings, string $locale): string
    {
        $normalizedLocale = strtolower(trim($locale));
        if (str_starts_with($normalizedLocale, 'en')) {
            return (string) ($settings['cookie_banner_text_en'] ?? '');
        }

        return (string) ($settings['cookie_banner_text_de'] ?? '');
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, bool>
     */
    private function buildHeaderConfig(array $settings): array
    {
        return [
            'show_language' => (bool) ($settings['show_language_toggle'] ?? true),
            'show_theme_toggle' => (bool) ($settings['show_theme_toggle'] ?? true),
            'show_contrast_toggle' => (bool) ($settings['show_contrast_toggle'] ?? true),
        ];
    }

    /**
     * @param array<string, mixed> $settings
     * @return array{mode:string,src:?string,alt:string,label:string,path:string}
     */
    private function buildHeaderLogoSettings(array $settings, string $basePath): array
    {
        $mode = is_string($settings['header_logo_mode'] ?? null)
            ? strtolower(trim((string) $settings['header_logo_mode']))
            : 'text';
        $path = is_string($settings['header_logo_path'] ?? null)
            ? trim((string) $settings['header_logo_path'])
            : '';
        $alt = is_string($settings['header_logo_alt'] ?? null)
            ? trim((string) $settings['header_logo_alt'])
            : '';
        $label = is_string($settings['header_logo_label'] ?? null)
            ? trim((string) $settings['header_logo_label'])
            : '';
        if ($label === '') {
            $label = $alt !== '' ? $alt : 'QuizRace';
        }
        if ($alt === '') {
            $alt = $label;
        }
        $src = $this->resolveHeaderLogoPath($path, $basePath);

        if ($mode !== 'image' || $src === null) {
            $mode = 'text';
        }

        return [
            'mode' => $mode,
            'src' => $src,
            'alt' => $alt,
            'label' => $label,
            'path' => $path,
        ];
    }

    private function resolveHeaderLogoPath(?string $path, string $basePath): ?string
    {
        if (!is_string($path)) {
            return null;
        }

        $normalized = trim($path);
        if ($normalized === '') {
            return null;
        }

        if (str_starts_with($normalized, 'http://') || str_starts_with($normalized, 'https://')) {
            return $normalized;
        }

        $normalizedBase = rtrim($basePath, '/');

        return ($normalizedBase !== '' ? $normalizedBase : '') . '/' . ltrim($normalized, '/');
    }

    private function buildNewsBasePath(Request $request, string $pageSlug): string
    {
        $path = $request->getUri()->getPath();
        if (preg_match('~^/m/([a-z0-9-]+)~', $path) === 1) {
            return sprintf('/m/%s/news', $pageSlug);
        }

        return sprintf('/%s/news', $pageSlug);
    }

    /**
     * @return array{html: string, data: array|null}
     */
    private function extractCalhelpModules(string $html): array
    {
        $pattern = '/<script[^>]*data-calhelp-modules[^>]*>(.*?)<\/script>/si';
        if (!preg_match($pattern, $html, $matches)) {
            return ['html' => $html, 'data' => null];
        }

        $json = trim(html_entity_decode($matches[1], ENT_QUOTES));
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return ['html' => str_replace($matches[0], '', $html), 'data' => null];
        }

        $modules = [];
        if (isset($decoded['modules']) && is_array($decoded['modules'])) {
            foreach ($decoded['modules'] as $module) {
                if (is_array($module)) {
                    $modules[] = $module;
                }
            }
        }

        $data = [
            'headline' => isset($decoded['headline']) && is_array($decoded['headline']) ? $decoded['headline'] : [],
            'subheadline' => isset($decoded['subheadline']) && is_array($decoded['subheadline']) ? $decoded['subheadline'] : [],
            'modules' => $modules,
        ];

        if (isset($decoded['eyebrow']) && is_array($decoded['eyebrow'])) {
            $data['eyebrow'] = $decoded['eyebrow'];
        }

        return [
            'html' => str_replace($matches[0], '', $html),
            'data' => $data,
        ];
    }

    /**
     * @return array{html: string, data: array|null}
     */
    private function extractCalhelpUsecases(string $html): array
    {
        $pattern = '/<script[^>]*data-calhelp-usecases[^>]*>(.*?)<\/script>/si';
        if (!preg_match($pattern, $html, $matches)) {
            return ['html' => $html, 'data' => null];
        }

        $json = trim(html_entity_decode($matches[1], ENT_QUOTES));
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return ['html' => str_replace($matches[0], '', $html), 'data' => null];
        }

        $usecases = [];
        if (isset($decoded['usecases']) && is_array($decoded['usecases'])) {
            foreach ($decoded['usecases'] as $usecase) {
                if (is_array($usecase)) {
                    $usecases[] = $usecase;
                }
            }
        }

        $data = [
            'heading' => isset($decoded['heading']) && is_array($decoded['heading']) ? $decoded['heading'] : [],
            'intro' => isset($decoded['intro']) && is_array($decoded['intro']) ? $decoded['intro'] : [],
            'usecases' => $usecases,
        ];

        return [
            'html' => str_replace($matches[0], '', $html),
            'data' => $data,
        ];
    }

    /**
     * Replace the static calHelp news section with a placeholder for dynamic rendering.
     *
     * @return array{html: string, replaced: bool}
     */
    private function replaceCalhelpNewsSection(string $html, string $placeholder): array
    {
        $pattern = '/<section id="news" class="uk-section calhelp-section" aria-labelledby="news-title">[\s\S]*?<\/section>/i';

        $result = preg_replace($pattern, $placeholder, $html, 1, $count);

        if (!is_string($result) || $count === 0) {
            return ['html' => $html, 'replaced' => false];
        }

        return ['html' => $result, 'replaced' => true];
    }

    private function ensureTurnstileMarkup(string $html, string $widgetMarkup, bool $placeholderExisted): string
    {
        if ($widgetMarkup === '' || $placeholderExisted) {
            return $html;
        }

        if (str_contains($html, 'cf-turnstile')) {
            return $html;
        }

        $formPattern = '/(<form[^>]*id="contact-form"[^>]*>)(.*?)(<\/form>)/is';
        if (!preg_match($formPattern, $html, $matches)) {
            return $html;
        }

        $formContent = $matches[2];
        if (str_contains($formContent, 'cf-turnstile')) {
            return $html;
        }

        $containerPattern = '/(<div[^>]*data-turnstile-container[^>]*>)(.*?)(<\/div>)/is';
        $updatedFormContent = $formContent;

        if (preg_match($containerPattern, $formContent, $containerMatches)) {
            if (!str_contains($containerMatches[2], 'cf-turnstile')) {
                $containerReplacement = $containerMatches[1]
                    . $containerMatches[2]
                    . $widgetMarkup
                    . $containerMatches[3];
                $updatedFormContent = preg_replace(
                    $containerPattern,
                    $containerReplacement,
                    $formContent,
                    1
                ) ?? $formContent;
            }
        } else {
            $updatedFormContent .= '<div class="turnstile-widget">' . $widgetMarkup . '</div>';
        }

        $replacement = $matches[1] . $updatedFormContent . $matches[3];
        $result = preg_replace($formPattern, $replacement, $html, 1);

        return is_string($result) ? $result : $html;
    }

    private function ensureNewsletterOptIn(string $html, string $templateSlug, string $locale): string
    {
        if (!str_contains($html, 'id="contact-form"')) {
            return $html;
        }

        if (str_contains($html, 'name="newsletter_subscribe"') || str_contains($html, 'name="newsletter_action"')) {
            return $html;
        }

        $formPattern = '/(<form[^>]*id="contact-form"[^>]*>)(.*?)(<\/form>)/is';
        if (!preg_match($formPattern, $html, $matches)) {
            return $html;
        }

        $formContent = $matches[2];
        if (str_contains($formContent, 'newsletter_subscribe')) {
            return $html;
        }

        $label = $this->determineNewsletterLabel($html, $templateSlug, $locale);
        $escapedLabel = htmlspecialchars($label, ENT_QUOTES);

        $newsletterMarkup = <<<HTML
          <div class="uk-margin">
            <label><input class="uk-checkbox" type="checkbox" name="newsletter_subscribe" value="1"> {$escapedLabel}</label>
          </div>
        HTML;

        $privacyPattern = '/(<div[^>]*>\s*<label[^>]*><input[^>]*name="privacy"[^>]*>.*?<\/label>\s*<\/div>)/is';
        $applied = false;
        $updatedFormContent = preg_replace_callback(
            $privacyPattern,
            static function (array $innerMatches) use ($newsletterMarkup, &$applied): string {
                if ($applied) {
                    return $innerMatches[0];
                }
                $applied = true;

                return $newsletterMarkup . "\n" . $innerMatches[0];
            },
            $formContent,
            1
        );

        if (is_string($updatedFormContent) && $updatedFormContent !== $formContent) {
            $formContent = $updatedFormContent;
        } else {
            $formContent = $newsletterMarkup . $formContent;
        }

        $replacement = $matches[1] . $formContent . $matches[3];
        $result = preg_replace($formPattern, $replacement, $html, 1);

        return is_string($result) ? $result : $html;
    }

    private function determineNewsletterLabel(string $html, string $templateSlug, string $locale): string
    {
        $formTag = null;
        if (preg_match('/<form[^>]*id="contact-form"[^>]*>/i', $html, $formMatches)) {
            $formTag = $formMatches[0];
        }

        if (is_string($formTag) && preg_match('/data-newsletter-label="([^\"]+)"/i', $formTag, $labelMatches)) {
            return html_entity_decode($labelMatches[1], ENT_QUOTES);
        }

        $brand = null;
        if (is_string($formTag) && preg_match('/data-newsletter-brand="([^\"]+)"/i', $formTag, $brandMatches)) {
            $brand = html_entity_decode($brandMatches[1], ENT_QUOTES);
        }

        if ($brand === null) {
            $brand = $this->resolveNewsletterBrand($html, $templateSlug);
        }

        return $this->buildNewsletterLabel($brand, $locale);
    }

    private function resolveNewsletterBrand(string $html, string $templateSlug): string
    {
        $identifier = $this->extractContactEndpointSlug($html);
        if ($identifier === null) {
            $identifier = $templateSlug;
        }

        $normalized = $this->normalizeNewsletterSlug($identifier);
        $brands = [
            'landing' => 'QuizRace',
            'calserver' => 'calServer',
            'calhelp' => 'calHelp',
            'future-is-green' => 'Future is Green',
        ];
        if (isset($brands[$normalized])) {
            return $brands[$normalized];
        }

        if (str_contains($normalized, 'calserver')) {
            return $brands['calserver'];
        }

        if (str_contains($normalized, 'calhelp')) {
            return $brands['calhelp'];
        }

        if (str_contains($normalized, 'future-is-green') || str_contains($normalized, 'futureisgreen')) {
            return $brands['future-is-green'];
        }

        return 'QuizRace';
    }

    private function extractContactEndpointSlug(string $html): ?string
    {
        if (preg_match('/data-contact-endpoint="[^"]*\/([a-z0-9-]+)\/contact"/i', $html, $matches)) {
            return strtolower($matches[1]);
        }

        return null;
    }

    private function normalizeNewsletterSlug(string $slug): string
    {
        $normalized = strtolower($slug);
        if (preg_match('/^(.*)-(de|en)$/', $normalized, $matches)) {
            return $matches[1];
        }

        return $normalized;
    }

    private function buildNewsletterLabel(?string $brand, string $locale): string
    {
        $normalizedBrand = trim((string) $brand);
        if ($normalizedBrand === '') {
            $normalizedBrand = 'QuizRace';
        }

        $language = strtolower(substr($locale, 0, 2));
        if ($language === 'en') {
            return sprintf('I would like to receive the %s newsletter.', $normalizedBrand);
        }

        return sprintf('Ich möchte den %s Newsletter erhalten.', $normalizedBrand);
    }

    private function buildMaintenanceWindowLabel(string $locale): string
    {
        $timezone = new DateTimeZone('Europe/Berlin');
        $start = new DateTimeImmutable('today', $timezone);
        $end = $start->modify('+2 days');

        $intlLocale = $locale === 'en' ? 'en_GB' : 'de_DE';
        $sameMonth = $start->format('mY') === $end->format('mY');

        if ($locale === 'en') {
            $startPattern = $sameMonth ? 'd' : 'd MMMM';
            $startFallback = $sameMonth ? 'j' : 'j F';
            $endPattern = 'd MMMM';
            $endFallback = 'j F';
        } else {
            $startPattern = $sameMonth ? 'd.' : 'd. MMMM';
            $startFallback = $sameMonth ? 'j.' : 'j. F';
            $endPattern = 'd. MMMM';
            $endFallback = 'j. F';
        }

        $startLabel = $this->formatWithIntl($start, $intlLocale, $timezone, $startPattern, $startFallback);
        $endLabel = $this->formatWithIntl($end, $intlLocale, $timezone, $endPattern, $endFallback);

        return sprintf('%s–%s', $startLabel, $endLabel);
    }

    private function formatWithIntl(
        DateTimeImmutable $date,
        string $locale,
        DateTimeZone $timezone,
        string $pattern,
        string $fallbackPattern
    ): string {
        if (class_exists('\\IntlDateFormatter')) {
            $formatter = new IntlDateFormatter(
                $locale,
                IntlDateFormatter::NONE,
                IntlDateFormatter::NONE,
                $timezone->getName(),
                IntlDateFormatter::GREGORIAN,
                $pattern
            );

            $formatted = $formatter->format($date);
            if ($formatted !== false) {
                return $formatted;
            }
        }

        return $date->format($fallbackPattern);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function appendWikiMenuItem(array $menuItems, string $label, string $url): array
    {
        $menuItems[] = [
            'id' => 'wiki',
            'label' => $label,
            'href' => $url,
            'icon' => 'file-text',
            'layout' => 'link',
            'isExternal' => false,
            'isActive' => true,
            'children' => [],
        ];

        return $menuItems;
    }

    /**
     * @return array<string, string>
     */
    private function buildLaborAssetUrls(string $basePath): array
    {
        $assetConfig = [
            '/assets/labor-hero.jpg' => ['label' => 'Labor Hero', 'width' => 1200, 'height' => 900],
            '/assets/kalibrierung-labor-detail.jpg' => ['label' => 'Labor Detail', 'width' => 1200, 'height' => 900],
            '/assets/messgroessen-elektrisch.jpg' => ['label' => 'Elektrische Messgrößen', 'width' => 960, 'height' => 640],
            '/assets/temperatur.jpg' => ['label' => 'Temperatur', 'width' => 960, 'height' => 640],
            '/assets/vor-ort.jpg' => ['label' => 'Vor-Ort-Kalibrierung', 'width' => 960, 'height' => 640],
            '/assets/pruefmittel.jpg' => ['label' => 'Prüfmittelmanagement', 'width' => 960, 'height' => 640],
            '/assets/calserver-dashboard.png' => ['label' => 'calServer Dashboard', 'width' => 1400, 'height' => 880],
            '/assets/dakks-logo.png' => ['label' => 'DAkkS Logo', 'width' => 640, 'height' => 320],
        ];

        $resolved = [];
        foreach ($assetConfig as $path => $config) {
            $resolved[$path] = $this->resolveMarketingAsset(
                $path,
                $basePath,
                (int) $config['width'],
                (int) $config['height'],
                (string) $config['label'],
            );
        }

        return $resolved;
    }

    private function resolveMarketingAsset(string $path, string $basePath, int $width, int $height, string $label): string
    {
        $normalizedPath = '/' . ltrim($path, '/');
        $publicPath = dirname(__DIR__, 2) . '/public' . $normalizedPath;

        if (is_readable($publicPath)) {
            return $basePath . $normalizedPath;
        }

        return $this->buildSvgPlaceholder($width, $height, $label);
    }

    private function buildSvgPlaceholder(int $width, int $height, string $label): string
    {
        $safeLabel = htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5);
        $innerWidth = max(0, $width - 48);
        $innerHeight = max(0, $height - 48);

        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%1$d" height="%2$d" viewBox="0 0 %1$d %2$d" role="img" aria-label="%3$s">'
            . '<rect width="100%%" height="100%%" fill="#e5eaf1" />'
            . '<rect x="24" y="24" width="%4$d" height="%5$d" rx="18" fill="none" stroke="#c7d1e3" stroke-width="3" />'
            . '<text x="50%%" y="50%%" fill="#42536b" font-family="Inter, -apple-system, BlinkMacSystemFont, &quot;Segoe UI&quot;, sans-serif"'
            . ' font-size="32" font-weight="600" text-anchor="middle" dominant-baseline="middle">%3$s</text>'
            . '</svg>',
            $width,
            $height,
            $safeLabel,
            $innerWidth,
            $innerHeight,
        );

        $minified = preg_replace('/\s+/', ' ', trim($svg));

        return 'data:image/svg+xml;utf8,' . rawurlencode($minified ?? $svg);
    }

    private function extractPageBlocks(string $html): ?array
    {
        $pattern = '/<script\\b[^>]*\\bdata-json=["\']page["\'][^>]*>(.*?)<\\/script>/si';
        if (preg_match($pattern, $html, $matches)) {
            $json = $matches[1];
            $decoded = json_decode(html_entity_decode($json, ENT_QUOTES), true);
            if ($decoded === null) {
                return null;
            }

            $blocks = array_key_exists('blocks', $decoded) ? $decoded['blocks'] : $decoded;
            if (!is_array($blocks)) {
                return null;
            }

            return $blocks;
        }

        $trimmed = trim($html);
        if ($trimmed === '' || !str_starts_with($trimmed, '{')) {
            return null;
        }

        $decoded = json_decode($trimmed, true);
        if (!is_array($decoded)) {
            return null;
        }

        $blocks = array_key_exists('blocks', $decoded) ? $decoded['blocks'] : $decoded;
        if (!is_array($blocks)) {
            return null;
        }

        return $blocks;
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

    private function resolveLocalizedSlug(string $baseSlug, string $locale): string
    {
        return MarketingSlugResolver::resolveLocalizedSlug($baseSlug, $locale);
    }
}
