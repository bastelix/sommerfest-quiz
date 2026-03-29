<?php

declare(strict_types=1);

use App\Application\Middleware\AccountAuthMiddleware;
use App\Application\Middleware\RateLimitMiddleware;
use App\Controller\AccountEmailController;
use App\Controller\AccountSubscriptionsController;
use App\Controller\AuthLoginController;
use App\Controller\AuthLogoutController;
use App\Controller\AuthRegisterController;
use App\Controller\OAuthCallbackController;
use App\Controller\OAuthProviderController;
use App\Controller\StripeAccountCheckoutController;
use App\Infrastructure\Database;
use App\Service\EmailConfirmationService;
use Slim\App;

return function (App $app): void {
    // Account registration & login pages
    $app->get('/auth/register', [AuthRegisterController::class, 'show']);
    $app->get('/auth/login', [AuthLoginController::class, 'show']);

    // Email double opt-in for account registration
    $app->post('/auth/email', function ($request, $response) {
        $pdo = Database::connectFromEnv();
        $controller = new AccountEmailController(new EmailConfirmationService($pdo));

        return $controller->request($request, $response);
    })->add(new RateLimitMiddleware(5, 300));

    $app->get('/auth/email/confirm', function ($request, $response) {
        $pdo = Database::connectFromEnv();
        $controller = new AccountEmailController(new EmailConfirmationService($pdo));

        return $controller->confirm($request, $response);
    });

    $app->get('/auth/email/status', function ($request, $response) {
        $pdo = Database::connectFromEnv();
        $controller = new AccountEmailController(new EmailConfirmationService($pdo));

        return $controller->status($request, $response);
    })->add(new RateLimitMiddleware(30, 60));

    // OAuth provider redirect + callback
    $app->get('/auth/provider/{name}', OAuthProviderController::class)
        ->add(new RateLimitMiddleware(10, 60));
    $app->get('/auth/callback/{name}', OAuthCallbackController::class)
        ->add(new RateLimitMiddleware(10, 60));

    // Account logout (only clears account session, not tenant login)
    $app->get('/auth/logout', AuthLogoutController::class);

    // Auth-gated Stripe Checkout for central accounts
    $app->get('/stripe/checkout', StripeAccountCheckoutController::class)
        ->add(new AccountAuthMiddleware());

    // Account subscription management page
    $app->get('/account/subscriptions', AccountSubscriptionsController::class)
        ->add(new AccountAuthMiddleware());
};
