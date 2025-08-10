<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Service\StripeService;

/**
 * Verify status of a Stripe Checkout session.
 */
class StripeSessionController
{
    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $sessionId = (string) ($args['id'] ?? '');
        $service = new StripeService();
        $paid = $sessionId !== '' && $service->isCheckoutSessionPaid($sessionId);
        $payload = json_encode(['paid' => $paid]);
        $response->getBody()->write($payload !== false ? $payload : '{}');

        return $response->withHeader('Content-Type', 'application/json');
    }
}

