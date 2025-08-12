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

    public function __construct(?string $apiKey = null, ?StripeClient $client = null)
    {
        if ($client !== null) {
            $this->client = $client;
            return;
        }
        $useSandbox = filter_var(getenv('STRIPE_SANDBOX'), FILTER_VALIDATE_BOOLEAN);
        $envKey = $useSandbox ? 'STRIPE_SANDBOX_SECRET_KEY' : 'STRIPE_SECRET_KEY';
        $apiKey = $apiKey ?? (getenv($envKey) ?: '');
        $this->client = new StripeClient($apiKey);
    }

    /**
     * Create a checkout session for a subscription plan and return its URL or
     * client secret for embedded mode.
     */
    public function createCheckoutSession(
        string $priceId,
        string $successUrl,
        ?string $cancelUrl = null,
        ?string $customerEmail = null,
        ?string $customerId = null,
        ?string $clientReferenceId = null,
        bool $embedded = false
    ): string {
        $params = [
            'mode' => 'subscription',
            'line_items' => [
                ['price' => $priceId, 'quantity' => 1],
            ],
            'payment_method_types' => ['card'],
            'subscription_data' => ['trial_period_days' => 7],
        ];
        if ($embedded) {
            $params['ui_mode'] = 'embedded';
            $params['return_url'] = $successUrl;
        } else {
            $params['success_url'] = $successUrl;
            if ($cancelUrl !== null) {
                $params['cancel_url'] = $cancelUrl;
            }
        }
        if ($customerEmail !== null) {
            $params['customer_email'] = $customerEmail;
        }
        if ($customerId !== null) {
            $params['customer'] = $customerId;
        }
        if ($clientReferenceId !== null) {
            $params['client_reference_id'] = $clientReferenceId;
        }
        $session = $this->client->checkout->sessions->create($params);
        return $embedded ? (string) ($session->client_secret ?? '') : (string) $session->url;
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

    /**
     * Return the publishable key for Stripe.js.
     */
    public static function getPublishableKey(): string
    {
        $useSandbox = filter_var(getenv('STRIPE_SANDBOX'), FILTER_VALIDATE_BOOLEAN);
        $envKey = $useSandbox ? 'STRIPE_SANDBOX_PUBLISHABLE_KEY' : 'STRIPE_PUBLISHABLE_KEY';
        return getenv($envKey) ?: '';
    }
}
