<?php

declare(strict_types=1);

namespace App\Controller\Marketing;

use App\Infrastructure\Database;
use App\Service\NamespaceRenderContextService;
use App\Support\BasePathHelper;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;

use function is_string;
use function trim;

final class CalhelpLandingController
{
    private NamespaceRenderContextService $namespaceRenderContext;

    public function __construct(?NamespaceRenderContextService $namespaceRenderContext = null)
    {
        $this->namespaceRenderContext = $namespaceRenderContext ?? new NamespaceRenderContextService();
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $basePath = BasePathHelper::normalize(RouteContext::fromRequest($request)->getBasePath());
        $namespace = $this->resolveNamespace($request);

        $view = Twig::fromRequest($request);
        $renderContext = $this->namespaceRenderContext->build($namespace);

        return $view->render($response, 'marketing/calhelp-landing.twig', [
            'namespace' => $namespace,
            'pageNamespace' => $namespace,
            'designNamespace' => $namespace,
            'renderContext' => $renderContext,
            'design' => $renderContext['design'],
            'appearance' => $renderContext['design']['appearance'] ?? [],
            'pageTheme' => $renderContext['design']['theme'] ?? 'light',
        ]);
    }

    private function resolveNamespace(Request $request): string
    {
        $namespace = $request->getAttribute('pageNamespace')
            ?? $request->getAttribute('namespace');
        if (is_string($namespace) && trim($namespace) !== '') {
            return trim($namespace);
        }

        return 'calhelp';
    }
}
