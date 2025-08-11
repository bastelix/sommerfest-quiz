<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Service\StripeService;
use App\Service\TenantService;
use App\Infrastructure\Database;

/**
 * Redirects the user to the Stripe customer portal.
 */
class SubscriptionController
{
    public function __invoke(Request $request, Response $response): Response
    {
        $host = $request->getUri()->getHost();
        $parts = explode('.', $host);
        $mainDomain = getenv('MAIN_DOMAIN') ?: $host;
        if ($host === $mainDomain || count($parts) < 2) {
            $response->getBody()->write('Missing tenant context');
            return $response->withStatus(500);
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
}
