<?php

declare(strict_types=1);

namespace App\Controller\Marketing;

use App\Service\Seo\SitemapService;
use App\Support\RequestDatabase;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Serves a dynamic XML sitemap for the current namespace/domain.
 */
final class SitemapController
{
    public function __invoke(Request $request, Response $response): Response
    {
        $namespace = (string) ($request->getAttribute('namespace') ?? 'default');
        $uri = $request->getUri();
        $baseUrl = $uri->getScheme() . '://' . $uri->getHost();
        if ($uri->getPort() !== null && $uri->getPort() !== 443 && $uri->getPort() !== 80) {
            $baseUrl .= ':' . $uri->getPort();
        }

        $pdo = RequestDatabase::resolve($request);
        $service = new SitemapService(
            new \App\Service\PageService($pdo),
            new \App\Application\Seo\PageSeoConfigService($pdo),
            new \App\Service\CmsPageWikiArticleService($pdo)
        );

        $xml = $service->generate($namespace, $baseUrl);
        $response->getBody()->write($xml);

        return $response
            ->withHeader('Content-Type', 'application/xml; charset=utf-8')
            ->withHeader('Cache-Control', 'public, max-age=3600');
    }
}
