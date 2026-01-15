<?php

declare(strict_types=1);

namespace App\Controller\Marketing;

use App\Service\CmsPageWikiArticleService;
use App\Service\CmsPageWikiSettingsService;
use App\Service\MarketingSlugResolver;
use App\Service\MarketingWikiThemeConfigService;
use App\Service\NamespaceAppearanceService;
use App\Service\NamespaceRenderContextService;
use App\Service\NamespaceResolver;
use App\Service\PageService;
use App\Support\FeatureFlags;
use App\Support\MarketingWikiThemeResolver;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;

final class CmsPageWikiListController
{
    private PageService $pageService;

    private CmsPageWikiSettingsService $settingsService;

    private CmsPageWikiArticleService $articleService;

    private NamespaceResolver $namespaceResolver;

    private MarketingWikiThemeConfigService $themeConfigService;

    private NamespaceAppearanceService $namespaceAppearance;

    private NamespaceRenderContextService $namespaceRenderContext;

    public function __construct(
        ?PageService $pageService = null,
        ?CmsPageWikiSettingsService $settingsService = null,
        ?CmsPageWikiArticleService $articleService = null,
        ?NamespaceResolver $namespaceResolver = null,
        ?MarketingWikiThemeConfigService $themeConfigService = null,
        ?NamespaceAppearanceService $namespaceAppearance = null,
        ?NamespaceRenderContextService $namespaceRenderContext = null
    ) {
        $this->pageService = $pageService ?? new PageService();
        $this->settingsService = $settingsService ?? new CmsPageWikiSettingsService();
        $this->articleService = $articleService ?? new CmsPageWikiArticleService();
        $this->namespaceResolver = $namespaceResolver ?? new NamespaceResolver();
        $this->themeConfigService = $themeConfigService ?? new MarketingWikiThemeConfigService();
        $this->namespaceAppearance = $namespaceAppearance ?? new NamespaceAppearanceService();
        $this->namespaceRenderContext = $namespaceRenderContext ?? new NamespaceRenderContextService();
    }

    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $slug = (string) ($args['slug'] ?? '');
        if ($slug === '' || !preg_match('/^[a-z0-9-]+$/', $slug)) {
            return $response->withStatus(404);
        }

        if (!FeatureFlags::wikiEnabled()) {
            return $response->withStatus(404);
        }

        $locale = (string) $request->getAttribute('lang', 'de');
        $pageSlug = MarketingSlugResolver::resolveLocalizedSlug($slug, $locale);
        $namespaceContext = $this->namespaceResolver->resolve($request);
        $namespace = $namespaceContext->getNamespace();
        $page = $this->pageService->findByKey($namespace, $pageSlug);
        if ($page === null && $pageSlug !== $slug) {
            $page = $this->pageService->findByKey($namespace, $slug);
        }
        if ($page === null) {
            return $response->withStatus(404);
        }

        $pageNamespace = $page->getNamespace();
        if ($pageNamespace === '') {
            $pageNamespace = $namespace !== '' ? $namespace : PageService::DEFAULT_NAMESPACE;
        }
        $renderContext = $this->namespaceRenderContext->build($pageNamespace);
        $design = $renderContext['design'] ?? [];
        $appearance = $design['appearance'] ?? $this->namespaceAppearance->load($pageNamespace);

        $settingsPage = $page;
        $wikiSlug = $slug;
        $baseSlug = MarketingSlugResolver::resolveBaseSlug($page->getSlug());
        if ($baseSlug !== $page->getSlug()) {
            $basePage = $this->pageService->findByKey($namespace, $baseSlug);
            if ($basePage !== null) {
                $settingsPage = $basePage;
                $wikiSlug = $baseSlug;
            }
        }

        $settings = $this->settingsService->getSettingsForPage($settingsPage->getId());
        if (!$settings->isActive()) {
            return $response->withStatus(404);
        }

        $query = $request->getQueryParams();
        $search = trim((string) ($query['q'] ?? ''));

        $articles = $this->articleService->getPublishedArticles($settingsPage->getId(), $locale);
        if ($search !== '') {
            $articles = array_values(array_filter($articles, static function ($article) use ($search) {
                $haystack = strtolower($article->getTitle() . ' ' . ($article->getExcerpt() ?? ''));
                return str_contains($haystack, strtolower($search));
            }));
        }

        if ($articles === []) {
            return $response->withStatus(404);
        }

        $view = Twig::fromRequest($request);
        $basePath = RouteContext::fromRequest($request)->getBasePath();
        $menuLabel = $settings->getMenuLabelForLocale($locale) ?? 'Dokumentation';

        $themeOverrides = $this->themeConfigService->getThemeForSlug($namespace, $settingsPage->getSlug());
        $theme = MarketingWikiThemeResolver::resolve($themeOverrides);

        return $view->render($response, 'marketing/wiki/index.twig', [
            'page' => $page,
            'articles' => $articles,
            'searchTerm' => $search,
            'menuLabel' => $menuLabel,
            'wikiTheme' => $theme,
            'namespace' => $pageNamespace,
            'pageNamespace' => $pageNamespace,
            'appearance' => $appearance,
            'design' => $design,
            'renderContext' => $renderContext,
            'breadcrumbs' => [
                [
                    'url' => $basePath . '/' . $page->getSlug(),
                    'label' => $page->getTitle(),
                ],
                [
                    'url' => $basePath . '/pages/' . $wikiSlug . '/wiki',
                    'label' => $menuLabel,
                ],
            ],
        ]);
    }
}
