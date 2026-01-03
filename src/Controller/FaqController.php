<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\NamespaceResolver;
use App\Service\NamespaceAppearanceService;
use App\Service\PageService;
use App\Support\BasePathHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;

/**
 * Displays the frequently asked questions.
 */
class FaqController
{
    /**
     * Render the FAQ page.
     */
    public function __invoke(Request $request, Response $response): Response {
        $service = new PageService();
        $namespace = (new NamespaceResolver())->resolve($request)->getNamespace();
        $appearance = (new NamespaceAppearanceService())->load($namespace);
        $html = $service->getByKey($namespace, 'faq');
        if ($html === null) {
            return $response->withStatus(404);
        }
        $basePath = BasePathHelper::normalize(RouteContext::fromRequest($request)->getBasePath());
        $html = str_replace('{{ basePath }}', $basePath, $html);

        $view = Twig::fromRequest($request);
        return $view->render($response, 'faq.twig', [
            'content' => $html,
            'appearance' => $appearance,
            'pageNamespace' => $namespace,
        ]);
    }
}
