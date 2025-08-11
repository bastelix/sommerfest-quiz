<?php

declare(strict_types=1);

namespace App\Service;

use Stripe\StripeClient;

/**
 * Wrapper around the Stripe PHP SDK.
 */
class StripeService
{
    private StripeClient $client;

    public function __construct(?string $apiKey = null)
    {
        $useSandbox = filter_var(getenv('STRIPE_SANDBOX'), FILTER_VALIDATE_BOOLEAN);
        $envKey = $useSandbox ? 'STRIPE_SANDBOX_SECRET_KEY' : 'STRIPE_SECRET_KEY';
        $apiKey = $apiKey ?? (getenv($envKey) ?: '');
        $this->client = new StripeClient($apiKey);
    }

    /**
     * Create a checkout session for a subscription plan and return its URL.
     */
    public function createCheckoutSession(
        string $priceId,
        string $successUrl,
        string $cancelUrl,
        ?string $customerEmail = null
    ): string {
        $params = [
            'mode' => 'subscription',
            'line_items' => [
                ['price' => $priceId, 'quantity' => 1],
            ],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
        ];
        if ($customerEmail !== null) {
            $params['customer_email'] = $customerEmail;
        }
        $session = $this->client->checkout->sessions->create($params);
        return (string) $session->url;
    }

    /**
     * Look up a Stripe customer id by email address.
     */
    public function findCustomerIdByEmail(string $email): ?string
    {
        $customers = $this->client->customers->all([
            'email' => $email,
            'limit' => 1,
        ]);
        $first = $customers->data[0]->id ?? null;
        return $first !== null ? (string) $first : null;
    }

    /**
     * Create a billing portal session and return its URL.
     */
    public function createBillingPortal(string $customerId, string $returnUrl): string
    {
        $session = $this->client->billingPortal->sessions->create([
            'customer' => $customerId,
            'return_url' => $returnUrl,
        ]);
        return (string) $session->url;
    }

    /**
     * Check whether a checkout session has been paid.
     */
    public function isCheckoutSessionPaid(string $sessionId): bool
    {
        return $this->getCheckoutSessionInfo($sessionId)['paid'];
    }

    /**
     * Retrieve checkout session payment status and customer id.
     *
     * @return array{paid:bool, customer_id:?string}
     */
    public function getCheckoutSessionInfo(string $sessionId): array
    {
        try {
            $session = $this->client->checkout->sessions->retrieve($sessionId, []);
            return [
                'paid' => ($session->payment_status ?? '') === 'paid',
                'customer_id' => isset($session->customer) ? (string) $session->customer : null,
            ];
        } catch (\Throwable $e) {
            return ['paid' => false, 'customer_id' => null];
        }
    }

    /**
     * Check whether Stripe is configured with a secret key and price IDs.
     */
    public static function isConfigured(): bool
    {
        $useSandbox = filter_var(getenv('STRIPE_SANDBOX'), FILTER_VALIDATE_BOOLEAN);
        $prefix = $useSandbox ? 'STRIPE_SANDBOX_' : 'STRIPE_';
        $required = [
            'SECRET_KEY',
            'PRICE_STARTER',
            'PRICE_STANDARD',
            'PRICE_PROFESSIONAL',
        ];
        foreach ($required as $suffix) {
            $value = getenv($prefix . $suffix) ?: '';
            if ($value === '') {
                return false;
            }
        }
        return true;
    }
}
