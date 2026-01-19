<?php

declare(strict_types=1);

namespace App\Controller\Marketing;

use App\Domain\Page;
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

use function preg_match;
use function sprintf;
use function strip_tags;

class LandingNewsController
{
    private LandingNewsService $news;

    private PageService $pages;
    private NamespaceResolver $namespaceResolver;
    private NamespaceAppearanceService $namespaceAppearance;
    private NamespaceRenderContextService $namespaceRenderContext;

    public function __construct(
        ?LandingNewsService $news = null,
        ?PageService $pages = null,
        ?NamespaceResolver $namespaceResolver = null,
        ?NamespaceAppearanceService $namespaceAppearance = null,
        ?NamespaceRenderContextService $namespaceRenderContext = null
    ) {
        $this->news = $news ?? new LandingNewsService();
        $this->pages = $pages ?? new PageService();
        $this->namespaceResolver = $namespaceResolver ?? new NamespaceResolver();
        $this->namespaceAppearance = $namespaceAppearance ?? new NamespaceAppearanceService();
        $this->namespaceRenderContext = $namespaceRenderContext ?? new NamespaceRenderContextService();
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

        return $view->render($response, 'marketing/landing_news_index.twig', [
            'page' => $page,
            'entries' => $entries,
            'newsBasePath' => $newsBasePath,
            'newsIndexUrl' => $basePath . $newsBasePath,
            'landingPageUrl' => $basePath . '/' . $newsOwnerPage->getSlug(),
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
        ]);
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

        return $view->render($response, 'marketing/landing_news_show.twig', [
            'page' => $page,
            'entry' => $entry,
            'newsBasePath' => $newsBasePath,
            'newsIndexUrl' => $basePath . $newsBasePath,
            'landingPageUrl' => $basePath . '/' . $newsOwnerPage->getSlug(),
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
        ]);
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
