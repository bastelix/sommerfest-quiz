<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Application\Seo\PageSeoConfigService;
use App\Domain\Page;
use App\Domain\PageSeoConfig;
use App\Infrastructure\Database;
use App\Service\DomainStartPageService;
use App\Service\NamespaceResolver;
use App\Service\PageService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;

/**
 * Handles admin actions for the marketing landing page.
 */
class LandingpageController
{
    private PageSeoConfigService $seoService;
    private PageService $pageService;
    private DomainStartPageService $domainService;
    private NamespaceResolver $namespaceResolver;

    /** @var string[] */
    public const EXCLUDED_SLUGS = ['impressum', 'datenschutz', 'faq', 'lizenz'];

    public function __construct(
        ?PageSeoConfigService $seoService = null,
        ?PageService $pageService = null,
        ?DomainStartPageService $domainService = null,
        ?NamespaceResolver $namespaceResolver = null
    ) {
        $this->seoService = $seoService ?? new PageSeoConfigService();
        $this->pageService = $pageService ?? new PageService();
        $this->domainService = $domainService ?? new DomainStartPageService(Database::connectFromEnv());
        $this->namespaceResolver = $namespaceResolver ?? new NamespaceResolver();
    }

    /**
     * Display the SEO edit form.
     */
    public function page(Request $request, Response $response): Response {
        $view = Twig::fromRequest($request);
        $namespace = $this->namespaceResolver->resolve($request)->getNamespace();
        $pages = $this->getMarketingPages($namespace);
        if ($pages === []) {
            return $view->render($response, 'admin/landingpage/edit.html.twig', [
                'config' => [],
                'seoPages' => [],
                'selectedPageId' => null,
            ]);
        }

        $query = $request->getQueryParams();
        $selectedSlug = isset($query['slug']) ? (string) $query['slug'] : '';
        $selectedPage = $this->determineSelectedPage($pages, $selectedSlug);

        $seoPages = $this->buildSeoPageList($pages, $selectedPage, $request->getUri()->getHost());
        $config = $seoPages[$selectedPage->getId()]['config'];

        return $view->render($response, 'admin/landingpage/edit.html.twig', [
            'config' => $config,
            'seoPages' => array_values($seoPages),
            'selectedPageId' => $selectedPage->getId(),
        ]);
    }

