<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Service\StripeService;
use App\Service\TenantService;
use App\Infrastructure\Database;
use Stripe\StripeClient;

/**
 * Redirects the user to the Stripe customer portal.
 */
class SubscriptionController
{
    public function __invoke(Request $request, Response $response): Response {
        $host = $request->getUri()->getHost();
        $parts = explode('.', $host);
        $mainDomain = getenv('MAIN_DOMAIN') ?: $host;
        if ($host === $mainDomain || count($parts) < 2) {
            if ($host === $mainDomain) {
                $selectUrl = $request->getUri()->getScheme() . '://' . $host . '/admin/tenants';
                return $response->withHeader('Location', $selectUrl)->withStatus(302);
            }
            $response->getBody()->write('Missing tenant context');
            return $response->withStatus(400);
        }
        $sub = $parts[0];

        $base = Database::connectFromEnv();
        $tenantService = new TenantService($base);
        $tenant = $tenantService->getBySubdomain($sub);
        $customerId = (string) ($tenant['stripe_customer_id'] ?? '');
        $uri = $request->getUri();
        if ($customerId === '') {
            $upgradeUrl = $uri->getScheme() . '://' . $uri->getHost() . '/onboarding';
            return $response->withHeader('Location', $upgradeUrl)->withStatus(302);
        }

        $returnUrl = $uri->getScheme() . '://' . $uri->getHost() . '/admin';
        $service = new StripeService();
        $url = $service->createBillingPortal($customerId, $returnUrl);
        return $response->withHeader('Location', $url)->withStatus(302);
    }

    public function cancelOnboardingCheckout(
        Request $request,
        Response $response,
        array $args
    ): Response {
        $sessionId = (string) ($args['id'] ?? '');
        if ($sessionId !== '') {
            $service = $request->getAttribute('stripeService');
            if (!$service instanceof StripeService) {
                $service = new StripeService();
            }
            try {
                $info = $service->getCheckoutSessionInfo($sessionId);
                $customerId = $info['customer_id'];
                if ($customerId !== null) {
                    $service->cancelSubscriptionForCustomer($customerId);
                } else {
                    $useSandbox = filter_var(getenv('STRIPE_SANDBOX'), FILTER_VALIDATE_BOOLEAN);
                    $envKey = $useSandbox ? 'STRIPE_SANDBOX_SECRET_KEY' : 'STRIPE_SECRET_KEY';
                    $altKey = $useSandbox ? 'STRIPE_SANDBOX_SECRET' : 'STRIPE_SECRET';
                    $apiKey = getenv($envKey) ?: getenv($altKey) ?: '';
                    $client = new StripeClient($apiKey);
                    $client->checkout->sessions->expire($sessionId, []);
                }
            } catch (\Throwable $e) {
                // ignore errors
            }
        }
        return $response->withStatus(204);
    }
}
