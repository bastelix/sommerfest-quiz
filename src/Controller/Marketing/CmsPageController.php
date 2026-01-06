<?php

declare(strict_types=1);

namespace App\Controller\Marketing;

use App\Application\Seo\PageSeoConfigService;
use App\Domain\Roles;
use App\Domain\Page;
use App\Domain\CmsBuilderRuntimeGuard;
use App\Domain\PageRuntimeType;
use App\Service\LandingNewsService;
use App\Service\MailService;
use App\Service\CmsPageMenuService;
use App\Service\CmsPageWikiArticleService;
use App\Service\CmsPageWikiSettingsService;
use App\Service\MarketingSlugResolver;
use App\Service\NamespaceAppearanceService;
use App\Service\NamespaceResolver;
use App\Service\ConfigService;
use App\Service\PageContentLoader;
use App\Service\PageModuleService;
use App\Service\PageService;
use App\Service\ProvenExpertRatingService;
use App\Service\ProjectSettingsService;
use App\Service\TurnstileConfig;
use App\Service\NamespaceRenderContextService;
use App\Service\EffectsPolicyService;
use App\Infrastructure\Database;
use App\Support\BasePathHelper;
use App\Support\FeatureFlags;
use DateTimeImmutable;
use DateTimeZone;
use IntlDateFormatter;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;
use Twig\Error\LoaderError;

use function array_key_exists;
use function dirname;
use function file_get_contents;
use function html_entity_decode;
use function htmlspecialchars;
use function is_array;
use function is_readable;
use function json_encode;
use function json_decode;
use function max;
use function preg_replace;
use function rawurlencode;
use function str_contains;
use function str_starts_with;
use function str_replace;
use function trim;

class CmsPageController
{
    private const CALHELP_NEWS_PLACEHOLDER = '__CALHELP_NEWS_SECTION__';

    private const DEFAULT_NEWSLETTER_BRAND = 'QuizRace';

    /** @var array<string, string> */
    private const NEWSLETTER_BRANDS = [
        'landing' => 'QuizRace',
        'calserver' => 'calServer',
        'calhelp' => 'calHelp',
        'future-is-green' => 'Future is Green',
    ];

    private PageService $pages;
    private PageSeoConfigService $seo;
    private ?string $slug;
    private TurnstileConfig $turnstileConfig;
    private ProvenExpertRatingService $provenExpert;
    private LandingNewsService $landingNews;
    private CmsPageMenuService $cmsMenu;
    private CmsPageWikiSettingsService $wikiSettings;
    private CmsPageWikiArticleService $wikiArticles;
    private PageContentLoader $contentLoader;
    private PageModuleService $pageModules;
    private NamespaceAppearanceService $namespaceAppearance;
    private NamespaceResolver $namespaceResolver;
    private NamespaceRenderContextService $namespaceRenderContext;
    private ProjectSettingsService $projectSettings;
    private ConfigService $configService;
    private EffectsPolicyService $effectsPolicy;

    public function __construct(
        ?string $slug = null,
        ?PageService $pages = null,
        ?PageSeoConfigService $seo = null,
        ?TurnstileConfig $turnstileConfig = null,
        ?ProvenExpertRatingService $provenExpert = null,
        ?LandingNewsService $landingNews = null,
        ?CmsPageMenuService $cmsMenu = null,
        ?CmsPageWikiSettingsService $wikiSettings = null,
        ?CmsPageWikiArticleService $wikiArticles = null,
        ?PageContentLoader $contentLoader = null,
        ?PageModuleService $pageModules = null,
        ?NamespaceAppearanceService $namespaceAppearance = null,
        ?NamespaceResolver $namespaceResolver = null,
        ?NamespaceRenderContextService $namespaceRenderContext = null,
        ?ProjectSettingsService $projectSettings = null,
        ?ConfigService $configService = null,
        ?EffectsPolicyService $effectsPolicy = null
    ) {
        $this->slug = $slug;
        $this->pages = $pages ?? new PageService();
        $this->seo = $seo ?? new PageSeoConfigService();
        $this->turnstileConfig = $turnstileConfig ?? TurnstileConfig::fromEnv();
        $this->provenExpert = $provenExpert ?? new ProvenExpertRatingService();
        $this->landingNews = $landingNews ?? new LandingNewsService();
        $this->cmsMenu = $cmsMenu ?? new CmsPageMenuService();
        $this->wikiSettings = $wikiSettings ?? new CmsPageWikiSettingsService();
        $this->wikiArticles = $wikiArticles ?? new CmsPageWikiArticleService();
        $this->contentLoader = $contentLoader ?? new PageContentLoader();
        $this->pageModules = $pageModules ?? new PageModuleService();
        $this->namespaceAppearance = $namespaceAppearance ?? new NamespaceAppearanceService();
        $this->namespaceResolver = $namespaceResolver ?? new NamespaceResolver();
        $this->namespaceRenderContext = $namespaceRenderContext ?? new NamespaceRenderContextService();
        $this->projectSettings = $projectSettings ?? new ProjectSettingsService();
        $pdo = Database::connectFromEnv();
        $this->configService = $configService ?? new ConfigService($pdo);
        $this->effectsPolicy = $effectsPolicy ?? new EffectsPolicyService($this->configService);
    }

