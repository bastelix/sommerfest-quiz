<?php

declare(strict_types=1);

namespace App\Controller\Cms;

use App\Application\Seo\PageSeoConfigService;
use App\Service\CmsPageMenuService;
use App\Service\ConfigService;
use App\Service\EffectsPolicyService;
use App\Service\NamespaceAppearanceService;
use App\Service\NamespaceResolver;
use App\Service\PageContentLoader;
use App\Service\PageModuleService;
use App\Service\PageService;
use App\Service\ProjectSettingsService;
use App\Service\MarketingSlugResolver;
use App\Infrastructure\Database;
use App\Support\BasePathHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;

use function dirname;
use function file_get_contents;
use function html_entity_decode;
use function in_array;
use function is_array;
use function is_readable;
use function json_decode;
use function preg_match;
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
    private NamespaceResolver $namespaceResolver;
    private ProjectSettingsService $projectSettings;
    private ConfigService $configService;
    private EffectsPolicyService $effectsPolicy;
    private CmsPageMenuService $cmsMenu;

    public function __construct(
        ?string $slug = null,
        ?PageService $pages = null,
        ?PageSeoConfigService $seo = null,
        ?PageContentLoader $contentLoader = null,
        ?PageModuleService $pageModules = null,
        ?NamespaceAppearanceService $namespaceAppearance = null,
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
        $this->contentLoader = $contentLoader ?? new PageContentLoader();
        $this->pageModules = $pageModules ?? new PageModuleService();
        $this->namespaceAppearance = $namespaceAppearance ?? new NamespaceAppearanceService();
        $this->namespaceResolver = $namespaceResolver ?? new NamespaceResolver();
        $this->projectSettings = $projectSettings ?? new ProjectSettingsService($pdo);
        $this->configService = $configService ?? new ConfigService($pdo);
        $this->effectsPolicy = $effectsPolicy ?? new EffectsPolicyService($this->configService);
        $this->cmsMenu = $cmsMenu ?? new CmsPageMenuService($pdo, $this->pages);
    }

    public function __invoke(Request $request, Response $response, array $args = []): Response
    {
        $templateSlug = $this->slug ?? (string) ($args['slug'] ?? '');
        if ($templateSlug === '' || !preg_match('/^[a-z0-9-]+$/', $templateSlug)) {
            return $response->withStatus(404);
        }

        $locale = (string) $request->getAttribute('lang');
        $contentSlug = $this->resolveLocalizedSlug($templateSlug, $locale);

        $namespaceContext = $this->namespaceResolver->resolve($request);
        $resolvedNamespace = $namespaceContext->getNamespace();

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
        $basePath = BasePathHelper::normalize(RouteContext::fromRequest($request)->getBasePath());
        $html = str_replace('{{ basePath }}', $basePath, $html);

        $csrf = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(16));
        $_SESSION['csrf_token'] = $csrf;
        $html = str_replace('{{ csrf_token }}', $csrf, $html);

        $pageBlocks = $this->extractPageBlocks($html);

        $design = $this->loadDesign($resolvedNamespace);
        if ($this->wantsJson($request)) {
            return $this->renderJsonPage($response, [
                'namespace' => $resolvedNamespace,
                'contentNamespace' => $contentNamespace,
                'slug' => $page->getSlug(),
                'blocks' => $pageBlocks ?? [],
                'design' => $design,
            ]);
        }

        $view = Twig::fromRequest($request);
        $config = $this->seo->load($page->getId());
        $globals = $view->getEnvironment()->getGlobals();
        $canonicalFallback = isset($globals['canonicalUrl']) ? (string) $globals['canonicalUrl'] : null;
        $canonicalUrl = $config?->getCanonicalUrl() ?? $canonicalFallback;

        $cmsMenuItems = $this->cmsMenu->getMenuTreeForSlug(
            $contentNamespace,
            $page->getSlug(),
            $locale,
            true
        );

        $cookieSettings = $this->projectSettings->getCookieConsentSettings($resolvedNamespace);
        $cookieConsentConfig = $this->buildCookieConsentConfig($cookieSettings, $locale);
        $privacyUrl = $this->projectSettings->resolvePrivacyUrlForSettings($cookieSettings, $locale, $basePath);
        $headerConfig = $this->buildHeaderConfig($cookieSettings);
        $headerLogo = $this->buildHeaderLogoSettings($cookieSettings, $basePath);

        $navigation = $this->loadNavigationSections(
            $contentNamespace,
            $page->getSlug(),
            $locale,
            $basePath,
            $privacyUrl,
            $cmsMenuItems
        );

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
            'csrf_token' => $csrf,
            'cmsSlug' => $templateSlug,
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
            'cmsFooterNavigation' => $navigation['footer'],
            'cmsLegalNavigation' => $navigation['legal'],
            'cmsSidebarNavigation' => $navigation['sidebar'],
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
     * @param array{namespace: string, contentNamespace: string, slug: string, blocks: array<int, mixed>, design: array<string,mixed>} $data
     */
    private function renderJsonPage(Response $response, array $data): Response
    {
        [
            'namespace' => $namespace,
            'contentNamespace' => $contentNamespace,
            'slug' => $slug,
            'blocks' => $blocks,
            'design' => $design,
        ] = $data;

        $payload = [
            'namespace' => $namespace,
            'contentNamespace' => $contentNamespace,
            'slug' => $slug,
            'blocks' => $blocks,
            'design' => $design,
        ];

        $response->getBody()->write(json_encode($payload, JSON_PRETTY_PRINT));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * @return array{footer: array<int, array<string, mixed>>, legal: array<int, array<string, mixed>>, sidebar: array<int, array<string, mixed>>}
     */
    private function loadNavigationSections(
        string $contentNamespace,
        string $slug,
        string $locale,
        string $basePath,
        string $privacyUrl,
        array $cmsMenuItems
    ): array {
        $navigation = $this->loadNavigationFromContent($contentNamespace, $slug, $locale, $basePath);

        $footerNavigation = $navigation['footer'];
        if ($footerNavigation === []) {
            $footerNavigation = $this->mapMenuItemsToLinks($cmsMenuItems, $basePath);
        }

        $legalNavigation = $navigation['legal'];
        if ($legalNavigation === []) {
            $legalNavigation = $this->buildDefaultLegalNavigation($basePath, $privacyUrl);
        }

        $sidebarNavigation = $navigation['sidebar'];
        if ($sidebarNavigation === []) {
            $sidebarNavigation = $this->mapMenuItemsToLinks($cmsMenuItems, $basePath);
        }

        return [
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

    private function buildDefaultLegalNavigation(string $basePath, string $privacyUrl): array
    {
        return [
            [
                'label' => 'Impressum',
                'href' => $this->normalizeMenuHref('/impressum', $basePath),
                'isExternal' => false,
            ],
            [
                'label' => 'Datenschutz',
                'href' => $privacyUrl,
                'isExternal' => false,
            ],
            [
                'label' => 'Lizenz',
                'href' => $this->normalizeMenuHref('/lizenz', $basePath),
                'isExternal' => false,
            ],
        ];
    }

    /**
     * @return array{footer: array<int, array<string, mixed>>, legal: array<int, array<string, mixed>>, sidebar: array<int, array<string, mixed>>}
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
            'footer' => [],
            'legal' => [],
            'sidebar' => [],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{footer: array<int, array<string, mixed>>, legal: array<int, array<string, mixed>>, sidebar: array<int, array<string, mixed>>}
     */
    private function normalizeNavigationPayload(array $payload, string $basePath): array
    {
        $normalized = [
            'footer' => [],
            'legal' => [],
            'sidebar' => [],
        ];

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

            $normalized[] = [
                'label' => $label,
                'href' => $this->normalizeMenuHref($href, $basePath),
                'isExternal' => (bool) ($item['isExternal'] ?? false),
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

    private function extractPageBlocks(string $html): ?array
    {
        $token = '<script type="application/json" data-json="page">';
        $start = strpos($html, $token);
        if ($start === false) {
            return null;
        }

        $start += strlen($token);
        $end = strpos($html, '</script>', $start);
        if ($end === false) {
            return null;
        }

        $json = substr($html, $start, $end - $start);
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
