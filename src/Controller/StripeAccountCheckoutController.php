<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Plan;
use App\Infrastructure\Database;
use App\Service\AccountService;
use App\Service\StripeService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;

/**
 * Start a Stripe Checkout session for an authenticated central account.
 *
 * Protected by AccountAuthMiddleware which guarantees $_SESSION['account_id'].
 */
class StripeAccountCheckoutController
{
    public function __invoke(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $planKey = (string) ($params['plan'] ?? '');
        $app = (string) ($params['app'] ?? '');
        $basePath = RouteContext::fromRequest($request)->getBasePath();

        $plan = Plan::tryFrom($planKey);
        if ($plan === null || $app === '') {
            $response->getBody()->write('Missing or invalid plan/app parameter.');

            return $response->withStatus(400);
        }

        if (!StripeService::isConfigured()['ok']) {
            $response->getBody()->write('Payment service unavailable.');

            return $response->withStatus(503);
        }

        $priceId = StripeService::priceIdForPlan($plan->value);
        if ($priceId === '') {
            $response->getBody()->write('Unknown plan.');

            return $response->withStatus(422);
        }

        $accountId = (int) ($_SESSION['account_id'] ?? 0);
        $pdo = Database::connectFromEnv();
        $accountService = new AccountService($pdo);
        $account = $accountService->findById($accountId);

        if ($account === null) {
            unset($_SESSION['account_id'], $_SESSION['account_email']);

            return $response->withHeader('Location', $basePath . '/auth/register')->withStatus(302);
        }

        $uri = $request->getUri();
        $base = $uri->getScheme() . '://' . $uri->getHost();
        $successUrl = $base . $basePath . '/stripe/checkout/success?session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl = $base . $basePath . '/stripe/checkout/cancel';

        $service = $request->getAttribute('stripeService');
        if (!$service instanceof StripeService) {
            $service = new StripeService();
        }

        $customerId = !empty($account['stripe_customer_id']) ? $account['stripe_customer_id'] : null;

        try {
            $url = $service->createCheckoutSession(
                $priceId,
                $successUrl,
                $cancelUrl,
                $plan->value,
                $customerId !== null ? null : $account['email'],
                $customerId,
                null,
                null,
                false,
                [
                    'account_id' => (string) $account['id'],
                    'app' => $app,
                ]
            );
        } catch (\Throwable $e) {
            error_log('StripeAccountCheckout error: ' . $e->getMessage());
            $response->getBody()->write('Checkout could not be started.');

            return $response->withStatus(500);
        }

        return $response->withHeader('Location', $url)->withStatus(302);
    }
}