    public function __invoke(Request $request, Response $response, array $args = []): Response {
        $templateSlug = $this->slug ?? (string) ($args['slug'] ?? '');
        if ($templateSlug === '' || !preg_match('/^[a-z0-9-]+$/', $templateSlug)) {
            return $response->withStatus(404);
        }

        $locale = (string) $request->getAttribute('lang');
        $contentSlug = $this->resolveLocalizedSlug($templateSlug, $locale);

        $namespaceContext = $this->namespaceResolver->resolve($request);
        $resolvedNamespace = $namespaceContext->getNamespace();
        $namespaceFallbackUsed = $namespaceContext->usedFallback();

        $page = $this->pages->findByKey($resolvedNamespace, $contentSlug);
        if ($page === null && $contentSlug !== $templateSlug) {
            $page = $this->pages->findByKey($resolvedNamespace, $templateSlug);
            $contentSlug = $templateSlug;
        }
        if ($page === null) {
            return $response->withStatus(404);
        }

        $contentNamespace = $page->getNamespace();

        $html = $this->contentLoader->load($page);
        $runtimeType = $this->determinePageRuntimeType($page, $html);
        CmsBuilderRuntimeGuard::assert($page, $runtimeType, $html, $namespaceFallbackUsed);
        $basePath = BasePathHelper::normalize(RouteContext::fromRequest($request)->getBasePath());
        $html = str_replace('{{ basePath }}', $basePath, $html);

        $csrf = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(16));
        $_SESSION['csrf_token'] = $csrf;
        $html = str_replace('{{ csrf_token }}', $csrf, $html);
        $html = str_replace('data-calserver-chat-open', 'data-marketing-chat-open', $html);
        $html = str_replace('aria-controls="calserver-chat-modal"', 'aria-controls="marketing-chat-modal"', $html);

        $newsReplacement = $this->replaceCalhelpNewsSection($html, self::CALHELP_NEWS_PLACEHOLDER);
        $html = $newsReplacement['html'];

        $moduleExtraction = $this->extractCalhelpModules($html);
        $html = $moduleExtraction['html'];
        $calhelpModules = $moduleExtraction['data'];

        $usecaseExtraction = $this->extractCalhelpUsecases($html);
        $html = $usecaseExtraction['html'];
        $calhelpUsecases = $usecaseExtraction['data'];

        $calhelpNewsPlaceholderActive = $newsReplacement['replaced'];

        $landingNews = $this->landingNews->getPublishedForPage($page->getId(), 3);
        $landingNewsOwnerSlug = $page->getSlug();

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

        $landingNewsBasePath = null;
        if ($landingNews !== []) {
            $landingNewsBasePath = $this->buildNewsBasePath($request, $landingNewsOwnerSlug);
        }

        $mailConfigured = MailService::isConfigured();
        $placeholderToken = '{{ turnstile_widget }}';
        $hadPlaceholder = str_contains($html, $placeholderToken);

        $widgetMarkup = '';
        if ($this->turnstileConfig->isEnabled()) {
            $siteKey = $this->turnstileConfig->getSiteKey() ?? '';
            $widgetMarkup = sprintf(
                '<div class="cf-turnstile" data-sitekey="%s" data-callback="contactTurnstileSuccess" ' .
                'data-error-callback="contactTurnstileError" data-expired-callback="contactTurnstileExpired"></div>',
                htmlspecialchars($siteKey, ENT_QUOTES)
            );
        }
        $html = str_replace($placeholderToken, $widgetMarkup, $html);

