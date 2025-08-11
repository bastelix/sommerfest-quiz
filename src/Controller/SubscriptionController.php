<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Service\StripeService;

/**
 * Redirects the user to the Stripe customer portal.
 */
class SubscriptionController
{
    public function __invoke(Request $request, Response $response): Response
    {
        $path = dirname(__DIR__, 2) . '/data/profile.json';
        $profile = [];
        if (is_readable($path)) {
            $profile = json_decode((string) file_get_contents($path), true) ?: [];
        }
        $email = (string) ($profile['imprint_email'] ?? '');
        if ($email === '') {
            $response->getBody()->write('Missing profile email');
            return $response->withStatus(500);
        }

        $uri = $request->getUri();
        $returnUrl = $uri->getScheme() . '://' . $uri->getHost() . '/admin';
        $service = new StripeService();
        $customerId = $service->findCustomerIdByEmail($email);
        if ($customerId === null) {
            $response->getBody()->write('Missing Stripe customer id');
            return $response->withStatus(500);
        }
        $url = $service->createBillingPortal($customerId, $returnUrl);
        return $response->withHeader('Location', $url)->withStatus(302);
    }
}
