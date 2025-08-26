<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Service\TenantService;
use App\Infrastructure\Database;
use Stripe\Webhook;

/**
 * Handle Stripe webhook events.
 */
class StripeWebhookController
{
    public function __invoke(Request $request, Response $response): Response
    {
        $payload = (string) $request->getBody();
        $sigHeader = $request->getHeaderLine('Stripe-Signature');
        $webhookSecret = getenv('STRIPE_WEBHOOK_SECRET') ?: '';

        if ($webhookSecret === '') {
            error_log('Missing STRIPE_WEBHOOK_SECRET environment variable');
            return $response->withStatus(500);
        }

        try {
            Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
        } catch (\UnexpectedValueException | \Stripe\Exception\SignatureVerificationException) {
            return $response->withStatus(400);
        }

        $data = json_decode($payload, true);
        if (!is_array($data) || !isset($data['type'])) {
            return $response->withStatus(400);
        }
        $type = (string) $data['type'];
        $object = $data['data']['object'] ?? [];

        $base = Database::connectFromEnv();
        $tenantService = new TenantService($base);

        switch ($type) {
            case 'checkout.session.completed':
                $sub = (string) ($object['client_reference_id'] ?? '');
                $customerId = (string) ($object['customer'] ?? '');
                $subscriptionId = (string) ($object['subscription'] ?? '');
                $plan = (string) ($object['metadata']['plan'] ?? '');
                if ($sub !== '' && $customerId !== '') {
                    $data = ['stripe_customer_id' => $customerId];
                    if ($subscriptionId !== '') {
                        $data['stripe_subscription_id'] = $subscriptionId;
                    }
                    if ($plan !== '') {
                        $data['plan'] = $plan;
                    }
                    $tenantService->updateProfile($sub, $data);
                }
                break;
            case 'customer.subscription.updated':
                $customerId = (string) ($object['customer'] ?? '');
                if ($customerId !== '') {
                    $priceId = (string) ($object['items']['data'][0]['price']['id'] ?? '');
                    $plan = $this->mapPriceToPlan($priceId);
                    $status = (string) ($object['status'] ?? '');
                    $currentEnd = isset($object['current_period_end'])
                        ? date('Y-m-d H:i:sP', (int) $object['current_period_end'])
                        : null;
                    $cancelAtPeriodEnd = array_key_exists('cancel_at_period_end', $object)
                        ? ((bool) $object['cancel_at_period_end'] ? 1 : 0)
                        : null;
                    $tenantService->updateByStripeCustomerId($customerId, [
                        'stripe_subscription_id' => (string) ($object['id'] ?? ''),
                        'plan' => $plan,
                        'stripe_price_id' => $priceId !== '' ? $priceId : null,
                        'stripe_status' => $status !== '' ? $status : null,
                        'stripe_current_period_end' => $currentEnd,
                        'stripe_cancel_at_period_end' => $cancelAtPeriodEnd,
                    ]);
                }
                break;
            case 'customer.subscription.deleted':
                $customerId = (string) ($object['customer'] ?? '');
                if ($customerId !== '') {
                    $tenantService->updateByStripeCustomerId($customerId, [
                        'plan' => null,
                        'stripe_status' => 'canceled',
                    ]);
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
