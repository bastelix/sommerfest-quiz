<?php

declare(strict_types=1);

namespace App\Controller\Marketing;

use App\Application\Seo\PageSeoConfigService;
use App\Infrastructure\Database;
use App\Service\CmsMenuResolverService;
use App\Service\LandingNewsService;
use App\Service\LegacyMarketingMenuDefinition;
use App\Service\NamespaceRenderContextService;
use App\Service\PageService;
use App\Service\ProjectSettingsService;
use App\Service\ProvenExpertRatingService;
use App\Service\TranslationService;
use App\Service\TurnstileConfig;
use App\Support\BasePathHelper;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;

use function is_array;
use function is_string;
use function sprintf;
use function str_starts_with;
use function strtolower;
use function trim;

final class LegacyCalserverLandingController
{
    private NamespaceRenderContextService $namespaceRenderContext;
    private ProvenExpertRatingService $provenExpert;
    private TurnstileConfig $turnstileConfig;

    public function __construct(
        ?NamespaceRenderContextService $namespaceRenderContext = null,
        ?ProvenExpertRatingService $provenExpert = null,
        ?TurnstileConfig $turnstileConfig = null
    ) {
        $this->namespaceRenderContext = $namespaceRenderContext ?? new NamespaceRenderContextService();
        $this->provenExpert = $provenExpert ?? new ProvenExpertRatingService();
        $this->turnstileConfig = $turnstileConfig ?? TurnstileConfig::fromEnv();
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $basePath = BasePathHelper::normalize(RouteContext::fromRequest($request)->getBasePath());
        $locale = (string) $request->getAttribute('lang');
        $namespace = $this->resolveNamespace($request);

        $pdo = $request->getAttribute('pdo');
        if (!$pdo instanceof PDO) {
            $pdo = Database::connectFromEnv();
        }

        $view = Twig::fromRequest($request);
        $renderContext = $this->namespaceRenderContext->build($namespace);

        $projectSettings = new ProjectSettingsService($pdo);
        $cookieSettings = $projectSettings->getCookieConsentSettings($namespace);
        $cookieConsentConfig = $this->buildCookieConsentConfig($cookieSettings, $locale);
        $privacyUrl = $projectSettings->resolvePrivacyUrlForSettings($cookieSettings, $locale, $basePath);
        $headerConfig = $this->buildHeaderConfig($cookieSettings);

        $pageService = new PageService($pdo);
        $page = $this->resolveCalserverPage($pageService, $namespace);
        $seoConfig = $page !== null ? (new PageSeoConfigService($pdo))->load($page->getId()) : null;
        $globals = $view->getEnvironment()->getGlobals();
        $canonicalFallback = isset($globals['canonicalUrl']) ? (string) $globals['canonicalUrl'] : null;
        $canonicalUrl = $seoConfig?->getCanonicalUrl() ?? $canonicalFallback;

        $menuResolver = new CmsMenuResolverService($pdo);
        $headerNavigation = $menuResolver->resolveMenu($namespace, 'header', null, $locale);
        $cmsMainNavigation = $headerNavigation['items'] ?? [];
        $cmsMenuItems = $this->buildLegacyMenuItems('calserver', $locale);
        if ($cmsMainNavigation === []) {
            $cmsMainNavigation = $cmsMenuItems;
        }

        $footerNavigation = $menuResolver->resolveMenu($namespace, 'footer', null, $locale);
        $legalNavigation = $menuResolver->resolveMenu($namespace, 'legal', null, $locale);
        $cmsFooterColumns = $this->resolveFooterColumns($menuResolver, $namespace, $locale);

        $landingNews = [];
        $landingNewsBasePath = null;
        if ($page !== null) {
            $landingNews = (new LandingNewsService($pdo))->getPublishedForPage($page->getId());
            $landingNewsBasePath = '/calserver/news';
        }

        $marketingChatEndpoint = $basePath . '/calserver/chat';
        $turnstileSiteKey = $this->turnstileConfig->isEnabled() ? $this->turnstileConfig->getSiteKey() : null;

        return $view->render($response, 'marketing/calserver.twig', [
            'metaTitle' => $seoConfig?->getMetaTitle(),
            'metaDescription' => $seoConfig?->getMetaDescription(),
            'canonicalUrl' => $canonicalUrl,
            'robotsMeta' => $seoConfig?->getRobotsMeta(),
            'ogTitle' => $seoConfig?->getOgTitle(),
            'ogDescription' => $seoConfig?->getOgDescription(),
            'ogImage' => $seoConfig?->getOgImage(),
            'schemaJson' => $seoConfig?->getSchemaJson(),
            'hreflang' => $seoConfig?->getHreflang(),
            'namespace' => $namespace,
            'pageNamespace' => $namespace,
            'designNamespace' => $namespace,
            'renderContext' => $renderContext,
            'design' => $renderContext['design'],
            'appearance' => $renderContext['design']['appearance'] ?? [],
            'pageTheme' => $renderContext['design']['theme'] ?? 'light',
            'headerConfig' => $headerConfig,
            'cmsMainNavigation' => $cmsMainNavigation,
            'cmsMenuItems' => $cmsMenuItems,
            'cmsFooterColumns' => $cmsFooterColumns,
            'cmsLegalNavigation' => $legalNavigation['items'] ?? [],
            'marketingChatEndpoint' => $marketingChatEndpoint,
            'landingNews' => $landingNews,
            'landingNewsBasePath' => $landingNewsBasePath,
            'landingNewsIndexUrl' => $landingNewsBasePath !== null ? $basePath . $landingNewsBasePath : null,
            'provenExpertRating' => $this->provenExpert->getAggregateRatingMarkup(),
            'cookieConsentConfig' => $cookieConsentConfig,
            'privacyUrl' => $privacyUrl,
            'turnstileSiteKey' => $turnstileSiteKey,
        ]);
    }

