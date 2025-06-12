<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Service\ConfigService;
use Slim\Views\Twig;

class HomeController
{
    public function __invoke(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        $cfg = (new ConfigService(__DIR__ . '/../../data/config.json'))->getConfig();
        return $view->render($response, 'index.twig', ['config' => $cfg]);
    }
}
