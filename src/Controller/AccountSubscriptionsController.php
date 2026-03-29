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
 * Show and manage the authenticated account's profile and subscriptions.
 *
 * Protected by AccountAuthMiddleware.
 */
class AccountSubscriptionsController
{
    public function show(Request $request, Response $response): Response
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
            'profile_saved' => (bool) ($request->getQueryParams()['saved'] ?? false),
        ]);
    }

    public function updateProfile(Request $request, Response $response): Response
    {
        $accountId = (int) ($_SESSION['account_id'] ?? 0);
        $basePath = RouteContext::fromRequest($request)->getBasePath();

        $body = $request->getParsedBody();
        $name = trim((string) ($body['name'] ?? ''));

        $pdo = Database::connectFromEnv();
        $accountService = new AccountService($pdo);
        $account = $accountService->findById($accountId);

        if ($account === null) {
            unset($_SESSION['account_id'], $_SESSION['account_email']);

            return $response->withHeader('Location', $basePath . '/auth/register')->withStatus(302);
        }

        $accountService->updateName($accountId, $name);

        return $response
            ->withHeader('Location', $basePath . '/account/subscriptions?saved=1')
            ->withStatus(302);
    }
}
