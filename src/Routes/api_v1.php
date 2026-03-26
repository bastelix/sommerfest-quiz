<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Application\Middleware\ApiTokenAuthMiddleware;
use App\Controller\Api\V1\NamespaceDesignController;
use App\Controller\Api\V1\NamespacePageController;
use App\Controller\Api\V1\NamespaceMenuController;
use App\Controller\Api\V1\NamespaceNewsController;
use App\Controller\Api\V1\NamespaceQuizController;
use App\Controller\Api\V1\NamespaceTicketController;
use App\Controller\Api\V1\CustomerController;

return function (\Slim\App $app): void {
    // Public registration endpoint (no auth required)
    $app->post('/api/v1/register', function (Request $request, Response $response): Response {
        return (new CustomerController())->register($request, $response);
    });

    // Public subscription plans endpoint (display-safe data only, no auth required)
    $app->get('/api/v1/subscription/plans', function (Request $request, Response $response): Response {
        $config = \App\Service\StripeService::isConfigured();
        $plans = [];

        if ($config['ok']) {
            try {
                $service = new \App\Service\StripeService();
                $products = $service->listProducts();
                // Filter to display-safe fields only (no price_id, no limits)
                foreach ($products as $product) {
                    $plans[] = [
                        'plan_key' => $product['plan_key'],
                        'name' => $product['name'],
                        'description' => $product['description'],
                        'price' => $product['price'],
                        'currency' => $product['currency'],
                        'interval' => $product['interval'],
                        'features' => $product['features'],
                        'highlighted' => $product['highlighted'],
                        'trial_days' => $product['trial_days'],
                        'sort_order' => $product['sort_order'],
                    ];
                }
            } catch (\Throwable) {
                // Return empty array on failure
            }
        }

        $response->getBody()->write((string) json_encode($plans));
        return $response->withHeader('Content-Type', 'application/json');
    });

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
        $group->get('/namespaces/{ns:[a-z0-9\-]+}/menus', function (
            Request $request,
            Response $response,
            array $args
        ): Response {
            return (new NamespaceMenuController())->listMenus($request, $response, $args);
        })->add(new ApiTokenAuthMiddleware(null, null, NamespaceMenuController::SCOPE_MENU_READ));

        $group->post('/namespaces/{ns:[a-z0-9\-]+}/menus', function (
            Request $request,
            Response $response,
            array $args
        ): Response {
            return (new NamespaceMenuController())->createMenu($request, $response, $args);
        })->add(new ApiTokenAuthMiddleware(null, null, NamespaceMenuController::SCOPE_MENU_WRITE));

        $group->patch('/namespaces/{ns:[a-z0-9\-]+}/menus/{menuId:[0-9]+}', function (
            Request $request,
            Response $response,
            array $args
        ): Response {
            return (new NamespaceMenuController())->updateMenu($request, $response, $args);
        })->add(new ApiTokenAuthMiddleware(null, null, NamespaceMenuController::SCOPE_MENU_WRITE));

        $group->delete('/namespaces/{ns:[a-z0-9\-]+}/menus/{menuId:[0-9]+}', function (
            Request $request,
            Response $response,
            array $args
        ): Response {
            return (new NamespaceMenuController())->deleteMenu($request, $response, $args);
        })->add(new ApiTokenAuthMiddleware(null, null, NamespaceMenuController::SCOPE_MENU_WRITE));

        $group->get('/namespaces/{ns:[a-z0-9\-]+}/menus/{menuId:[0-9]+}/items', function (
            Request $request,
            Response $response,
            array $args
        ): Response {
            return (new NamespaceMenuController())->listMenuItems($request, $response, $args);
        })->add(new ApiTokenAuthMiddleware(null, null, NamespaceMenuController::SCOPE_MENU_READ));

        $group->post('/namespaces/{ns:[a-z0-9\-]+}/menus/{menuId:[0-9]+}/items', function (
            Request $request,
            Response $response,
            array $args
        ): Response {
            return (new NamespaceMenuController())->createMenuItem($request, $response, $args);
        })->add(new ApiTokenAuthMiddleware(null, null, NamespaceMenuController::SCOPE_MENU_WRITE));

        $group->patch(
            '/namespaces/{ns:[a-z0-9\-]+}/menus/{menuId:[0-9]+}/items/{itemId:[0-9]+}',
            function (
                Request $request,
                Response $response,
                array $args
            ): Response {
                return (new NamespaceMenuController())->updateMenuItem($request, $response, $args);
            }
        )->add(new ApiTokenAuthMiddleware(null, null, NamespaceMenuController::SCOPE_MENU_WRITE));

        $group->delete(
            '/namespaces/{ns:[a-z0-9\-]+}/menus/{menuId:[0-9]+}/items/{itemId:[0-9]+}',
            function (
                Request $request,
                Response $response,
                array $args
            ): Response {
                return (new NamespaceMenuController())->deleteMenuItem($request, $response, $args);
            }
        )->add(new ApiTokenAuthMiddleware(null, null, NamespaceMenuController::SCOPE_MENU_WRITE));

        // News
        $group->get('/namespaces/{ns:[a-z0-9\-]+}/news', function (
            Request $request,
            Response $response,
            array $args
        ): Response {
            return (new NamespaceNewsController())->list($request, $response, $args);
        })->add(new ApiTokenAuthMiddleware(null, null, NamespaceNewsController::SCOPE_NEWS_READ));

        $group->get('/namespaces/{ns:[a-z0-9\-]+}/news/{id:[0-9]+}', function (
            Request $request,
            Response $response,
            array $args
        ): Response {
            return (new NamespaceNewsController())->get($request, $response, $args);
        })->add(new ApiTokenAuthMiddleware(null, null, NamespaceNewsController::SCOPE_NEWS_READ));

        $group->post('/namespaces/{ns:[a-z0-9\-]+}/news', function (
            Request $request,
            Response $response,
            array $args
        ): Response {
            return (new NamespaceNewsController())->create($request, $response, $args);
        })->add(new ApiTokenAuthMiddleware(null, null, NamespaceNewsController::SCOPE_NEWS_WRITE));

        $group->patch('/namespaces/{ns:[a-z0-9\-]+}/news/{id:[0-9]+}', function (
            Request $request,
            Response $response,
            array $args
        ): Response {
            return (new NamespaceNewsController())->update($request, $response, $args);
        })->add(new ApiTokenAuthMiddleware(null, null, NamespaceNewsController::SCOPE_NEWS_WRITE));

        $group->delete('/namespaces/{ns:[a-z0-9\-]+}/news/{id:[0-9]+}', function (
            Request $request,
            Response $response,
            array $args
        ): Response {
            return (new NamespaceNewsController())->delete($request, $response, $args);
        })->add(new ApiTokenAuthMiddleware(null, null, NamespaceNewsController::SCOPE_NEWS_WRITE));

        // Quiz
        $group->get('/namespaces/{ns:[a-z0-9\-]+}/events', function (
            Request $request,
            Response $response,
            array $args
        ): Response {
            return (new NamespaceQuizController())->listEvents($request, $response, $args);
        })->add(new ApiTokenAuthMiddleware(null, null, NamespaceQuizController::SCOPE_QUIZ_READ));

        $group->get('/namespaces/{ns:[a-z0-9\-]+}/events/{uid:[a-f0-9]+}', function (
            Request $request,
            Response $response,
            array $args
        ): Response {
            return (new NamespaceQuizController())->getEvent($request, $response, $args);
        })->add(new ApiTokenAuthMiddleware(null, null, NamespaceQuizController::SCOPE_QUIZ_READ));

        $group->post('/namespaces/{ns:[a-z0-9\-]+}/events', function (
            Request $request,
            Response $response,
            array $args
        ): Response {
            return (new NamespaceQuizController())->createEvent($request, $response, $args);
        })->add(new ApiTokenAuthMiddleware(null, null, NamespaceQuizController::SCOPE_QUIZ_WRITE));

        $group->patch('/namespaces/{ns:[a-z0-9\-]+}/events/{uid:[a-f0-9]+}', function (
            Request $request,
            Response $response,
            array $args
        ): Response {
            return (new NamespaceQuizController())->updateEvent($request, $response, $args);
        })->add(new ApiTokenAuthMiddleware(null, null, NamespaceQuizController::SCOPE_QUIZ_WRITE));

        $group->get(
            '/namespaces/{ns:[a-z0-9\-]+}/events/{uid:[a-f0-9]+}/catalogs',
            function (
                Request $request,
                Response $response,
                array $args
            ): Response {
                return (new NamespaceQuizController())->listCatalogs($request, $response, $args);
            }
        )->add(new ApiTokenAuthMiddleware(null, null, NamespaceQuizController::SCOPE_QUIZ_READ));

        $group->get(
            '/namespaces/{ns:[a-z0-9\-]+}/events/{uid:[a-f0-9]+}/catalogs/{slug:[a-z0-9\-]+}',
            function (
                Request $request,
                Response $response,
                array $args
            ): Response {
                return (new NamespaceQuizController())->getCatalog($request, $response, $args);
            }
        )->add(new ApiTokenAuthMiddleware(null, null, NamespaceQuizController::SCOPE_QUIZ_READ));

        $group->put(
            '/namespaces/{ns:[a-z0-9\-]+}/events/{uid:[a-f0-9]+}/catalogs/{slug:[a-z0-9\-]+}',
            function (
                Request $request,
                Response $response,
                array $args
            ): Response {
                return (new NamespaceQuizController())->upsertCatalog($request, $response, $args);
            }
        )->add(new ApiTokenAuthMiddleware(null, null, NamespaceQuizController::SCOPE_QUIZ_WRITE));

        $group->get(
            '/namespaces/{ns:[a-z0-9\-]+}/events/{uid:[a-f0-9]+}/results',
            function (
                Request $request,
                Response $response,
                array $args
            ): Response {
                return (new NamespaceQuizController())->listResults($request, $response, $args);
            }
        )->add(new ApiTokenAuthMiddleware(null, null, NamespaceQuizController::SCOPE_QUIZ_READ));

        $group->post(
            '/namespaces/{ns:[a-z0-9\-]+}/events/{uid:[a-f0-9]+}/results',
            function (
                Request $request,
                Response $response,
                array $args
            ): Response {
                return (new NamespaceQuizController())->submitResult($request, $response, $args);
            }
        )->add(new ApiTokenAuthMiddleware(null, null, NamespaceQuizController::SCOPE_QUIZ_WRITE));

        $group->delete(
            '/namespaces/{ns:[a-z0-9\-]+}/events/{uid:[a-f0-9]+}/results',
            function (
                Request $request,
                Response $response,
                array $args
            ): Response {
                return (new NamespaceQuizController())->clearResults($request, $response, $args);
            }
        )->add(new ApiTokenAuthMiddleware(null, null, NamespaceQuizController::SCOPE_QUIZ_WRITE));

        $group->get(
            '/namespaces/{ns:[a-z0-9\-]+}/events/{uid:[a-f0-9]+}/teams',
            function (
                Request $request,
                Response $response,
                array $args
            ): Response {
                return (new NamespaceQuizController())->listTeams($request, $response, $args);
            }
        )->add(new ApiTokenAuthMiddleware(null, null, NamespaceQuizController::SCOPE_QUIZ_READ));

        $group->put(
            '/namespaces/{ns:[a-z0-9\-]+}/events/{uid:[a-f0-9]+}/teams',
            function (
                Request $request,
                Response $response,
                array $args
            ): Response {
                return (new NamespaceQuizController())->replaceTeams($request, $response, $args);
            }
        )->add(new ApiTokenAuthMiddleware(null, null, NamespaceQuizController::SCOPE_QUIZ_WRITE));

        // Design
        $group->get('/namespaces/{ns:[a-z0-9\-]+}/design', function (
            Request $request,
            Response $response,
            array $args
        ): Response {
            return (new NamespaceDesignController())->manifest($request, $response, $args);
        })->add(new ApiTokenAuthMiddleware(null, null, NamespaceDesignController::SCOPE_DESIGN_READ));

        $group->get('/namespaces/{ns:[a-z0-9\-]+}/design/tokens', function (
            Request $request,
            Response $response,
            array $args
        ): Response {
            return (new NamespaceDesignController())->getTokens($request, $response, $args);
        })->add(new ApiTokenAuthMiddleware(null, null, NamespaceDesignController::SCOPE_DESIGN_READ));

        $group->patch('/namespaces/{ns:[a-z0-9\-]+}/design/tokens', function (
            Request $request,
            Response $response,
            array $args
        ): Response {
            return (new NamespaceDesignController())->updateTokens($request, $response, $args);
        })->add(new ApiTokenAuthMiddleware(null, null, NamespaceDesignController::SCOPE_DESIGN_WRITE));

        $group->post(
            '/namespaces/{ns:[a-z0-9\-]+}/design/validate/{slug:[a-z0-9\-]+}',
            function (
                Request $request,
                Response $response,
                array $args
            ): Response {
                return (new NamespaceDesignController())->validate($request, $response, $args);
            }
        )->add(new ApiTokenAuthMiddleware(null, null, NamespaceDesignController::SCOPE_DESIGN_READ));

        // Tickets
        $group->get('/namespaces/{ns:[a-z0-9\-]+}/tickets', function (
            Request $request,
            Response $response,
            array $args
        ): Response {
            return (new NamespaceTicketController())->list($request, $response, $args);
        })->add(new ApiTokenAuthMiddleware(null, null, NamespaceTicketController::SCOPE_TICKET_READ));

        $group->post('/namespaces/{ns:[a-z0-9\-]+}/tickets', function (
            Request $request,
            Response $response,
            array $args
        ): Response {
            return (new NamespaceTicketController())->create($request, $response, $args);
        })->add(new ApiTokenAuthMiddleware(null, null, NamespaceTicketController::SCOPE_TICKET_WRITE));

        $group->get('/namespaces/{ns:[a-z0-9\-]+}/tickets/{id:[0-9]+}', function (
            Request $request,
            Response $response,
            array $args
        ): Response {
            return (new NamespaceTicketController())->get($request, $response, $args);
        })->add(new ApiTokenAuthMiddleware(null, null, NamespaceTicketController::SCOPE_TICKET_READ));

        $group->patch('/namespaces/{ns:[a-z0-9\-]+}/tickets/{id:[0-9]+}', function (
            Request $request,
            Response $response,
            array $args
        ): Response {
            return (new NamespaceTicketController())->update($request, $response, $args);
        })->add(new ApiTokenAuthMiddleware(null, null, NamespaceTicketController::SCOPE_TICKET_WRITE));

        $group->delete('/namespaces/{ns:[a-z0-9\-]+}/tickets/{id:[0-9]+}', function (
            Request $request,
            Response $response,
            array $args
        ): Response {
            return (new NamespaceTicketController())->delete($request, $response, $args);
        })->add(new ApiTokenAuthMiddleware(null, null, NamespaceTicketController::SCOPE_TICKET_WRITE));

        $group->patch(
            '/namespaces/{ns:[a-z0-9\-]+}/tickets/{id:[0-9]+}/status',
            function (
                Request $request,
                Response $response,
                array $args
            ): Response {
                return (new NamespaceTicketController())->transition($request, $response, $args);
            }
        )->add(new ApiTokenAuthMiddleware(null, null, NamespaceTicketController::SCOPE_TICKET_WRITE));

        $group->get(
            '/namespaces/{ns:[a-z0-9\-]+}/tickets/{id:[0-9]+}/comments',
            function (
                Request $request,
                Response $response,
                array $args
            ): Response {
                return (new NamespaceTicketController())->listComments($request, $response, $args);
            }
        )->add(new ApiTokenAuthMiddleware(null, null, NamespaceTicketController::SCOPE_TICKET_READ));

        $group->post(
            '/namespaces/{ns:[a-z0-9\-]+}/tickets/{id:[0-9]+}/comments',
            function (
                Request $request,
                Response $response,
                array $args
            ): Response {
                return (new NamespaceTicketController())->addComment($request, $response, $args);
            }
        )->add(new ApiTokenAuthMiddleware(null, null, NamespaceTicketController::SCOPE_TICKET_WRITE));

        $group->delete(
            '/namespaces/{ns:[a-z0-9\-]+}/tickets/{id:[0-9]+}/comments/{commentId:[0-9]+}',
            function (
                Request $request,
                Response $response,
                array $args
            ): Response {
                return (new NamespaceTicketController())->deleteComment($request, $response, $args);
            }
        )->add(new ApiTokenAuthMiddleware(null, null, NamespaceTicketController::SCOPE_TICKET_WRITE));

        // Customer profile
        $group->get('/namespaces/{ns:[a-z0-9\-]+}/customer/profile', function (
            Request $request,
            Response $response,
            array $args
        ): Response {
            return (new CustomerController())->getProfile($request, $response, $args);
        })->add(new ApiTokenAuthMiddleware(null, null, CustomerController::SCOPE_CUSTOMER_READ));

        $group->patch('/namespaces/{ns:[a-z0-9\-]+}/customer/profile', function (
            Request $request,
            Response $response,
            array $args
        ): Response {
            return (new CustomerController())->updateProfile($request, $response, $args);
        })->add(new ApiTokenAuthMiddleware(null, null, CustomerController::SCOPE_CUSTOMER_WRITE));
    });
};
