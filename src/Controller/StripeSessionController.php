<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Service\StripeService;
use App\Service\TenantService;
use App\Infrastructure\Database;

/**
 * Verify status of a Stripe Checkout session.
 */
class StripeSessionController
{
    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $sessionId = (string) ($args['id'] ?? '');
        $service = $request->getAttribute('stripeService');
        if (!$service instanceof StripeService) {
            $service = new StripeService();
        }
        $info = $sessionId !== ''
            ? $service->getCheckoutSessionInfo($sessionId)
            : ['paid' => false, 'customer_id' => null, 'client_reference_id' => null];

        $sub = $info['client_reference_id'];
        if ($info['paid'] && $info['customer_id'] !== null && $sub !== null) {
            $base = Database::connectFromEnv();
            $tenantService = new TenantService($base);
            $tenantService->updateProfile($sub, ['stripe_customer_id' => $info['customer_id']]);
        }

        $payload = json_encode(['paid' => $info['paid']]);
        $response->getBody()->write($payload !== false ? $payload : '{}');

        return $response->withHeader('Content-Type', 'application/json');
    }
}
