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
        return $view->render($response, 'admin/landingpage/edit.html.twig');
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
        $errors = $this->seoService->validate($data);
        if ($errors !== []) {
            $response->getBody()->write(json_encode(['errors' => $errors]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        $pageId = (int) ($data['pageId'] ?? 0);
        if ($pageId <= 0) {
            return $response->withStatus(400);
        }
        $config = new PageSeoConfig(
            $pageId,
            (string) $data['slug'],
            $data['metaTitle'] ?? null,
            $data['metaDescription'] ?? null,
            $data['canonicalUrl'] ?? null,
            $data['robotsMeta'] ?? null,
            $data['ogTitle'] ?? null,
            $data['ogDescription'] ?? null,
            $data['ogImage'] ?? null,
            $data['schemaJson'] ?? null,
            $data['hreflang'] ?? null
        );
        $this->seoService->save($config);
        return $response->withStatus(204);
    }
}
