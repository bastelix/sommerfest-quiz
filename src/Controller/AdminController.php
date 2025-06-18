<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use App\Service\ConfigService;
use App\Service\ResultService;
use App\Service\CatalogService;
use App\Service\TeamService;
use App\Infrastructure\Database;

class AdminController
{
    public function __invoke(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        $pdo = Database::connectFromEnv();
        $cfg = (new ConfigService($pdo))->getConfig();
        $results = (new ResultService($pdo))->getAll();
        $catalogSvc = new CatalogService($pdo);
        $catalogsJson = $catalogSvc->read('catalogs.json');
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

        $teams = (new TeamService($pdo))->getAll();

        return $view->render($response, 'admin.twig', [
            'config' => $cfg,
            'results' => $results,
            'catalogs' => $catalogs,
            'teams' => $teams,
            'baseUrl' => $baseUrl,
        ]);
    }
}
