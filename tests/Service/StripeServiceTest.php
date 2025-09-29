<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\StripeService;
use PHPUnit\Framework\TestCase;

final class StripeServiceTest extends TestCase
{
    private function createFakeStripeClient(): \Stripe\StripeClient {
        return new class extends \Stripe\StripeClient {
            public object $checkout;
            public object $customers;
            public object $subscriptions;

            public function __construct() {
                parent::__construct('sk_test_fake');
                $this->checkout = new class {
                    public object $sessions;

                    public function __construct() {
                        $this->sessions = new class {
                            public array $lastParams = [];
                            public string $lastRetrievedId = '';
                            public ?object $retrieveResponse = null;

                            public function create(array $params) {
                                $this->lastParams = $params;
                                return (object) ['url' => 'https://example.com', 'client_secret' => 'sec_123'];
                            }

                            public function retrieve(string $id, array $params) {
                                $this->lastRetrievedId = $id;
                                if ($this->retrieveResponse !== null) {
                                    return $this->retrieveResponse;
                                }
                                return (object) [
                                    'payment_status' => 'paid',
                                    'customer' => 'cus_123',
                                    'client_reference_id' => 'tenant1',
                                    'metadata' => ['plan' => 'starter'],
                                ];
                            }
                        };
                    }
                };
                $this->customers = new class {
                    public array $lastParams = [];

                    public function create(array $params) {
                        $this->lastParams = $params;
                        return (object) ['id' => 'cus_new'];
                    }

                    public function all(array $params) {
                        return (object) ['data' => []];
                    }
                };
                $this->subscriptions = new class {
                    public array $lastParams = [];

                    public function all(array $params) {
                        $this->lastParams = $params;
                        return (object) ['data' => [
                            (object) [
                                'items' => (object) [
                                    'data' => [
                                        (object) [
                                            'price' => (object) [
                                                'id' => 'price_standard',
                                                'unit_amount' => 3900,
                                                'currency' => 'eur',
                                            ],
                                        ],
                                    ],
                                ],
                                'current_period_end' => strtotime('2024-01-01T00:00:00Z'),
                                'latest_invoice' => (object) ['status' => 'paid'],
                            ],
                        ]];
                    }
                };
            }
        };
    }

    public function testCreateCheckoutSessionAddsTrialPeriod(): void {
        $client = $this->createFakeStripeClient();
        $service = new StripeService(client: $client);
        $service->createCheckoutSession(
            'price_123',
            'https://success',
            'https://cancel',
            'starter',
            'user@example.com',
            null,
            null,
            7
        );
        $this->assertSame(
            7,
            $client->checkout->sessions->lastParams['subscription_data']['trial_period_days'] ?? null
        );
        $this->assertSame(
            'starter',
            $client->checkout->sessions->lastParams['metadata']['plan'] ?? null
        );
        $this->assertSame(
            'if_required',
            $client->checkout->sessions->lastParams['payment_method_collection'] ?? null
        );
        $this->assertArrayNotHasKey(
            'automatic_payment_methods',
            $client->checkout->sessions->lastParams
        );
    }

    public function testCreateCheckoutSessionDefaultsToSevenDayTrial(): void {
        $client = $this->createFakeStripeClient();
        $service = new StripeService(client: $client);
        putenv('STRIPE_TRIAL_DAYS');
        $service->createCheckoutSession(
            'price_123',
            'https://success',
            'https://cancel',
            'starter'
        );
        $this->assertSame(
            7,
            $client->checkout->sessions->lastParams['subscription_data']['trial_period_days'] ?? null
        );
        $this->assertSame(
            'if_required',
            $client->checkout->sessions->lastParams['payment_method_collection'] ?? null
        );
    }

    public function testCreateCheckoutSessionWithCustomerIdAndReference(): void {
        $client = $this->createFakeStripeClient();
        $service = new StripeService(client: $client);
        $service->createCheckoutSession(
            'price_123',
            'https://success',
            'https://cancel',
            'standard',
            null,
            'cus_123',
            'tenant1'
        );
        $this->assertSame(
            'cus_123',
            $client->checkout->sessions->lastParams['customer'] ?? null
        );
        $this->assertSame(
            'tenant1',
            $client->checkout->sessions->lastParams['client_reference_id'] ?? null
        );
    }

    public function testCreateEmbeddedCheckoutSessionReturnsClientSecret(): void {
        $client = $this->createFakeStripeClient();
        $service = new StripeService(client: $client);
        $secret = $service->createCheckoutSession(
            'price_123',
            'https://success',
            'https://cancel',
            'starter',
            embedded: true
        );
        $this->assertSame(
            'embedded',
            $client->checkout->sessions->lastParams['ui_mode'] ?? null
        );
        $this->assertSame(
            'https://success',
            $client->checkout->sessions->lastParams['return_url'] ?? null
        );
        $this->assertSame('sec_123', $secret);
    }

    public function testCreateCustomer(): void {
        $client = $this->createFakeStripeClient();
        $service = new StripeService(client: $client);
        $id = $service->createCustomer('user@example.com', 'User');
        $this->assertSame(
            'user@example.com',
            $client->customers->lastParams['email'] ?? null
        );
        $this->assertSame(
            'User',
            $client->customers->lastParams['name'] ?? null
        );
        $this->assertSame('cus_new', $id);
    }

    public function testGetCheckoutSessionInfoReturnsReference(): void {
        $client = $this->createFakeStripeClient();
        $service = new StripeService(client: $client);
        $info = $service->getCheckoutSessionInfo('sess_123');
        $this->assertTrue($info['paid']);
        $this->assertSame(
            'cus_123',
            $info['customer_id']
        );
        $this->assertSame(
            'tenant1',
            $info['client_reference_id']
        );
        $this->assertSame('starter', $info['plan']);
    }

    public function testGetCheckoutSessionInfoUsesPriceIdForPlan(): void {
        $client = $this->createFakeStripeClient();
        $client->checkout->sessions->retrieveResponse = (object) [
            'payment_status' => 'paid',
            'customer' => 'cus_123',
            'client_reference_id' => 'tenant1',
            'line_items' => (object) [
                'data' => [
                    (object) ['price' => (object) ['id' => 'price_standard']],
                ],
            ],
        ];
        putenv('STRIPE_PRICE_STANDARD=price_standard');
        $service = new StripeService(client: $client);
        $info = $service->getCheckoutSessionInfo('sess_123');
        $this->assertSame('standard', $info['plan']);
    }

    public function testGetActiveSubscriptionReturnsDetails(): void {
        $client = $this->createFakeStripeClient();
        putenv('STRIPE_PRICE_STANDARD=price_standard');
        $service = new StripeService(client: $client);
        $info = $service->getActiveSubscription('cus_123');
        $this->assertNotNull($info);
        $this->assertSame(
            'standard',
            $info['plan'] ?? null
        );
        $this->assertSame(
            3900,
            $info['amount'] ?? null
        );
        $this->assertSame(
            'eur',
            $info['currency'] ?? null
        );
        $this->assertSame(
            'paid',
            $info['status'] ?? null
        );
        $this->assertSame(
            '2024-01-01T00:00:00+00:00',
            $info['next_payment'] ?? null
        );
    }
}
