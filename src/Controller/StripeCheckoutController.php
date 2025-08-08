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
        $email = isset($data['email']) ? (string) $data['email'] : null;

        $priceMap = [
            'starter' => getenv('STRIPE_PRICE_STARTER') ?: '',
            'standard' => getenv('STRIPE_PRICE_STANDARD') ?: '',
            'professional' => getenv('STRIPE_PRICE_PROFESSIONAL') ?: '',
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
