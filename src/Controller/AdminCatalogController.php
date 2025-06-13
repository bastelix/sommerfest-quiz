<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use App\Service\CatalogService;

class AdminCatalogController
{
    private CatalogService $service;

    public function __construct(CatalogService $service)
    {
        $this->service = $service;
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        $catalogsJson = $this->service->read('catalogs.json');
        $catalogs = [];
        if ($catalogsJson !== null) {
            $catalogs = json_decode($catalogsJson, true) ?? [];
        }
        $uri    = $request->getUri();
        $domain = getenv('DOMAIN');
        if ($domain !== false && $domain !== '') {
            if (preg_match('#^https?://#', $domain) === 1) {
                $baseUrl = rtrim($domain, '/');
            } else {
                $baseUrl = 'https://' . $domain;
            }
        } else {
            $baseUrl = $uri->getScheme() . '://' . $uri->getHost();
            $port    = $uri->getPort();
            if ($port !== null && !in_array($port, [80, 443], true)) {
                $baseUrl .= ':' . $port;
            }
        }

        return $view->render($response, 'kataloge.twig', [
            'kataloge' => $catalogs,
            'baseUrl' => $baseUrl,
        ]);
    }
}
