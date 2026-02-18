<?php

declare(strict_types=1);

namespace App\Controller;

use App\Controller\Marketing\PageController;
use App\Service\LegalPageResolver;
use App\Service\NamespaceResolver;
use App\Service\NamespaceAppearanceService;
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

        $namespace = (new NamespaceResolver())->resolve($request)->getNamespace();

        $trimmed = trim($html);
        if ($trimmed !== '' && str_starts_with($trimmed, '{')) {
            $decoded = json_decode($trimmed, true);
            if (is_array($decoded) && array_key_exists('blocks', $decoded)) {
                $request = $request->withAttribute('namespace', $namespace);
                $controller = new PageController('impressum');
                return $controller($request, $response);
            }
        }

        $basePath = BasePathHelper::normalize(RouteContext::fromRequest($request)->getBasePath());
        $html = str_replace('{{ basePath }}', $basePath, $html);
        $appearance = (new NamespaceAppearanceService())->load($namespace);
        $html = PageVariableService::apply($html, $namespace);

        $view = Twig::fromRequest($request);
        return $view->render($response, 'impressum.twig', [
            'content' => $html,
            'appearance' => $appearance,
            'pageNamespace' => $namespace,
        ]);
    }
}
