<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use Slim\Routing\RouteContext;

/**
 * Displays the frequently asked questions.
 */
class FaqController
{
    /**
     * Render the FAQ page.
     */
    public function __invoke(Request $request, Response $response): Response
    {
        $path = dirname(__DIR__, 2) . '/content/faq.html';
        if (!is_file($path)) {
            return $response->withStatus(404);
        }
        $html = (string) file_get_contents($path);
        $basePath = RouteContext::fromRequest($request)->getBasePath();
        $html = str_replace('{{ basePath }}', $basePath, $html);

        $view = Twig::fromRequest($request);
        return $view->render($response, 'faq.twig', [
            'content' => $html,
        ]);
    }
}
