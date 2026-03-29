<?php

declare(strict_types=1);

namespace App\Controller\Marketing;

use App\Domain\Page;
use App\Service\CmsLayoutDataService;
use App\Service\LandingNewsService;
use App\Service\MarketingSlugResolver;
use App\Service\NamespaceAppearanceService;
use App\Service\NamespaceRenderContextService;
use App\Service\NamespaceResolver;
use App\Service\PageService;
use App\Support\BasePathHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;

use function array_merge;
use function ceil;
use function max;
use function preg_match;
use function sprintf;
use function strip_tags;
use function trim;

class LandingNewsController
{
    private LandingNewsService $news;

    private PageService $pages;
    private NamespaceResolver $namespaceResolver;
    private NamespaceAppearanceService $namespaceAppearance;
    private NamespaceRenderContextService $namespaceRenderContext;
    private CmsLayoutDataService $layoutData;

    public function __construct(
        ?LandingNewsService $news = null,
        ?PageService $pages = null,
        ?NamespaceResolver $namespaceResolver = null,
        ?NamespaceAppearanceService $namespaceAppearance = null,
        ?NamespaceRenderContextService $namespaceRenderContext = null,
        ?CmsLayoutDataService $layoutData = null
    ) {
        $this->news = $news ?? new LandingNewsService();
        $this->pages = $pages ?? new PageService();
        $this->namespaceResolver = $namespaceResolver ?? new NamespaceResolver();
        $this->namespaceAppearance = $namespaceAppearance ?? new NamespaceAppearanceService();
        $this->namespaceRenderContext = $namespaceRenderContext ?? new NamespaceRenderContextService();
        $this->layoutData = $layoutData ?? new CmsLayoutDataService();
    }

    public function index(Request $request, Response $response, array $args = []): Response
    {
        $namespaceContext = $this->namespaceResolver->resolve($request);
        $namespace = $namespaceContext->getNamespace();
        $page = $this->resolvePage($args, $namespace);
        if ($page === null) {
            return $response->withStatus(404);
        }

        $pageNamespace = $page->getNamespace();
        if ($pageNamespace === '') {
            $pageNamespace = $namespace !== '' ? $namespace : PageService::DEFAULT_NAMESPACE;
        }
        /** @var array{namespace: string, design: array<string, mixed>} $renderContext */
        $renderContext = $this->namespaceRenderContext->build($pageNamespace);
        $designNamespace = $renderContext['namespace'];
        $design = $renderContext['design'];
        $appearance = $design['appearance'] ?? $this->namespaceAppearance->load($pageNamespace);

        $entries = $this->news->getPublishedForPage($page->getId(), 20);
        $newsOwnerPage = $page;

        if ($entries === []) {
            $baseSlug = MarketingSlugResolver::resolveBaseSlug($page->getSlug());
            if ($baseSlug !== $page->getSlug()) {
                $basePage = $this->pages->findByKey($namespace, $baseSlug);
                if ($basePage !== null) {
                    $fallbackEntries = $this->news->getPublishedForPage($basePage->getId(), 20);
                    if ($fallbackEntries !== []) {
                        $entries = $fallbackEntries;
                        $newsOwnerPage = $basePage;
                    }
                }
            }
        }

        if ($entries === []) {
            // No published news, fallback to 404 to avoid empty listing.
            return $response->withStatus(404);
        }

        $view = Twig::fromRequest($request);
        $newsBasePath = $this->buildNewsBasePath($request, $newsOwnerPage->getSlug());
        $basePath = BasePathHelper::normalize(RouteContext::fromRequest($request)->getBasePath());

        $newsOwnerBaseSlug = MarketingSlugResolver::resolveBaseSlug($newsOwnerPage->getSlug());
        $landingPageUrl = $basePath . '/' . $newsOwnerPage->getSlug();

        $locale = (string) ($request->getAttribute('lang') ?? 'de');
        $layoutData = $this->layoutData->loadLayoutData($pageNamespace, $page->getId(), $locale, $basePath);

        $breadcrumbs = [
            ['label' => $page->getTitle(), 'url' => $landingPageUrl],
            ['label' => 'Neuigkeiten', 'url' => null],
        ];

        return $view->render($response, 'marketing/landing_news_index.twig', array_merge([
            'page' => $page,
            'entries' => $entries,
            'newsBasePath' => $newsBasePath,
            'newsIndexUrl' => $basePath . $newsBasePath,
            'landingPageUrl' => $landingPageUrl,
            'newsOwnerSlug' => $newsOwnerPage->getSlug(),
            'newsOwnerBaseSlug' => $newsOwnerBaseSlug,
            'namespace' => $pageNamespace,
            'pageNamespace' => $pageNamespace,
            'designNamespace' => $designNamespace,
            'appearance' => $appearance,
            'design' => $design,
            'renderContext' => $renderContext,
            'metaTitle' => sprintf('%s – Neuigkeiten', $page->getTitle()),
            'metaDescription' => sprintf('Aktuelles zu %s.', $page->getTitle()),
            'cmsSlug' => $newsOwnerPage->getSlug(),
            'breadcrumbs' => $breadcrumbs,
        ], $layoutData));
    }

