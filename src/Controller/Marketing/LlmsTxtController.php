<?php

declare(strict_types=1);

namespace App\Controller\Marketing;

use App\Service\Seo\LlmsTxtService;
use App\Support\RequestDatabase;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Serves llms.txt and llms-full.txt for AI/LLM consumption.
 *
 * @see https://llmstxt.org/
 */
final class LlmsTxtController
{
    private LlmsTxtService $service;

    public function __construct(?LlmsTxtService $service = null)
    {
        $this->service = $service ?? new LlmsTxtService();
    }

    public function index(Request $request, Response $response): Response
    {
        return $this->respond($request, $response, false);
    }

    public function full(Request $request, Response $response): Response
    {
        return $this->respond($request, $response, true);
    }

    private function respond(Request $request, Response $response, bool $full): Response
    {
        $namespace = (string) ($request->getAttribute('namespace') ?? 'default');
        $uri = $request->getUri();
        $baseUrl = $uri->getScheme() . '://' . $uri->getHost();
        if ($uri->getPort() !== null && $uri->getPort() !== 443 && $uri->getPort() !== 80) {
            $baseUrl .= ':' . $uri->getPort();
        }

        $pdo = RequestDatabase::resolve($request);
        $service = new LlmsTxtService(
            new \App\Service\PageService($pdo),
            new \App\Application\Seo\PageSeoConfigService($pdo),
            new \App\Service\CmsPageWikiArticleService($pdo),
            new \App\Service\LandingNewsService($pdo)
        );

        $content = $full
            ? $service->generateFull($namespace, $baseUrl)
            : $service->generate($namespace, $baseUrl);

        $response->getBody()->write($content);

        return $response
            ->withHeader('Content-Type', 'text/plain; charset=utf-8')
            ->withHeader('Cache-Control', 'public, max-age=3600');
    }
}
