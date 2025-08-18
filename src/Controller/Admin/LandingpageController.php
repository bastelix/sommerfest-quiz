<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Application\Seo\PageSeoConfigService;
use App\Domain\PageSeoConfig;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/**
 * Handles admin actions for the marketing landing page.
 */
class LandingpageController
{
    private PageSeoConfigService $seoService;

    public function __construct(?PageSeoConfigService $seoService = null)
    {
        $this->seoService = $seoService ?? new PageSeoConfigService();
    }

    /**
     * Display the SEO edit form.
     */
    public function page(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        $config = $this->seoService->load(1);
        return $view->render($response, 'admin/landingpage/edit.html.twig', [
            'config' => $config ? $config->jsonSerialize() : [],
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

        if ($payload['pageId'] <= 0) {
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

        return $response->withStatus(204);
    }
}
