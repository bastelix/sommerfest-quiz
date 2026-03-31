<?php

declare(strict_types=1);

namespace App\Controller;

use App\Controller\Traits\CmsBlockDetectionTrait;
use App\Service\NamespaceResolver;
use App\Service\NamespaceAppearanceService;
use App\Service\PageService;
use App\Support\BasePathHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;

/**
 * Shows the open-source license information.
 */
class LizenzController
{
    use CmsBlockDetectionTrait;

    public function __invoke(Request $request, Response $response): Response {
        $service = new PageService();
        $namespace = (new NamespaceResolver())->resolve($request)->getNamespace();
        $html = $service->getByKey($namespace, 'lizenz');
        if ($html === null) {
            return $response->withStatus(404);
        }

        if ($this->isCmsBlockContent($html)) {
            return $this->renderCmsPage($request, $response, $namespace, 'lizenz');
        }

        $appearance = (new NamespaceAppearanceService())->load($namespace);
        $basePath = BasePathHelper::normalize(RouteContext::fromRequest($request)->getBasePath());
        $html = str_replace('{{ basePath }}', $basePath, $html);

        $view = Twig::fromRequest($request);
        return $view->render($response, 'lizenz.twig', [
            'content' => $html,
            'appearance' => $appearance,
            'pageNamespace' => $namespace,
        ]);
    }
}
