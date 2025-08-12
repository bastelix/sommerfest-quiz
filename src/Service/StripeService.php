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
     * Create a checkout session for a subscription plan.
     *
     * @return string Checkout URL or client secret when using embedded mode
     */
    public function createCheckoutSession(
        string $priceId,
        string $successUrl,
        string $cancelUrl,
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
            $params['cancel_url'] = $cancelUrl;
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
     * Get the publishable key for the current environment.
     */
    public function getPublishableKey(): string
    {
        $useSandbox = filter_var(getenv('STRIPE_SANDBOX'), FILTER_VALIDATE_BOOLEAN);
        $envKey = $useSandbox ? 'STRIPE_SANDBOX_PUBLISHABLE_KEY' : 'STRIPE_PUBLISHABLE_KEY';
        return getenv($envKey) ?: '';
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
     * Create a new customer and return its id.
     */
    public function createCustomer(string $email, ?string $name = null): string
    {
        $params = ['email' => $email];
        if ($name !== null) {
            $params['name'] = $name;
        }
        $customer = $this->client->customers->create($params);
        return (string) $customer->id;
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
     * Retrieve details for the first active subscription of a customer.
     *
     * @return array{plan:?string, amount:int, currency:string, status:string, next_payment:?string}|null
     */
    public function getActiveSubscription(string $customerId): ?array
    {
        $subs = $this->client->subscriptions->all([
            'customer' => $customerId,
            'status' => 'active',
            'limit' => 1,
            'expand' => ['data.latest_invoice'],
        ]);
        $sub = $subs->data[0] ?? null;
        if ($sub === null) {
            return null;
        }
        $item = $sub->items->data[0] ?? null;
        $price = $item?->price;
        $priceId = $price->id ?? '';

        $useSandbox = filter_var(getenv('STRIPE_SANDBOX'), FILTER_VALIDATE_BOOLEAN);
        $prefix = $useSandbox ? 'STRIPE_SANDBOX_' : 'STRIPE_';
        $map = [];
        $starter = getenv($prefix . 'PRICE_STARTER') ?: '';
        if ($starter !== '') {
            $map[$starter] = 'starter';
        }
        $standard = getenv($prefix . 'PRICE_STANDARD') ?: '';
        if ($standard !== '') {
            $map[$standard] = 'standard';
        }
        $pro = getenv($prefix . 'PRICE_PROFESSIONAL') ?: '';
        if ($pro !== '') {
            $map[$pro] = 'professional';
        }
        $plan = $map[$priceId] ?? null;

        return [
            'plan' => $plan,
            'amount' => (int) ($price->unit_amount ?? 0),
            'currency' => (string) ($price->currency ?? ''),
            'status' => (string) ($sub->latest_invoice->status ?? ''),
            'next_payment' => isset($sub->current_period_end)
                ? date('c', (int) $sub->current_period_end)
                : null,
        ];
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
            'PUBLISHABLE_KEY',
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