        if (!$mailConfigured) {
            $html = preg_replace(
                '/<form id="contact-form"[\s\S]*?<\/form>/',
                '<p class="uk-text-center">Kontaktformular derzeit nicht verfügbar.</p>',
                $html
            );
        } else {
            $html = $this->ensureTurnstileMarkup($html, $widgetMarkup, $hadPlaceholder);
            $html = $this->ensureNewsletterOptIn($html, $templateSlug, $locale);
        }

        $view = Twig::fromRequest($request);
        $template = 'pages/render.twig';

        $headerContent = '';
        $isAdmin = false;
        if ($templateSlug === 'landing') {
            $isAdmin = ($_SESSION['user']['role'] ?? null) === Roles::ADMIN;
        }

        $config = $this->seo->load($page->getId());
        $globals = $view->getEnvironment()->getGlobals();
        $canonicalFallback = isset($globals['canonicalUrl']) ? (string) $globals['canonicalUrl'] : null;
        $canonicalUrl = $config?->getCanonicalUrl() ?? $canonicalFallback;

        $chatPath = sprintf('/%s/chat', $templateSlug);
        if (str_starts_with($request->getUri()->getPath(), '/m/')) {
            $chatPath = sprintf('/m/%s/chat', $templateSlug);
        }

        $cmsMenuItems = $this->cmsMenu->getMenuTreeForSlug(
            $contentNamespace,
            $page->getSlug(),
            $locale,
            true
        );

        $cmsSideNavigation = [];
        $cmsFooterNavigation = [];
        $cmsLegalNavigation = [];

        $cookieSettings = $this->projectSettings->getCookieConsentSettings($resolvedNamespace);
        $cookieConsentConfig = $this->buildCookieConsentConfig($cookieSettings, $locale);
        $privacyUrl = $this->projectSettings->resolvePrivacyUrlForSettings($cookieSettings, $locale, $basePath);
        $headerConfig = $this->buildHeaderConfig($cookieSettings);
        $headerLogo = $this->buildHeaderLogoSettings($cookieSettings, $basePath);

        $design = $this->loadDesign($resolvedNamespace);
        $renderContext = $this->namespaceRenderContext->build($resolvedNamespace);

        $pageBlocks = $this->extractPageBlocks($html);
        if ($this->wantsJson($request)) {
            return $this->renderJsonPage($response, [
                'namespace' => $resolvedNamespace,
                'contentNamespace' => $contentNamespace,
                'slug' => $page->getSlug(),
                'blocks' => $pageBlocks ?? [],
                'design' => $design,
                'renderContext' => $renderContext,
                'content' => $html,
            ]);
        }
        $data = [
            'content' => $html,
            'pageBlocks' => $pageBlocks,
            'pageJson' => $pageBlocks,
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
            'turnstileSiteKey' => $this->turnstileConfig->isEnabled() ? $this->turnstileConfig->getSiteKey() : null,
            'turnstileEnabled' => $this->turnstileConfig->isEnabled(),
            'csrf_token' => $csrf,
            'cmsSlug' => $templateSlug,
            'cmsChatEndpoint' => $basePath . $chatPath,
            'pageModules' => $this->pageModules->getModulesByPosition($page->getId()),
            'cookieConsentConfig' => $cookieConsentConfig,
            'privacyUrl' => $privacyUrl,
            'pageNamespace' => $resolvedNamespace,
            'contentNamespace' => $contentNamespace,
            'config' => $design['config'],
            'headerConfig' => $headerConfig,
            'headerLogo' => $headerLogo,
            'appearance' => $design['appearance'],
            'design' => $design,
            'renderContext' => $renderContext,
            'cmsFooterNavigation' => $cmsFooterNavigation,
            'cmsLegalNavigation' => $cmsLegalNavigation,
            'cmsSidebarNavigation' => $cmsSideNavigation,
        ];
        if ($calhelpModules !== null && ($calhelpModules['modules'] ?? []) !== []) {
            $data['calhelpModules'] = $calhelpModules;
        }

