<?php

declare(strict_types=1);

namespace App\Controller;

use App\Infrastructure\Database;
use App\Service\AccountService;
use App\Service\AppSubscriptionService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;

/**
 * Show the authenticated account's subscription overview.
 *
 * Protected by AccountAuthMiddleware.
 */
class AccountSubscriptionsController
{
    public function __invoke(Request $request, Response $response): Response
    {
        $accountId = (int) ($_SESSION['account_id'] ?? 0);
        $basePath = RouteContext::fromRequest($request)->getBasePath();

        $pdo = Database::connectFromEnv();
        $accountService = new AccountService($pdo);
        $account = $accountService->findById($accountId);

        if ($account === null) {
            unset($_SESSION['account_id'], $_SESSION['account_email']);

            return $response->withHeader('Location', $basePath . '/auth/register')->withStatus(302);
        }

        $subService = new AppSubscriptionService($pdo);
        $subscriptions = $subService->findByAccountId($accountId);

        $view = Twig::fromRequest($request);

        return $view->render($response, 'auth/subscriptions.twig', [
            'basePath' => $basePath,
            'account' => $account,
            'subscriptions' => $subscriptions,
        ]);
    }
}
