<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Application\Seo\PageSeoConfigService;
use App\Domain\Page;
use App\Domain\PageSeoConfig;
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

    /** @var string[] */
    public const EXCLUDED_SLUGS = ['impressum', 'datenschutz', 'faq', 'lizenz'];

    public function __construct(?PageSeoConfigService $seoService = null, ?PageService $pageService = null)
    {
        $this->seoService = $seoService ?? new PageSeoConfigService();
        $this->pageService = $pageService ?? new PageService();
    }

    /**
     * Display the SEO edit form.
     */
    public function page(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        $pages = $this->getMarketingPages();
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

        $seoPages = $this->buildSeoPageList($pages, $selectedPage);
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
    public function save(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        if (!is_array($data)) {
            return $response->withStatus(400);
        }

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
        ];

        $errors = $this->seoService->validate($payload);
        if ($errors !== []) {
            $response->getBody()->write(json_encode(['errors' => $errors]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $page = $this->pageService->findById($payload['pageId']);
        if ($page === null || in_array($page->getSlug(), self::EXCLUDED_SLUGS, true)) {
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
            $payload['hreflang']
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
        return $response
            ->withHeader('Location', $base . '/admin/landingpage/seo?slug=' . rawurlencode($page->getSlug()))
            ->withStatus(303);
    }

    /**
     * @return Page[]
     */
    private function getMarketingPages(): array
    {
        $pages = $this->pageService->getAll();

        return array_values(array_filter(
            $pages,
            static fn (Page $page): bool => !in_array($page->getSlug(), self::EXCLUDED_SLUGS, true)
        ));
    }

    /**
     * @param Page[] $pages
     */
    private function determineSelectedPage(array $pages, string $slug): Page
    {
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
    private function buildSeoPageList(array $pages, Page $selected): array
    {
        $result = [];
        foreach ($pages as $page) {
            $config = $this->seoService->load($page->getId());
            $result[$page->getId()] = [
                'id' => $page->getId(),
                'slug' => $page->getSlug(),
                'title' => $page->getTitle(),
                'config' => $config ? $config->jsonSerialize() : $this->seoService->defaultConfig($page->getId()),
            ];
        }

        if (!isset($result[$selected->getId()])) {
            $result[$selected->getId()] = [
                'id' => $selected->getId(),
                'slug' => $selected->getSlug(),
                'title' => $selected->getTitle(),
                'config' => $this->seoService->defaultConfig($selected->getId()),
            ];
        }

        return $result;
    }
}