    public function show(Request $request, Response $response, array $args = []): Response
    {
        $namespaceContext = $this->namespaceResolver->resolve($request);
        $namespace = $namespaceContext->getNamespace();
        $page = $this->resolvePage($args, $namespace);
        if ($page === null) {
            return $response->withStatus(404);
        }

        $pageNamespace = $page->getNamespace();
        if ($pageNamespace === '') {
            $pageNamespace = $namespace !== '' ? $namespace : PageService::DEFAULT_NAMESPACE;
        }
        /** @var array{namespace: string, design: array<string, mixed>} $renderContext */
        $renderContext = $this->namespaceRenderContext->build($pageNamespace);
        $designNamespace = $renderContext['namespace'];
        $design = $renderContext['design'];
        $appearance = $design['appearance'] ?? $this->namespaceAppearance->load($pageNamespace);

        $newsSlug = isset($args['newsSlug']) ? (string) $args['newsSlug'] : '';
        if ($newsSlug === '') {
            return $response->withStatus(404);
        }

        $entry = $this->news->findPublished($page->getSlug(), $newsSlug);
        $newsOwnerPage = $page;

        if ($entry === null) {
            $baseSlug = MarketingSlugResolver::resolveBaseSlug($page->getSlug());
            if ($baseSlug !== $page->getSlug()) {
                $basePage = $this->pages->findByKey($namespace, $baseSlug);
                if ($basePage !== null) {
                    $entry = $this->news->findPublished($basePage->getSlug(), $newsSlug);
                    if ($entry !== null) {
                        $newsOwnerPage = $basePage;
                    }
                }
            }
        }

        if ($entry === null) {
            return $response->withStatus(404);
        }

        $view = Twig::fromRequest($request);
        $newsBasePath = $this->buildNewsBasePath($request, $newsOwnerPage->getSlug());
        $basePath = BasePathHelper::normalize(RouteContext::fromRequest($request)->getBasePath());
        $excerpt = $entry->getExcerpt();
        $description = $excerpt !== null ? trim(strip_tags($excerpt)) : null;

        $newsOwnerBaseSlug = MarketingSlugResolver::resolveBaseSlug($newsOwnerPage->getSlug());
        $landingPageUrl = $basePath . '/' . $newsOwnerPage->getSlug();
        $newsIndexUrl = $basePath . $newsBasePath;

        $locale = (string) ($request->getAttribute('lang') ?? 'de');
        $layoutData = $this->layoutData->loadLayoutData($pageNamespace, $page->getId(), $locale, $basePath);

        $breadcrumbs = [
            ['label' => $page->getTitle(), 'url' => $landingPageUrl],
            ['label' => 'Neuigkeiten', 'url' => $newsIndexUrl],
            ['label' => $entry->getTitle(), 'url' => null],
        ];

        return $view->render($response, 'marketing/landing_news_show.twig', array_merge([
            'page' => $page,
            'entry' => $entry,
            'newsBasePath' => $newsBasePath,
            'newsIndexUrl' => $newsIndexUrl,
            'landingPageUrl' => $landingPageUrl,
            'newsOwnerSlug' => $newsOwnerPage->getSlug(),
            'newsOwnerBaseSlug' => $newsOwnerBaseSlug,
            'namespace' => $pageNamespace,
            'pageNamespace' => $pageNamespace,
            'designNamespace' => $designNamespace,
            'appearance' => $appearance,
            'design' => $design,
            'renderContext' => $renderContext,
            'metaTitle' => sprintf('%s – %s', $page->getTitle(), $entry->getTitle()),
            'metaDescription' => $description,
            'cmsSlug' => $newsOwnerPage->getSlug(),
            'breadcrumbs' => $breadcrumbs,
        ], $layoutData));
    }

