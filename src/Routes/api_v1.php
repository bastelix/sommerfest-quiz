<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Application\Middleware\ApiTokenAuthMiddleware;
use App\Controller\Api\V1\NamespacePageController;
use App\Controller\Api\V1\NamespaceMenuController;

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

        $group->get('/namespaces/{ns:[a-z0-9\-]+}/pages/tree', function (
            Request $request,
            Response $response,
            array $args
        ): Response {
            $controller = new NamespacePageController();
            return $controller->tree($request, $response, $args);
        })->add(new ApiTokenAuthMiddleware(null, null, NamespacePageController::SCOPE_CMS_READ));

        $group->put('/namespaces/{ns:[a-z0-9\-]+}/pages/{slug:[a-z0-9\-]+}', function (
            Request $request,
            Response $response,
            array $args
        ): Response {
            $controller = new NamespacePageController();
            return $controller->upsert($request, $response, $args);
        })->add(new ApiTokenAuthMiddleware(null, null, NamespacePageController::SCOPE_CMS_WRITE));

        // Menus
        $group->get('/namespaces/{ns:[a-z0-9\-]+}/menus', function (Request $request, Response $response, array $args): Response {
            return (new NamespaceMenuController())->listMenus($request, $response, $args);
        })->add(new ApiTokenAuthMiddleware(null, null, NamespaceMenuController::SCOPE_MENU_READ));

        $group->post('/namespaces/{ns:[a-z0-9\-]+}/menus', function (Request $request, Response $response, array $args): Response {
            return (new NamespaceMenuController())->createMenu($request, $response, $args);
        })->add(new ApiTokenAuthMiddleware(null, null, NamespaceMenuController::SCOPE_MENU_WRITE));

        $group->patch('/namespaces/{ns:[a-z0-9\-]+}/menus/{menuId:[0-9]+}', function (Request $request, Response $response, array $args): Response {
            return (new NamespaceMenuController())->updateMenu($request, $response, $args);
        })->add(new ApiTokenAuthMiddleware(null, null, NamespaceMenuController::SCOPE_MENU_WRITE));

        $group->delete('/namespaces/{ns:[a-z0-9\-]+}/menus/{menuId:[0-9]+}', function (Request $request, Response $response, array $args): Response {
            return (new NamespaceMenuController())->deleteMenu($request, $response, $args);
        })->add(new ApiTokenAuthMiddleware(null, null, NamespaceMenuController::SCOPE_MENU_WRITE));

        $group->get('/namespaces/{ns:[a-z0-9\-]+}/menus/{menuId:[0-9]+}/items', function (Request $request, Response $response, array $args): Response {
            return (new NamespaceMenuController())->listMenuItems($request, $response, $args);
        })->add(new ApiTokenAuthMiddleware(null, null, NamespaceMenuController::SCOPE_MENU_READ));

        $group->post('/namespaces/{ns:[a-z0-9\-]+}/menus/{menuId:[0-9]+}/items', function (Request $request, Response $response, array $args): Response {
            return (new NamespaceMenuController())->createMenuItem($request, $response, $args);
        })->add(new ApiTokenAuthMiddleware(null, null, NamespaceMenuController::SCOPE_MENU_WRITE));

        $group->patch('/namespaces/{ns:[a-z0-9\-]+}/menus/{menuId:[0-9]+}/items/{itemId:[0-9]+}', function (Request $request, Response $response, array $args): Response {
            return (new NamespaceMenuController())->updateMenuItem($request, $response, $args);
        })->add(new ApiTokenAuthMiddleware(null, null, NamespaceMenuController::SCOPE_MENU_WRITE));

        $group->delete('/namespaces/{ns:[a-z0-9\-]+}/menus/{menuId:[0-9]+}/items/{itemId:[0-9]+}', function (Request $request, Response $response, array $args): Response {
            return (new NamespaceMenuController())->deleteMenuItem($request, $response, $args);
        })->add(new ApiTokenAuthMiddleware(null, null, NamespaceMenuController::SCOPE_MENU_WRITE));
    });
};
