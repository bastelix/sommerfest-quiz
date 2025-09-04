<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\PageService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use Slim\Routing\RouteContext;
use App\Service\PageVariableService;

/**
 * Renders the privacy policy page.
 */
class DatenschutzController
{
    public function __invoke(Request $request, Response $response): Response
    {
        $service = new PageService();
        $html = $service->get('datenschutz');
        if ($html === null) {
            return $response->withStatus(404);
        }
        $basePath = RouteContext::fromRequest($request)->getBasePath();
        $html = str_replace('{{ basePath }}', $basePath, $html);
        $html = PageVariableService::apply($html);

        $view = Twig::fromRequest($request);
        return $view->render($response, 'datenschutz.twig', [
            'content' => $html,
        ]);
    }
}
