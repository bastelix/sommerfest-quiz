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
        try {
            $session = $this->client->checkout->sessions->retrieve($sessionId, []);
            return ($session->payment_status ?? '') === 'paid';
        } catch (\Throwable $e) {
            return false;
        }
    }
}
