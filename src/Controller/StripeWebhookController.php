<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Service\TenantService;
use App\Infrastructure\Database;

/**
 * Handle Stripe webhook events.
 */
class StripeWebhookController
{
    public function __invoke(Request $request, Response $response): Response
    {
        $payload = json_decode((string) $request->getBody(), true);
        if (!is_array($payload) || !isset($payload['type'])) {
            return $response->withStatus(400);
        }
        $type = (string) $payload['type'];
        $object = $payload['data']['object'] ?? [];

        $base = Database::connectFromEnv();
        $tenantService = new TenantService($base);

        switch ($type) {
            case 'checkout.session.completed':
                $sub = (string) ($object['client_reference_id'] ?? '');
                $customerId = (string) ($object['customer'] ?? '');
                if ($sub !== '' && $customerId !== '') {
                    $tenantService->updateProfile($sub, ['stripe_customer_id' => $customerId]);
                }
                break;
            case 'customer.subscription.updated':
                $customerId = (string) ($object['customer'] ?? '');
                if ($customerId !== '') {
                    $plan = $this->mapPriceToPlan(
                        (string) ($object['items']['data'][0]['price']['id'] ?? '')
                    );
                    $billing = ((string) ($object['collection_method'] ?? '') === 'charge_automatically')
                        ? 'credit'
                        : null;
                    $tenantService->updateByStripeCustomerId($customerId, [
                        'plan' => $plan,
                        'billing_info' => $billing,
                    ]);
                }
                break;
            case 'customer.subscription.deleted':
                $customerId = (string) ($object['customer'] ?? '');
                if ($customerId !== '') {
                    $tenantService->updateByStripeCustomerId($customerId, ['plan' => null]);
                }
                break;
        }

        return $response->withStatus(200);
    }

    private function mapPriceToPlan(string $priceId): ?string
    {
        $useSandbox = filter_var(getenv('STRIPE_SANDBOX'), FILTER_VALIDATE_BOOLEAN);
        $prefix = $useSandbox ? 'STRIPE_SANDBOX_' : 'STRIPE_';
        $map = [
            getenv($prefix . 'PRICE_STARTER') ?: '' => 'starter',
            getenv($prefix . 'PRICE_STANDARD') ?: '' => 'standard',
            getenv($prefix . 'PRICE_PROFESSIONAL') ?: '' => 'professional',
        ];
        return $map[$priceId] ?? null;
    }
}
