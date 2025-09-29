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

    public function __construct(?string $apiKey = null, ?StripeClient $client = null) {
        if ($client !== null) {
            $this->client = $client;
            return;
        }
        $useSandbox = filter_var(getenv('STRIPE_SANDBOX'), FILTER_VALIDATE_BOOLEAN);
        $envKey = $useSandbox ? 'STRIPE_SANDBOX_SECRET_KEY' : 'STRIPE_SECRET_KEY';
        $altKey = $useSandbox ? 'STRIPE_SANDBOX_SECRET' : 'STRIPE_SECRET';
        $apiKey = $apiKey ?? (getenv($envKey) ?: getenv($altKey) ?: '');
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
        string $plan,
        ?string $customerEmail = null,
        ?string $customerId = null,
        ?string $clientReferenceId = null,
        ?int $trialPeriodDays = null,
        bool $embedded = false
    ): string {
        if ($trialPeriodDays === null) {
            $envTrial = getenv('STRIPE_TRIAL_DAYS');
            if ($envTrial !== false && $envTrial !== '') {
                $trialPeriodDays = (int) $envTrial;
            } else {
                $trialPeriodDays = 7;
            }
        }

        $params = [
            'mode' => 'subscription',
            'line_items' => [
                ['price' => $priceId, 'quantity' => 1],
            ],
            'metadata' => ['plan' => $plan],
        ];
        if ($trialPeriodDays > 0) {
            $params['subscription_data'] = ['trial_period_days' => $trialPeriodDays];
            $params['payment_method_collection'] = 'if_required';
        }
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
    public function getPublishableKey(): string {
        $useSandbox = filter_var(getenv('STRIPE_SANDBOX'), FILTER_VALIDATE_BOOLEAN);
        $envKey = $useSandbox ? 'STRIPE_SANDBOX_PUBLISHABLE_KEY' : 'STRIPE_PUBLISHABLE_KEY';
        $altKey = $useSandbox ? 'STRIPE_SANDBOX_PUBLISHABLE' : 'STRIPE_PUBLISHABLE';
        return getenv($envKey) ?: getenv($altKey) ?: '';
    }

    /**
     * Look up a Stripe customer id by email address.
     */
    public function findCustomerIdByEmail(string $email): ?string {
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
    public function createCustomer(string $email, ?string $name = null): string {
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
    public function createBillingPortal(string $customerId, string $returnUrl): string {
        $session = $this->client->billingPortal->sessions->create([
            'customer' => $customerId,
            'return_url' => $returnUrl,
        ]);
        return (string) $session->url;
    }

    /**
     * Check whether a checkout session has been paid.
     */
    public function isCheckoutSessionPaid(string $sessionId): bool {
        return $this->getCheckoutSessionInfo($sessionId)['paid'];
    }

    /**
     * Retrieve checkout session payment status, customer id, client reference and plan.
     *
     * @return array{paid:bool, customer_id:?string, client_reference_id:?string, plan:?string}
     */
    public function getCheckoutSessionInfo(string $sessionId): array {
        try {
            $session = $this->client->checkout->sessions->retrieve(
                $sessionId,
                ['expand' => ['line_items']]
            );

            $plan = null;
            if (isset($session->metadata['plan'])) {
                $plan = (string) $session->metadata['plan'];
            } else {
                $item = $session->line_items->data[0] ?? null;
                $priceId = $item?->price->id ?? '';
                if ($priceId !== '') {
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
                }
            }

            return [
                'paid' => ($session->payment_status ?? '') === 'paid',
                'customer_id' => isset($session->customer) ? (string) $session->customer : null,
                'client_reference_id' => isset($session->client_reference_id)
                    ? (string) $session->client_reference_id
                    : null,
                'plan' => $plan,
            ];
        } catch (\Throwable $e) {
            return ['paid' => false, 'customer_id' => null, 'client_reference_id' => null, 'plan' => null];
        }
    }

    /**
     * Retrieve details for the first active subscription of a customer.
     *
     * @return array{plan:?string, amount:int, currency:string, status:string, next_payment:?string}|null
     */
    public function getActiveSubscription(string $customerId): ?array {
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
     * Update the price of the first subscription for the given customer.
     */
    public function updateSubscriptionForCustomer(string $customerId, string $priceId): void {
        $subs = $this->client->subscriptions->all([
            'customer' => $customerId,
            'limit' => 1,
        ]);
        $sub = $subs->data[0] ?? null;
        if ($sub === null) {
            throw new \RuntimeException('subscription-not-found');
        }
        $itemId = $sub->items->data[0]->id ?? null;
        if ($itemId === null) {
            throw new \RuntimeException('subscription-item-not-found');
        }
        $this->client->subscriptions->update((string) $sub->id, [
            'items' => [
                ['id' => $itemId, 'price' => $priceId],
            ],
            'cancel_at_period_end' => false,
        ]);
    }

    /**
     * Cancel the first subscription for the given customer.
     */
    public function cancelSubscriptionForCustomer(string $customerId): void {
        $subs = $this->client->subscriptions->all([
            'customer' => $customerId,
            'limit' => 1,
        ]);
        $sub = $subs->data[0] ?? null;
        if ($sub === null) {
            return;
        }
        $this->client->subscriptions->cancel((string) $sub->id, []);
    }

    /**
     * Retrieve a list of invoices for a customer.
     *
     * @return array<int, array{
     *     id: string,
     *     number: ?string,
     *     amount: int,
     *     currency: string,
     *     status: string,
     *     created: ?string,
     *     invoice_pdf: ?string
     * }>
     */
    public function listInvoices(string $customerId): array {
        $invoices = $this->client->invoices->all([
            'customer' => $customerId,
            'limit' => 24,
        ]);

        $result = [];
        foreach ($invoices->data as $inv) {
            $result[] = [
                'id' => (string) ($inv->id ?? ''),
                'number' => isset($inv->number) ? (string) $inv->number : null,
                'amount' => (int) ($inv->amount_due ?? 0),
                'currency' => (string) ($inv->currency ?? ''),
                'status' => (string) ($inv->status ?? ''),
                'created' => isset($inv->created) ? date('c', (int) $inv->created) : null,
                'invoice_pdf' => (string) ($inv->invoice_pdf ?? ''),
            ];
        }

        return $result;
    }

    /**
     * Check whether Stripe is configured and provide details on issues.
     *
     * @return array{ok:bool, missing:string[], warnings:string[]}
     */
    public static function isConfigured(): array {
        $useSandbox = filter_var(getenv('STRIPE_SANDBOX'), FILTER_VALIDATE_BOOLEAN);
        $prefix = $useSandbox ? 'STRIPE_SANDBOX_' : 'STRIPE_';

        $sk = getenv($prefix . 'SECRET_KEY') ?: getenv($prefix . 'SECRET') ?: '';
        $pk = getenv($prefix . 'PUBLISHABLE_KEY') ?: getenv($prefix . 'PUBLISHABLE') ?: '';
        $wh = getenv($prefix . 'WEBHOOK_SECRET') ?: '';

        $priceStarter = getenv($prefix . 'PRICE_STARTER') ?: '';
        $priceStandard = getenv($prefix . 'PRICE_STANDARD') ?: '';
        $pricePro = getenv($prefix . 'PRICE_PROFESSIONAL') ?: '';
        $pricingTableId = getenv($prefix . 'PRICING_TABLE_ID') ?: '';

        $mapRequired = [
            $prefix . 'SECRET_KEY' => $sk,
            $prefix . 'PUBLISHABLE_KEY' => $pk,
            $prefix . 'PRICE_STARTER' => $priceStarter,
            $prefix . 'PRICE_STANDARD' => $priceStandard,
            $prefix . 'PRICE_PROFESSIONAL' => $pricePro,
            $prefix . 'PRICING_TABLE_ID' => $pricingTableId,
        ];

        $missing = [];
        $warnings = [];
        foreach ($mapRequired as $name => $val) {
            if ($val === '') {
                $missing[] = $name;
                error_log('Missing ' . $name);
            }
        }

        if ($wh === '') {
            $warnings[] = $prefix . 'WEBHOOK_SECRET missing';
            error_log('Missing ' . $prefix . 'WEBHOOK_SECRET');
        }
        $skLive = str_starts_with($sk, 'sk_live_');
        $pkLive = str_starts_with($pk, 'pk_live_');
        if (($skLive && !$pkLive) || (!$skLive && $pkLive)) {
            $warnings[] = 'Publishable/Secret Key sind nicht im selben Modus (test vs live).';
        }

        $appEnv = getenv('APP_ENV') ?: 'dev';
        if ($appEnv === 'production' && !$skLive) {
            $warnings[] = 'APP_ENV=production, aber Test-Keys verwendet.';
        }
        if ($appEnv !== 'production' && $skLive) {
            $warnings[] = 'Nicht-Produktivumgebung mit Live-Keys.';
        }

        return [
            'ok' => $missing === [],
            'missing' => $missing,
            'warnings' => $warnings,
        ];
    }

    /**
     * Perform a preflight check to ensure configured price IDs exist and are active.
     *
     * @return array{ok:bool, issues:string[]}
     */
    public static function preflight(): array {
        $issues = [];
        $useSandbox = filter_var(getenv('STRIPE_SANDBOX'), FILTER_VALIDATE_BOOLEAN);
        $sk = $useSandbox
            ? (getenv('STRIPE_SANDBOX_SECRET_KEY') ?: getenv('STRIPE_SANDBOX_SECRET') ?: '')
            : (getenv('STRIPE_SECRET_KEY') ?: getenv('STRIPE_SECRET') ?: '');

        if ($sk === '') {
            return ['ok' => false, 'issues' => ['Secret-Key fehlt']];
        }

        try {
            $client = new StripeClient($sk);
            $prefix = $useSandbox ? 'STRIPE_SANDBOX_' : 'STRIPE_';
            $prices = [
                'starter' => getenv($prefix . 'PRICE_STARTER') ?: '',
                'standard' => getenv($prefix . 'PRICE_STANDARD') ?: '',
                'pro' => getenv($prefix . 'PRICE_PROFESSIONAL') ?: '',
            ];
            foreach ($prices as $name => $id) {
                if ($id === '') {
                    $issues[] = "Price-ID für {$name} fehlt";
                    continue;
                }
                try {
                    $p = $client->prices->retrieve($id, []);
                    if (!$p->active) {
                        $issues[] = "Price {$name} ({$id}) ist inaktiv";
                    }
                } catch (\Throwable $e) {
                    $issues[] = "Price {$name} ({$id}) nicht abrufbar: "
                        . $e->getMessage();
                }
            }
        } catch (\Throwable $e) {
            $issues[] = 'Stripe SDK/Client-Fehler: ' . $e->getMessage();
        }

        return ['ok' => $issues === [], 'issues' => $issues];
    }
}
