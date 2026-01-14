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

final class CmsPageWikiArticleController
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
        $articleSlug = (string) ($args['articleSlug'] ?? '');
        if (!preg_match('/^[a-z0-9-]+$/', $slug) || !preg_match('/^[a-z0-9-]+$/', $articleSlug)) {
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
        $appearance = $this->namespaceAppearance->load($pageNamespace);
        $renderContext = $this->namespaceRenderContext->build($pageNamespace);
        $design = $renderContext['design'] ?? [];

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

        $article = $this->articleService->findPublishedArticle($settingsPage->getId(), $locale, $articleSlug);
        if ($article === null) {
            return $response->withStatus(404);
        }

        $menuLabel = $settings->getMenuLabelForLocale($locale) ?? 'Dokumentation';
        $view = Twig::fromRequest($request);
        $basePath = RouteContext::fromRequest($request)->getBasePath();

        $themeOverrides = $this->themeConfigService->getThemeForSlug($namespace, $settingsPage->getSlug());
        $theme = MarketingWikiThemeResolver::resolve($themeOverrides);

        return $view->render($response, 'marketing/wiki/show.twig', [
            'page' => $page,
            'article' => $article,
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
                [
                    'url' => $basePath . '/pages/' . $wikiSlug . '/wiki/' . $article->getSlug(),
                    'label' => $article->getTitle(),
                ],
            ],
        ]);
    }
}