    /**
     * Persist SEO settings submitted via POST.
     */
    public function save(Request $request, Response $response): Response {
        $data = $request->getParsedBody();
        if (!is_array($data)) {
            return $response->withStatus(400);
        }

        $normalizedDomain = isset($data['domain'])
            ? $this->domainService->normalizeDomain((string) $data['domain'])
            : '';

        $payload = [
            'pageId' => (int) ($data['pageId'] ?? 0),
            'slug' => (string) ($data['slug'] ?? ''),
            'metaTitle' => $data['metaTitle'] ?? $data['meta_title'] ?? null,
            'metaDescription' => $data['metaDescription'] ?? $data['meta_description'] ?? null,
            'canonicalUrl' => $data['canonicalUrl'] ?? $data['canonical'] ?? null,
            'robotsMeta' => $data['robotsMeta'] ?? $data['robots'] ?? null,
            'ogTitle' => $data['ogTitle'] ?? $data['og_title'] ?? null,
            'ogDescription' => $data['ogDescription'] ?? $data['og_description'] ?? null,
            'ogImage' => $data['ogImage'] ?? $data['og_image'] ?? null,
            'schemaJson' => $data['schemaJson'] ?? $data['schema'] ?? null,
            'hreflang' => $data['hreflang'] ?? null,
            'domain' => $normalizedDomain !== '' ? $normalizedDomain : null,
            'faviconPath' => $data['faviconPath'] ?? $data['favicon_path'] ?? null,
        ];

        $errors = $this->seoService->validate($payload);
        if ($errors !== []) {
            $response->getBody()->write(json_encode(['errors' => $errors]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $payload['faviconPath'] = $this->seoService->normalizeFaviconPath(
            isset($payload['faviconPath']) ? (string) $payload['faviconPath'] : null
        );

        $page = $this->pageService->findById($payload['pageId']);
        $namespace = $this->namespaceResolver->resolve($request)->getNamespace();
        if (
            $page === null
            || $page->getNamespace() !== $namespace
            || in_array($page->getSlug(), self::EXCLUDED_SLUGS, true)
        ) {
            return $response->withStatus(400);
        }

        $config = new PageSeoConfig(
            $payload['pageId'],
            $payload['slug'],
            $payload['metaTitle'],
            $payload['metaDescription'],
            $payload['canonicalUrl'],
            $payload['robotsMeta'],
            $payload['ogTitle'],
            $payload['ogDescription'],
            $payload['ogImage'],
            $payload['schemaJson'],
            $payload['hreflang'],
            $payload['domain'],
            $payload['faviconPath']
        );

        $this->seoService->save($config);

        $accept = $request->getHeaderLine('Accept');
        if (str_contains($accept, 'application/json')) {
            $response->getBody()->write(json_encode([
                'status' => 'ok',
                'config' => $config->jsonSerialize(),
                'page' => [
                    'id' => $page->getId(),
                    'slug' => $page->getSlug(),
                    'title' => $page->getTitle(),
                ],
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        }

        $base = RouteContext::fromRequest($request)->getBasePath();
        $query = [
            'slug' => $page->getSlug(),
            'namespace' => $namespace,
        ];
        return $response
            ->withHeader('Location', $base . '/admin/landingpage/seo?' . http_build_query($query))
            ->withStatus(303);
    }

    /**
     * @return Page[]
     */
    private function getMarketingPages(string $namespace): array {
        $pages = $this->pageService->getAllForNamespace($namespace);

        return array_values(array_filter(
            $pages,
            static fn (Page $page): bool => !in_array($page->getSlug(), self::EXCLUDED_SLUGS, true)
        ));
    }

    /**
     * @param Page[] $pages
     */
    private function determineSelectedPage(array $pages, string $slug): Page {
        foreach ($pages as $page) {
            if ($slug !== '' && $page->getSlug() === $slug) {
                return $page;
            }
        }

        return $pages[0];
    }

    /**
     * @param Page[] $pages
     * @return array<int,array{id:int,slug:string,title:string,config:array<string,mixed>}> keyed by page id
     */
    private function buildSeoPageList(array $pages, Page $selected, string $host): array {
        $mappings = $this->domainService->getAllMappings();
        $domainsBySlug = [];
        foreach ($mappings as $domain => $config) {
            $slug = trim($config['start_page']);
            if ($slug === '') {
                continue;
            }
            $domainsBySlug[$slug][] = $domain;
        }

        $mainDomain = $this->domainService->normalizeDomain((string) getenv('MAIN_DOMAIN'));
        if ($mainDomain !== '') {
            $domainsBySlug['landing'][] = $mainDomain;
        }

        $currentHost = $this->domainService->normalizeDomain($host);
        $fallbackHost = $currentHost !== '' ? $currentHost : $mainDomain;

        $result = [];
        foreach ($pages as $page) {
            $pageDomains = $domainsBySlug[$page->getSlug()] ?? [];
            if ($pageDomains === [] && $page->getSlug() === 'landing' && $mainDomain !== '') {
                $pageDomains[] = $mainDomain;
            }
            if ($pageDomains === [] && $fallbackHost !== '') {
                $pageDomains[] = $fallbackHost;
            }
            $pageDomains = array_values(
                array_unique(
                    array_filter($pageDomains, static fn ($value): bool => $value !== '')
                )
            );

            $config = $this->seoService->load($page->getId());
            $configData = $config ? $config->jsonSerialize() : $this->seoService->defaultConfig($page->getId());

            if (($configData['domain'] ?? null) !== null) {
                $domainValue = (string) $configData['domain'];
                if ($domainValue !== '' && !in_array($domainValue, $pageDomains, true)) {
                    array_unshift($pageDomains, $domainValue);
                }
            } elseif ($pageDomains !== []) {
                $configData['domain'] = $pageDomains[0];
            }

            $result[$page->getId()] = [
                'id' => $page->getId(),
                'slug' => $page->getSlug(),
                'title' => $page->getTitle(),
                'domains' => $pageDomains,
                'config' => $configData,
            ];
        }

        if (!isset($result[$selected->getId()])) {
            $pageDomains = $domainsBySlug[$selected->getSlug()] ?? [];
            if ($pageDomains === [] && $selected->getSlug() === 'landing' && $mainDomain !== '') {
                $pageDomains[] = $mainDomain;
            }
            if ($pageDomains === [] && $fallbackHost !== '') {
                $pageDomains[] = $fallbackHost;
            }
            $pageDomains = array_values(
                array_unique(
                    array_filter($pageDomains, static fn ($value): bool => $value !== '')
                )
            );

            $configData = $this->seoService->defaultConfig($selected->getId());
            if (($configData['domain'] ?? null) === null && $pageDomains !== []) {
                $configData['domain'] = $pageDomains[0];
            }

            $result[$selected->getId()] = [
                'id' => $selected->getId(),
                'slug' => $selected->getSlug(),
                'title' => $selected->getTitle(),
                'domains' => $pageDomains,
                'config' => $configData,
            ];
        }

        return $result;
    }
}
