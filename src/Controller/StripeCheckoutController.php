<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Service\StripeService;

/**
 * Start a Stripe Checkout session during onboarding.
 */
class StripeCheckoutController
{
    public function __invoke(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $plan = (string) ($data['plan'] ?? '');
        $email = filter_var($data['email'] ?? null, FILTER_VALIDATE_EMAIL) ? (string) $data['email'] : null;

        $useSandbox = filter_var(getenv('STRIPE_SANDBOX'), FILTER_VALIDATE_BOOLEAN);
        $prefix = $useSandbox ? 'STRIPE_SANDBOX_' : 'STRIPE_';
        $priceMap = [
            'starter' => getenv($prefix . 'PRICE_STARTER') ?: '',
            'standard' => getenv($prefix . 'PRICE_STANDARD') ?: '',
            'professional' => getenv($prefix . 'PRICE_PROFESSIONAL') ?: '',
        ];
        $priceId = $priceMap[$plan] ?? '';
        $uri = $request->getUri();
        $base = $uri->getScheme() . '://' . $uri->getHost();
        $successUrl = $base . '/onboarding?paid=1';
        $cancelUrl = $base . '/onboarding?canceled=1';

        $service = new StripeService();
        $url = $service->createCheckoutSession($priceId, $successUrl, $cancelUrl, $email);

        $payload = json_encode(['url' => $url]);
        $response->getBody()->write($payload !== false ? $payload : '{}');
        return $response->withHeader('Content-Type', 'application/json');
    }
}
