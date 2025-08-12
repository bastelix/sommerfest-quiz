<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\StripeService;
use PHPUnit\Framework\TestCase;

final class StripeServiceTest extends TestCase
{
    private function createFakeStripeClient(): \Stripe\StripeClient
    {
        return new class extends \Stripe\StripeClient {
            public object $checkout;

            public function __construct()
            {
                parent::__construct('sk_test_fake');
                $this->checkout = new class {
                    public object $sessions;

                    public function __construct()
                    {
                        $this->sessions = new class {
                            public array $lastParams = [];

                            public function create(array $params)
                            {
                                $this->lastParams = $params;
                                return (object) ['url' => 'https://example.com', 'client_secret' => 'sec_123'];
                            }
                        };
                    }
                };
            }
        };
    }

    public function testCreateCheckoutSessionAddsPaymentMethodTypesAndTrialPeriod(): void
    {
        $client = $this->createFakeStripeClient();
        $service = new StripeService(client: $client);
        $service->createCheckoutSession('price_123', 'https://success', 'https://cancel', 'user@example.com');
        $this->assertSame(['card'], $client->checkout->sessions->lastParams['payment_method_types'] ?? null);
        $this->assertSame(7, $client->checkout->sessions->lastParams['subscription_data']['trial_period_days'] ?? null);
    }

    public function testCreateCheckoutSessionWithCustomerIdAndReference(): void
    {
        $client = $this->createFakeStripeClient();
        $service = new StripeService(client: $client);
        $service->createCheckoutSession('price_123', 'https://success', 'https://cancel', null, 'cus_123', 'tenant1');
        $this->assertSame('cus_123', $client->checkout->sessions->lastParams['customer'] ?? null);
        $this->assertSame('tenant1', $client->checkout->sessions->lastParams['client_reference_id'] ?? null);
    }

    public function testCreateEmbeddedCheckoutSessionReturnsClientSecret(): void
    {
        $client = $this->createFakeStripeClient();
        $service = new StripeService(client: $client);
        $secret = $service->createCheckoutSession('price_123', 'https://success', 'https://cancel', embedded: true);
        $this->assertSame('embedded', $client->checkout->sessions->lastParams['ui_mode'] ?? null);
        $this->assertSame('https://success', $client->checkout->sessions->lastParams['return_url'] ?? null);
        $this->assertSame('sec_123', $secret);
    }
}
