<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\LegalPageResolver;
use App\Service\NamespaceResolver;
use App\Service\PageVariableService;
use App\Support\BasePathHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;

/**
 * Displays the legal notice page.
 */
class ImpressumController
{
    public function __invoke(Request $request, Response $response): Response {
        $resolver = new LegalPageResolver();
        $html = $resolver->resolve($request, 'impressum');
        if ($html === null) {
            return $response->withStatus(404);
        }
        $basePath = BasePathHelper::normalize(RouteContext::fromRequest($request)->getBasePath());
        $html = str_replace('{{ basePath }}', $basePath, $html);
        $namespace = (new NamespaceResolver())->resolve($request)->getNamespace();
        $html = PageVariableService::apply($html, $namespace);

        $view = Twig::fromRequest($request);
        return $view->render($response, 'impressum.twig', [
            'content' => $html,
        ]);
    }
}
