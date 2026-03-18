<?php

declare(strict_types=1);

namespace App\Controller\Marketing;

use App\Service\Seo\FeedService;
use App\Support\RequestDatabase;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Serves RSS 2.0 and Atom feeds for AI and feed reader consumption.
 */
final class FeedController
{
    public function rss(Request $request, Response $response): Response
    {
        return $this->respond($request, $response, 'rss');
    }

    public function atom(Request $request, Response $response): Response
    {
        return $this->respond($request, $response, 'atom');
    }

    private function respond(Request $request, Response $response, string $format): Response
    {
        $namespace = (string) ($request->getAttribute('namespace') ?? 'default');
        $uri = $request->getUri();
        $baseUrl = $uri->getScheme() . '://' . $uri->getHost();
        if ($uri->getPort() !== null && $uri->getPort() !== 443 && $uri->getPort() !== 80) {
            $baseUrl .= ':' . $uri->getPort();
        }

        $siteName = ucfirst($namespace);

        $pdo = RequestDatabase::resolve($request);
        $service = new FeedService(
            new \App\Service\PageService($pdo),
            new \App\Service\CmsPageWikiArticleService($pdo),
            new \App\Service\LandingNewsService($pdo)
        );

        if ($format === 'atom') {
            $xml = $service->generateAtom($namespace, $baseUrl, $siteName);
            $contentType = 'application/atom+xml; charset=utf-8';
        } else {
            $xml = $service->generateRss($namespace, $baseUrl, $siteName);
            $contentType = 'application/rss+xml; charset=utf-8';
        }

        $response->getBody()->write($xml);

        return $response
            ->withHeader('Content-Type', $contentType)
            ->withHeader('Cache-Control', 'public, max-age=3600');
    }
}