    private function resolveNamespace(Request $request): string
    {
        $namespace = $request->getAttribute('pageNamespace')
            ?? $request->getAttribute('namespace');
        if (is_string($namespace) && trim($namespace) !== '') {
            return trim($namespace);
        }

        return 'calserver';
    }

    private function resolveCalserverPage(PageService $pageService, string $namespace): ?\App\Domain\Page
    {
        $normalized = trim($namespace);
        if ($normalized === '') {
            $normalized = PageService::DEFAULT_NAMESPACE;
        }

        $page = $pageService->findByKey($normalized, 'calserver');
        if ($page === null && $normalized !== PageService::DEFAULT_NAMESPACE) {
            $page = $pageService->findByKey(PageService::DEFAULT_NAMESPACE, 'calserver');
        }

        return $page;
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
     * @return array<int, array<string, mixed>>
     */
    private function buildLegacyMenuItems(string $slug, string $locale): array
    {
        $definition = LegacyMarketingMenuDefinition::getDefinitionForSlug($slug)
            ?? LegacyMarketingMenuDefinition::getDefaultDefinition();
        $translator = new TranslationService($locale !== '' ? $locale : 'de');

        $items = $definition['items'] ?? [];
        if (!is_array($items) || $items === []) {
            return [];
        }

        return $this->mapLegacyMenuItems($items, $translator, $slug);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function mapLegacyMenuItems(array $items, TranslationService $translator, string $prefix): array
    {
        $mapped = [];

        foreach ($items as $index => $item) {
            $label = $this->resolveLabel($item, $translator, 'label', 'label_key');
            $detailTitle = $this->resolveLabel($item, $translator, 'detail_title', 'detail_title_key');
            $detailText = $this->resolveLabel($item, $translator, 'detail_text', 'detail_text_key');
            $detailSubline = $this->resolveLabel($item, $translator, 'detail_subline', 'detail_subline_key');

            $children = [];
            if (isset($item['children']) && is_array($item['children'])) {
                $children = $this->mapLegacyMenuItems(
                    $item['children'],
                    $translator,
                    sprintf('%s-%s', $prefix, $index)
                );
            }

            $mapped[] = [
                'id' => $item['id'] ?? sprintf('%s-%s', $prefix, $index),
                'label' => $label,
                'href' => $item['href'] ?? '#',
                'icon' => $item['icon'] ?? null,
                'layout' => $item['layout'] ?? 'link',
                'detailTitle' => $detailTitle,
                'detailText' => $detailText,
                'detailSubline' => $detailSubline,
                'children' => $children,
                'isExternal' => $item['isExternal'] ?? false,
            ];
        }

        return $mapped;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function resolveLabel(
        array $item,
        TranslationService $translator,
        string $labelKey,
        string $translationKey
    ): string {
        if (isset($item[$labelKey]) && is_string($item[$labelKey])) {
            return trim($item[$labelKey]);
        }

        if (isset($item[$translationKey]) && is_string($item[$translationKey])) {
            return $translator->translate(trim($item[$translationKey]));
        }

        return '';
    }

    /**
     * @return array<int, array{slot: string, items: array<int, array<string, mixed>>}>
     */
    private function resolveFooterColumns(
        CmsMenuResolverService $menuResolver,
        string $namespace,
        string $locale
    ): array {
        $columns = [];

        foreach (CmsMenuResolverService::FOOTER_SLOTS as $slot) {
            $resolved = $menuResolver->resolveMenu($namespace, $slot, null, $locale);
            if (!is_array($resolved['items']) || $resolved['items'] === []) {
                continue;
            }

            $columns[] = [
                'slot' => $slot,
                'items' => $resolved['items'],
            ];
        }

        return $columns;
    }
}
