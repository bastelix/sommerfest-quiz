<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use App\Service\ConfigService;
use App\Service\ResultService;

class AdminController
{
    public function __invoke(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        $cfg = (new ConfigService(__DIR__ . '/../../config/config.json'))->getConfig();
        $results = (new ResultService(__DIR__ . '/../../data/results.json'))->getAll();
        return $view->render($response, 'admin.twig', [
            'config' => $cfg,
            'results' => $results,
        ]);
    }
}
