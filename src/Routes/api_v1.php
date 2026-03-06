<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Application\Middleware\ApiTokenAuthMiddleware;
use App\Controller\Api\V1\NamespacePageController;

return function (\Slim\App $app): void {
    $app->group('/api/v1', function (\Slim\Routing\RouteCollectorProxy $group) {
        $group->get('/namespaces/{ns:[a-z0-9\-]+}/pages', function (
            Request $request,
            Response $response,
            array $args
        ): Response {
            $controller = new NamespacePageController();
            return $controller->list($request, $response, $args);
        })->add(new ApiTokenAuthMiddleware(null, null, NamespacePageController::SCOPE_CMS_READ));

        $group->put('/namespaces/{ns:[a-z0-9\-]+}/pages/{slug:[a-z0-9\-]+}', function (
            Request $request,
            Response $response,
            array $args
        ): Response {
            $controller = new NamespacePageController();
            return $controller->upsert($request, $response, $args);
        })->add(new ApiTokenAuthMiddleware(null, null, NamespacePageController::SCOPE_CMS_WRITE));
    });
};