        if ($calhelpUsecases !== null && ($calhelpUsecases['usecases'] ?? []) !== []) {
            $data['calhelpUsecases'] = $calhelpUsecases;
        }

        if ($landingNews !== []) {
            $data['landingNews'] = $landingNews;
            $data['landingNewsBasePath'] = $landingNewsBasePath;
            $data['landingNewsIndexUrl'] = $basePath . $landingNewsBasePath;
        }

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
                $data['cmsWikiMenu'] = [
                    'label' => $label,
                    'url' => $wikiUrl,
                ];
                $data['cmsWikiArticles'] = $wikiArticles;
                $cmsMenuItems = $this->appendWikiMenuItem($cmsMenuItems, $label, $wikiUrl);
            }
        }

        $navigation = $this->loadNavigationSections(
            $contentNamespace,
            $page->getSlug(),
            $locale,
            $basePath,
            $cmsMenuItems
        );
        $cmsMainNavigation = $navigation['main'];
        $cmsFooterNavigation = $navigation['footer'];
        $cmsLegalNavigation = $navigation['legal'];
        $cmsSideNavigation = $navigation['sidebar'];

        if ($templateSlug === 'landing') {
            $menuMarkup = $this->renderCmsMenuMarkup($view, $cmsMainNavigation, 'uk-navbar-nav uk-visible@m');
            $headerContent = $this->loadHeaderContent($view, $menuMarkup, $headerConfig, $headerLogo);
        }

        if ($templateSlug === 'landing') {
            $data['headerContent'] = $headerContent;
            $data['isAdmin'] = $isAdmin;
            $data['cmsNamespace'] = $contentNamespace;
        }

        $data['cmsMenuItems'] = $cmsMenuItems;
        $data['cmsMainNavigation'] = $cmsMainNavigation;

        $data['calhelpNewsPlaceholder'] = $calhelpNewsPlaceholderActive ? self::CALHELP_NEWS_PLACEHOLDER : null;
        $data['calhelpNewsPlaceholderActive'] = $calhelpNewsPlaceholderActive;

        if (in_array($templateSlug, ['calserver', 'landing'], true)) {
            $data['provenExpertRating'] = $this->provenExpert->getAggregateRatingMarkup();
        }

        if ($canonicalUrl !== null) {
            $data['hreflangLinks'] = $this->buildHreflangLinks($config?->getHreflang(), $canonicalUrl);
        }

        if ($templateSlug === 'calserver-maintenance') {
            $data['maintenanceWindowLabel'] = $this->buildMaintenanceWindowLabel($locale);
        }

        if ($templateSlug === 'labor') {
            $data['laborAssets'] = $this->buildLaborAssetUrls($basePath);
        }

        $response = $response->withHeader('Content-Type', 'text/html; charset=UTF-8');
        try {
            return $view->render($response, $template, $data);
        } catch (LoaderError $e) {
            return $response->withStatus(404);
        }
    }

    /**
     * @return array<int, mixed>|null
     */
    private function extractPageBlocks(string $content): ?array
    {
        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            return null;
        }

        $blocks = array_key_exists('blocks', $decoded) ? $decoded['blocks'] : $decoded;
        if (!is_array($blocks)) {
            return null;
        }

        return $blocks;
    }

    private function determinePageRuntimeType(Page $page, string $rawContent): string
    {
        $decoded = json_decode($rawContent, true);
        if (
            is_array($decoded)
            && isset($decoded['meta']['schemaVersion'])
            && isset($decoded['blocks'])
            && is_array($decoded['blocks'])
        ) {
            return PageRuntimeType::CMS_BUILDER;
        }

        if ($page->getType() === 'system') {
            return PageRuntimeType::SYSTEM;
        }

        return PageRuntimeType::LEGACY_MARKETING;
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

        return false;
    }

    /**
     * Render a marketing page payload without embedding it into the DOM.
     *
     * @param array{namespace: string, contentNamespace: string, slug: string, blocks: array<int, mixed>, design: array<string,mixed>, content: string, renderContext?: array<string, mixed>} $data
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
            'renderContext' => $renderContext,
        ] = $data + ['renderContext' => []];

        $payload = [
            'namespace' => $namespace,
            'contentNamespace' => $contentNamespace,
            'slug' => $slug,
            'blocks' => $blocks,
            'design' => $design,
            'renderContext' => $renderContext,
            'content' => $content,
        ];

        $response->getBody()->write(json_encode($payload, JSON_PRETTY_PRINT));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * @param array<int, array<string, mixed>> $cmsMenuItems
     * @return array{main: array<int, array<string, mixed>>, footer: array<int, array<string, mixed>>, legal: array<int, array<string, mixed>>, sidebar: array<int, array<string, mixed>>}
     */
    private function loadNavigationSections(
        string $contentNamespace,
        string $slug,
        string $locale,
        string $basePath,
        array $cmsMenuItems
    ): array {
        $navigation = $this->loadNavigationFromContent($contentNamespace, $slug, $locale, $basePath);

        $mainNavigation = $navigation['main'];
        if ($mainNavigation === []) {
            $mainNavigation = $this->mapMenuItemsToLinks($cmsMenuItems, $basePath);
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

    /**
     * @return array{main: array<int, array<string, mixed>>, footer: array<int, array<string, mixed>>, legal: array<int, array<string, mixed>>, sidebar: array<int, array<string, mixed>>}
     */
    private function loadNavigationFromContent(
        string $contentNamespace,
        string $slug,
        string $locale,
        string $basePath
    ): array {
        $baseDir = dirname(__DIR__, 3) . '/content/navigation';
        $normalizedSlug = trim($slug);
        $normalizedLocale = trim($locale) !== '' ? trim($locale) : 'de';

        $candidates = [
            sprintf('%s/%s/%s.%s.json', $baseDir, $contentNamespace, $normalizedSlug, $normalizedLocale),
            sprintf('%s/%s/%s.json', $baseDir, $contentNamespace, $normalizedSlug),
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

    private function normalizeMenuHref(string $href, string $basePath): string
    {
        $trimmedHref = trim($href);
        $trimmedBase = rtrim($basePath, '/');

        if ($trimmedHref === '') {
            return '#';
        }

        $lowerHref = strtolower($trimmedHref);
        $specialPrefixes = ['http://', 'https://', 'mailto:', 'tel:', '#'];
        foreach ($specialPrefixes as $prefix) {
            if (str_starts_with($lowerHref, $prefix)) {
                return $trimmedHref;
            }
        }

        if (!str_starts_with($trimmedHref, '/')) {
            return $trimmedHref;
        }

        if ($trimmedBase === '') {
            return $trimmedHref;
        }

        return $trimmedBase . $trimmedHref;
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

    /**
     * @param array<string, bool> $headerConfig
     * @param array<string, mixed> $headerLogo
     */
    private function loadHeaderContent(Twig $view, string $cmsMenuMarkup, array $headerConfig, array $headerLogo): string
    {
        $filePath = dirname(__DIR__, 3) . '/content/header.html';
        if (!is_readable($filePath)) {
            return '';
        }

        $fileContent = file_get_contents($filePath);
        if ($fileContent === false) {
            return '';
        }

        $configMenu = $view->fetch('components/config-menu.twig', [
            'show_help' => false,
            'show_language' => $headerConfig['show_language'] ?? true,
            'show_theme_toggle' => $headerConfig['show_theme_toggle'] ?? true,
            'show_contrast_toggle' => $headerConfig['show_contrast_toggle'] ?? true,
        ]);
        $lockedMenu = '<div class="qr-header-config-menu" contenteditable="false">' . $configMenu . '</div>';
        $fileContent = str_replace('{{ cms_menu }}', $cmsMenuMarkup, $fileContent);
        $fileContent = str_replace('{{ header_logo }}', $this->renderHeaderLogo($headerLogo), $fileContent);

        return str_replace('{{ config_menu }}', $lockedMenu, $fileContent);
    }

    /**
     * @param array{mode:string,src:?string,alt:string,label:string} $headerLogo
     */
    private function renderHeaderLogo(array $headerLogo): string
    {
        $label = htmlspecialchars((string) $headerLogo['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $alt = htmlspecialchars((string) $headerLogo['alt'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $mode = $headerLogo['mode'];
        $src = $headerLogo['src'];

        if ($mode === 'image' && is_string($src) && $src !== '') {
            $safeSrc = htmlspecialchars($src, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            return sprintf('<a class="uk-logo" href="landing"><img src="%s" alt="%s"></a>', $safeSrc, $alt);
        }

        return sprintf('<a class="uk-logo" href="landing">%s</a>', $label);
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

    private function renderCmsMenuMarkup(Twig $view, array $menuItems, string $navClass): string
    {
        return $view->fetch('marketing/partials/menu-main.twig', [
            'menuItems' => $menuItems,
            'navClass' => $navClass,
            'dropdownAttr' => 'uk-dropdown="offset: 20; delay-hide: 150; pos: bottom-left"',
        ]);
    }
    /**
     * @param array<int, array<string, mixed>> $menuItems
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

    /**
     * @param array{
     *     cookie_consent_enabled?:bool,
     *     cookie_storage_key?:string,
     *     cookie_banner_text_de?:string,
     *     cookie_banner_text_en?:string,
     *     cookie_vendor_flags?:array<array-key, mixed>
     * } $settings
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
                'banner' => '[data-calserver-cookie-banner]',
                'trigger' => '[data-calserver-cookie-open]',
                'accept' => '[data-calserver-cookie-accept]',
                'necessary' => '[data-calserver-cookie-necessary]',
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
                'bannerVisible' => 'calserver-cookie-banner--visible',
                'triggerActive' => 'calserver-cookie-trigger--active',
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
     * Ensure the marketing contact form contains the Turnstile widget markup when enabled.
     *
     * @param string $html               Rendered page content
     * @param string $widgetMarkup       Prepared Turnstile widget markup
     * @param bool   $placeholderExisted True when the original template provided a placeholder
     *
     * @return string Updated page content with widget markup inserted when necessary
     */
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
        if (isset(self::NEWSLETTER_BRANDS[$normalized])) {
            return self::NEWSLETTER_BRANDS[$normalized];
        }

        if (str_contains($normalized, 'calserver')) {
            return self::NEWSLETTER_BRANDS['calserver'];
        }

        if (str_contains($normalized, 'calhelp')) {
            return self::NEWSLETTER_BRANDS['calhelp'];
        }

        if (str_contains($normalized, 'future-is-green') || str_contains($normalized, 'futureisgreen')) {
            return self::NEWSLETTER_BRANDS['future-is-green'];
        }

        return self::DEFAULT_NEWSLETTER_BRAND;
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
            $normalizedBrand = self::DEFAULT_NEWSLETTER_BRAND;
        }

        $language = strtolower(substr($locale, 0, 2));
        if ($language === 'en') {
            return sprintf('I would like to receive the %s newsletter.', $normalizedBrand);
        }

        return sprintf('Ich möchte den %s Newsletter erhalten.', $normalizedBrand);
    }

    /**
     * Normalize hreflang definitions to a list of alternate link descriptors.
     *
     * @return array<int,array{href:string,hreflang:string}>
     */
    private function buildHreflangLinks(?string $hreflang, string $canonicalUrl): array {
        if ($hreflang === null) {
            return [];
        }

        $hreflang = trim($hreflang);
        if ($hreflang === '') {
            return [];
        }

        $decoded = json_decode($hreflang, true);
        if (is_array($decoded)) {
            $links = [];
            foreach ($decoded as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $href = $entry['href'] ?? null;
                $lang = $entry['hreflang'] ?? null;
                if ($href === null || $lang === null) {
                    continue;
                }
                $href = trim((string) $href);
                $lang = trim((string) $lang);
                if ($href === '' || $lang === '') {
                    continue;
                }
                $links[] = [
                    'href' => $href,
                    'hreflang' => $lang,
                ];
            }

            if ($links !== []) {
                return $links;
            }
        }

        $codes = preg_split('/[\s,;|]+/', $hreflang, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($codes)) {
            return [];
        }

        $links = [];
        foreach ($codes as $code) {
            $code = trim((string) $code);
            if ($code === '') {
                continue;
            }
            $links[] = [
                'href' => $canonicalUrl,
                'hreflang' => $code,
            ];
        }

        return $links;
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

    private function resolveLocalizedSlug(string $baseSlug, string $locale): string {
        return MarketingSlugResolver::resolveLocalizedSlug($baseSlug, $locale);
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
}
