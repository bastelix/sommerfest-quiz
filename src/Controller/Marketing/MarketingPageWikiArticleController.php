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

final class MarketingPageWikiArticleController
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
        $articleSlug = (string) ($args['articleSlug'] ?? '');
        if (!preg_match('/^[a-z0-9-]+$/', $slug) || !preg_match('/^[a-z0-9-]+$/', $articleSlug)) {
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

        $settings = $this->settingsService->getSettingsForPage($page->getId());
        if (!$settings->isActive()) {
            return $response->withStatus(404);
        }

        $article = $this->articleService->findPublishedArticle($page->getId(), $locale, $articleSlug);
        if ($article === null) {
            return $response->withStatus(404);
        }

        $menuLabel = $settings->getMenuLabel() ?? 'Dokumentation';
        $view = Twig::fromRequest($request);
        $basePath = RouteContext::fromRequest($request)->getBasePath();

        return $view->render($response, 'marketing/wiki/show.twig', [
            'page' => $page,
            'article' => $article,
            'menuLabel' => $menuLabel,
            'breadcrumbs' => [
                [
                    'url' => $basePath . '/' . $page->getSlug(),
                    'label' => $page->getTitle(),
                ],
                [
                    'url' => $basePath . '/pages/' . $page->getSlug() . '/wiki',
                    'label' => $menuLabel,
                ],
                [
                    'url' => $basePath . '/pages/' . $page->getSlug() . '/wiki/' . $article->getSlug(),
                    'label' => $article->getTitle(),
                ],
            ],
        ]);
    }
}
