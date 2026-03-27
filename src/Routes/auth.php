<?php

declare(strict_types=1);

use App\Application\Middleware\AccountAuthMiddleware;
use App\Application\Middleware\RateLimitMiddleware;
use App\Controller\AuthLoginController;
use App\Controller\AuthLogoutController;
use App\Controller\AuthRegisterController;
use App\Controller\OAuthCallbackController;
use App\Controller\OAuthProviderController;
use App\Controller\StripeAccountCheckoutController;
use Slim\App;

return function (App $app): void {
    // Account registration & login pages
    $app->get('/auth/register', [AuthRegisterController::class, 'show']);
    $app->get('/auth/login', [AuthLoginController::class, 'show']);

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
};
