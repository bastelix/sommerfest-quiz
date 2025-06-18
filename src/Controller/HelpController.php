<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use App\Service\ConfigService;

class HelpController
{
    private ConfigService $config;

    public function __construct(ConfigService $config)
    {
        $this->config = $config;
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        $cfg = $this->config->getConfig();

        return $view->render($response, 'help.twig', ['config' => $cfg]);
    }
}