    // ── Magazin endpoints ────────────────────────────────────────────

    public function magazinIndex(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $currentPage = max(1, (int) ($params['page'] ?? 1));
        $searchQuery = isset($params['q']) ? trim((string) $params['q']) : null;
        if ($searchQuery === '') {
            $searchQuery = null;
        }
        $perPage = 8;

        [$namespace, $designNamespace, $design, $appearance, $basePath, $locale, $layoutData] =
            $this->resolveMagazinContext($request);

        $result = $this->news->getMagazinArticles($namespace, $currentPage, $perPage, null, $searchQuery);
        $categories = $this->news->getCategoriesForNamespace($namespace);
        $totalPages = (int) ceil($result['total'] / $perPage);

        $view = Twig::fromRequest($request);

        return $view->render($response, 'news/index.twig', array_merge([
            'articles' => $result['articles'],
            'categories' => $categories,
            'currentCategory' => null,
            'currentPage' => $currentPage,
            'totalPages' => $totalPages,
            'searchQuery' => $searchQuery,
            'namespace' => $namespace,
            'pageNamespace' => $namespace,
            'designNamespace' => $designNamespace,
            'appearance' => $appearance,
            'design' => $design,
            'basePath' => $basePath,
            'metaTitle' => 'News & Updates',
            'metaDescription' => 'Aktuelle Neuigkeiten und Artikel.',
        ], $layoutData));
    }

    public function magazinCategory(Request $request, Response $response, array $args): Response
    {
        $categorySlug = isset($args['categorySlug']) ? (string) $args['categorySlug'] : '';
        if ($categorySlug === '') {
            return $response->withStatus(404);
        }

        $params = $request->getQueryParams();
        $currentPage = max(1, (int) ($params['page'] ?? 1));
        $perPage = 8;

        [$namespace, $designNamespace, $design, $appearance, $basePath, $locale, $layoutData] =
            $this->resolveMagazinContext($request);

        $category = $this->news->getCategoryBySlug($namespace, $categorySlug);
        if ($category === null) {
            return $response->withStatus(404);
        }

        $result = $this->news->getMagazinArticles($namespace, $currentPage, $perPage, $categorySlug);
        $categories = $this->news->getCategoriesForNamespace($namespace);
        $totalPages = (int) ceil($result['total'] / $perPage);

        $view = Twig::fromRequest($request);

        return $view->render($response, 'news/index.twig', array_merge([
            'articles' => $result['articles'],
            'categories' => $categories,
            'currentCategory' => $category,
            'currentPage' => $currentPage,
            'totalPages' => $totalPages,
            'searchQuery' => null,
            'namespace' => $namespace,
            'pageNamespace' => $namespace,
            'designNamespace' => $designNamespace,
            'appearance' => $appearance,
            'design' => $design,
            'basePath' => $basePath,
            'metaTitle' => sprintf('News – %s', $category['name']),
            'metaDescription' => sprintf('Artikel in der Kategorie %s.', $category['name']),
        ], $layoutData));
    }

