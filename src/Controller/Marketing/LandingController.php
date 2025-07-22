<?php

declare(strict_types=1);

namespace App\Controller\Marketing;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use Slim\Routing\RouteContext;

/**
 * Displays the landing page for the marketing site.
 */
class LandingController
{
    public function __invoke(Request $request, Response $response): Response
    {
        $path = dirname(__DIR__, 3) . '/content/landing.html';
        if (!is_file($path)) {
            return $response->withStatus(404);
        }
        $html = (string) file_get_contents($path);
        $basePath = RouteContext::fromRequest($request)->getBasePath();
        $html = str_replace('{{ basePath }}', $basePath, $html);

        $view = Twig::fromRequest($request);
        return $view->render($response, 'marketing/landing.twig', [
            'content' => $html,
        ]);
    }
}
