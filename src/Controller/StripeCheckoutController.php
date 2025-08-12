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
        $data = json_decode((string) $request->getBody(), true);
        if (!is_array($data)) {
            return $response->withStatus(400);
        }

        $plan = (string) ($data['plan'] ?? '');
        $email = (string) ($data['email'] ?? '');

        $allowedPlans = ['starter', 'standard', 'professional'];
        if (!in_array($plan, $allowedPlans, true)) {
            return $this->jsonError($response, 422, 'invalid plan');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->jsonError($response, 422, 'invalid email');
        }

        if (!StripeService::isConfigured()) {
            return $this->jsonError($response, 503, 'service unavailable');
        }

        $useSandbox = filter_var(getenv('STRIPE_SANDBOX'), FILTER_VALIDATE_BOOLEAN);
        $prefix = $useSandbox ? 'STRIPE_SANDBOX_' : 'STRIPE_';
        $priceMap = [
            'starter' => getenv($prefix . 'PRICE_STARTER') ?: '',
            'standard' => getenv($prefix . 'PRICE_STANDARD') ?: '',
            'professional' => getenv($prefix . 'PRICE_PROFESSIONAL') ?: '',
        ];
        $priceId = $priceMap[$plan];
        if ($priceId === '') {
            return $this->jsonError($response, 422, 'invalid plan');
        }

        $uri = $request->getUri();
        $base = $uri->getScheme() . '://' . $uri->getHost();
        $successUrl = $base . '/onboarding?paid=1&session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl = $base . '/onboarding?canceled=1&session_id={CHECKOUT_SESSION_ID}';

        $service = new StripeService();
        try {
            $url = $service->createCheckoutSession($priceId, $successUrl, $cancelUrl, $email);
        } catch (\Throwable $e) {
            error_log($e->getMessage());
            error_log($e->getTraceAsString());
            return $this->jsonError($response, 500, 'internal error');
        }

        $payload = json_encode(['url' => $url]);
        $response->getBody()->write($payload !== false ? $payload : '{}');
        return $response->withHeader('Content-Type', 'application/json');
    }

    private function jsonError(Response $response, int $status, string $message): Response
    {
        $payload = json_encode(['error' => $message]);
        $response->getBody()->write($payload !== false ? $payload : '{}');
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}