    public function magazinShow(Request $request, Response $response, array $args): Response
    {
        $slug = isset($args['slug']) ? (string) $args['slug'] : '';
        if ($slug === '') {
            return $response->withStatus(404);
        }

        [$namespace, $designNamespace, $design, $appearance, $basePath, $locale, $layoutData] =
            $this->resolveMagazinContext($request);

        $article = $this->news->findMagazinArticle($namespace, $slug);
        if ($article === null) {
            return $response->withStatus(404);
        }

        $relatedArticles = $this->news->getRelatedArticles((int) $article['id'], $namespace, 3);

        $breadcrumbs = [
            ['label' => 'Magazin', 'url' => $basePath . '/magazin'],
        ];
        if ($article['primary_category'] !== null) {
            $breadcrumbs[] = [
                'label' => $article['primary_category']['name'],
                'url' => $basePath . '/magazin/' . $article['primary_category']['slug'],
            ];
        }
        $breadcrumbs[] = ['label' => $article['title'], 'url' => null];

        $excerpt = $article['excerpt'];
        $description = $excerpt !== null ? trim(strip_tags($excerpt)) : null;

        $view = Twig::fromRequest($request);

        return $view->render($response, 'news/show.twig', array_merge([
            'article' => $article,
            'relatedArticles' => $relatedArticles,
            'breadcrumbs' => $breadcrumbs,
            'namespace' => $namespace,
            'pageNamespace' => $namespace,
            'designNamespace' => $designNamespace,
            'appearance' => $appearance,
            'design' => $design,
            'basePath' => $basePath,
            'metaTitle' => $article['title'],
            'metaDescription' => $description,
            'ogImage' => $article['image_url'] ?? null,
        ], $layoutData));
    }

    /**
     * Shared context resolution for all magazin endpoints.
     *
     * @return array{0: string, 1: string, 2: array, 3: array, 4: string, 5: string, 6: array}
     */
    private function resolveMagazinContext(Request $request): array
    {
        $namespaceContext = $this->namespaceResolver->resolve($request);
        $namespace = $namespaceContext->getNamespace();
        if ($namespace === '') {
            $namespace = PageService::DEFAULT_NAMESPACE;
        }

        $renderContext = $this->namespaceRenderContext->build($namespace);
        $designNamespace = $renderContext['namespace'];
        $design = $renderContext['design'];
        $appearance = $design['appearance'] ?? $this->namespaceAppearance->load($namespace);

        $basePath = BasePathHelper::normalize(RouteContext::fromRequest($request)->getBasePath());
        $locale = (string) ($request->getAttribute('lang') ?? 'de');
        $layoutData = $this->layoutData->loadLayoutData($namespace, null, $locale, $basePath);

        return [$namespace, $designNamespace, $design, $appearance, $basePath, $locale, $layoutData];
    }

    private function resolvePage(array $args, string $namespace): ?Page
    {
        $slug = isset($args['landingSlug']) ? (string) $args['landingSlug'] : '';
        if ($slug === '') {
            $slug = isset($args['slug']) ? (string) $args['slug'] : '';
        }
        if ($slug === '') {
            $slug = 'landing';
        }

        return $this->pages->findByKey($namespace, $slug);
    }

    private function buildNewsBasePath(Request $request, string $pageSlug): string
    {
        $path = $request->getUri()->getPath();
        if (preg_match('~^/m/([a-z0-9-]+)~', $path) === 1) {
            return sprintf('/m/%s/news', $pageSlug);
        }

        return sprintf('/%s/news', $pageSlug);
    }
}
