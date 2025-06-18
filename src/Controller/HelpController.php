<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use App\Service\ConfigService;

class HelpController
{
    public function __invoke(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        $cfg = (new ConfigService(
            __DIR__ . '/../../config/config.json'
        ))->getConfig();

        return $view->render($response, 'help.twig', ['config' => $cfg]);
    }
}
