<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\LegalPageResolver;
use App\Service\PageVariableService;
use App\Support\BasePathHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;

/**
 * Renders the privacy policy page.
 */
class DatenschutzController
{
    public function __invoke(Request $request, Response $response): Response {
        $resolver = new LegalPageResolver();
        $html = $resolver->resolve($request, 'datenschutz');
        if ($html === null) {
            return $response->withStatus(404);
        }
        $basePath = BasePathHelper::normalize(RouteContext::fromRequest($request)->getBasePath());
        $html = str_replace('{{ basePath }}', $basePath, $html);
        $html = PageVariableService::apply($html);

        $view = Twig::fromRequest($request);
        return $view->render($response, 'datenschutz.twig', [
            'content' => $html,
        ]);
    }
}
