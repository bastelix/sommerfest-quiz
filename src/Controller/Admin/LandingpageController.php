<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Application\Seo\PageSeoConfigService;
use App\Domain\Page;
use App\Domain\PageSeoConfig;
use App\Infrastructure\Database;
use App\Service\DomainService;
use App\Service\Marketing\PageSeoAiErrorMapper;
use App\Service\Marketing\PageSeoAiGenerator;
use App\Service\NamespaceResolver;
use App\Service\PageService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;
use RuntimeException;

use function in_array;
use function is_array;
use function json_decode;
use function json_encode;
use function trim;

/**
 * Handles admin actions for the marketing landing page.
 */
class LandingpageController
{
    private PageSeoConfigService $seoService;
    private PageService $pageService;
    private DomainService $domainService;
    private NamespaceResolver $namespaceResolver;

    /** @var string[] */
    public const EXCLUDED_SLUGS = ['impressum', 'datenschutz', 'faq', 'lizenz'];

    public function __construct(
        ?PageSeoConfigService $seoService = null,
        ?PageService $pageService = null,
        ?DomainService $domainService = null,
        ?NamespaceResolver $namespaceResolver = null
    ) {
        $this->seoService = $seoService ?? new PageSeoConfigService();
        $this->pageService = $pageService ?? new PageService();
        $this->domainService = $domainService ?? new DomainService(Database::connectFromEnv());
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

    public function importFromAi(Request $request, Response $response): Response
    {
        $payload = $this->parseJsonBody($request);
        if ($payload === null) {
            return $response->withStatus(400);
        }

        $pageId = (int) ($payload['pageId'] ?? $payload['page_id'] ?? 0);
        if ($pageId <= 0) {
            return $response->withStatus(422);
        }

        $namespace = $this->namespaceResolver->resolve($request)->getNamespace();
        $page = $this->pageService->findById($pageId);
        if (
            $page === null
            || $page->getNamespace() !== $namespace
            || in_array($page->getSlug(), self::EXCLUDED_SLUGS, true)
        ) {
            return $response->withStatus(404);
        }

        $domain = $this->domainService->normalizeDomain(isset($payload['domain']) ? (string) $payload['domain'] : '');
        if ($domain === '') {
            $domain = $this->domainService->normalizeDomain($request->getUri()->getHost())
                ?: $this->domainService->normalizeDomain((string) getenv('MAIN_DOMAIN'))
                ?: '';
        }

        $promptTemplate = isset($payload['promptTemplate']) && is_string($payload['promptTemplate'])
            ? trim($payload['promptTemplate'])
            : null;

        try {
            $generator = new PageSeoAiGenerator();
            $config = $generator->generate($page, $domain, $promptTemplate);
        } catch (RuntimeException $exception) {
            $mapper = new PageSeoAiErrorMapper();
            $mapped = $mapper->map($exception);

            $response->getBody()->write(json_encode([
                'error' => $mapped['message'],
                'error_code' => $mapped['error_code'],
            ], JSON_PRETTY_PRINT));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus($mapped['status']);
        }

        $response->getBody()->write(json_encode(['config' => $config], JSON_PRETTY_PRINT));

        return $response->withHeader('Content-Type', 'application/json');
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
    private function determineSelectedPage(array $pages, ?string $slug): Page {
        $normalizedSlug = $slug !== null ? trim($slug) : '';

        if ($normalizedSlug === '') {
            return $pages[0];
        }

        foreach ($pages as $page) {
            if ($page->getSlug() === $normalizedSlug) {
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
        $mainDomain = $this->domainService->normalizeDomain((string) getenv('MAIN_DOMAIN'));
        $currentHost = $this->domainService->normalizeDomain($host);
        $fallbackHost = $currentHost !== '' ? $currentHost : $mainDomain;

        $result = [];
        foreach ($pages as $page) {
            $pageDomains = [];
            if ($page->getSlug() === 'landing' && $mainDomain !== '') {
                $pageDomains[] = $mainDomain;
            }
            if ($pageDomains === [] && $fallbackHost !== '') {
                $pageDomains[] = $fallbackHost;
            }
            $pageDomains = array_values(array_unique(array_filter($pageDomains)));

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
            $pageDomains = [];
            if ($selected->getSlug() === 'landing' && $mainDomain !== '') {
                $pageDomains[] = $mainDomain;
            }
            if ($pageDomains === [] && $fallbackHost !== '') {
                $pageDomains[] = $fallbackHost;
            }
            $pageDomains = array_values(array_unique(array_filter($pageDomains)));

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

    private function parseJsonBody(Request $request): ?array
    {
        $data = $request->getParsedBody();
        if (is_array($data)) {
            return $data;
        }

        $body = $request->getBody();
        if ($body->isSeekable()) {
            $body->rewind();
        }

        $raw = (string) $body;
        if ($raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }
}
