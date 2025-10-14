<?php

declare(strict_types=1);

namespace App\Controller\Marketing;

use App\Service\MarketingPageWikiArticleService;
use App\Service\MarketingPageWikiSettingsService;
use App\Service\MarketingSlugResolver;
use App\Service\PageService;
use App\Support\FeatureFlags;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;

final class MarketingPageWikiListController
{
    private PageService $pageService;

    private MarketingPageWikiSettingsService $settingsService;

    private MarketingPageWikiArticleService $articleService;

    public function __construct(
        ?PageService $pageService = null,
        ?MarketingPageWikiSettingsService $settingsService = null,
        ?MarketingPageWikiArticleService $articleService = null
    ) {
        $this->pageService = $pageService ?? new PageService();
        $this->settingsService = $settingsService ?? new MarketingPageWikiSettingsService();
        $this->articleService = $articleService ?? new MarketingPageWikiArticleService();
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
        $page = $this->pageService->findBySlug($pageSlug);
        if ($page === null && $pageSlug !== $slug) {
            $page = $this->pageService->findBySlug($slug);
        }
        if ($page === null) {
            return $response->withStatus(404);
        }

        $settingsPage = $page;
        $wikiSlug = $slug;
        $baseSlug = MarketingSlugResolver::resolveBaseSlug($page->getSlug());
        if ($baseSlug !== $page->getSlug()) {
            $basePage = $this->pageService->findBySlug($baseSlug);
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
        $menuLabel = $settings->getMenuLabel() ?? 'Dokumentation';

        return $view->render($response, 'marketing/wiki/index.twig', [
            'page' => $page,
            'articles' => $articles,
            'searchTerm' => $search,
            'menuLabel' => $menuLabel,
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
