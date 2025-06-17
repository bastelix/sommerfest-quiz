<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Service\ConfigService;
use App\Service\CatalogService;
use Slim\Views\Twig;

class HomeController
{
    public function __invoke(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        $cfg = (new ConfigService(
            __DIR__ . '/../../data/config.json',
            __DIR__ . '/../../config/config.json'
        ))->getConfig();

        $catalogService = new CatalogService(__DIR__ . '/../../data/kataloge');
        $catalogsJson = $catalogService->read('catalogs.json');
        $catalogs = [];
        if ($catalogsJson !== null) {
            $catalogs = json_decode($catalogsJson, true) ?? [];
        }

        if (($cfg['competitionMode'] ?? false) === true) {
            $params = $request->getQueryParams();
            $id = $params['katalog'] ?? '';
            $allowedIds = array_map(static fn($c) => $c['id'] ?? '', $catalogs);
            if ($id === '' || !in_array($id, $allowedIds, true)) {
                return $response
                    ->withHeader('Location', '/help')
                    ->withStatus(302);
            }
        }

        return $view->render($response, 'index.twig', [
            'config' => $cfg,
            'catalogs' => $catalogs,
        ]);
    }
}
