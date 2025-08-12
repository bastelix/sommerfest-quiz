<?php

// phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses
declare(strict_types=1);

namespace Tests\Service;

use App\Service\StripeService;
use PHPUnit\Framework\TestCase;

class FakeCheckoutSessions
{
    public array $lastParams = [];
    public function create(array $params)
    {
        $this->lastParams = $params;
        if (($params['ui_mode'] ?? '') === 'embedded') {
            return (object) ['client_secret' => 'cs_test'];
        }
        return (object) ['url' => 'https://example.com'];
    }
}

class FakeCheckout
{
    public FakeCheckoutSessions $sessions;
    public function __construct()
    {
        $this->sessions = new FakeCheckoutSessions();
    }
}

class FakeStripeClient extends \Stripe\StripeClient
{
    public FakeCheckout $checkout;
    public function __construct()
    {
        parent::__construct('sk_test_fake');
        $this->checkout = new FakeCheckout();
    }
}

final class StripeServiceTest extends TestCase
{
    public function testCreateCheckoutSessionAddsPaymentMethodTypesAndTrialPeriod(): void
    {
        $client = new FakeStripeClient();
        $service = new StripeService(client: $client);
        $service->createCheckoutSession('price_123', 'https://success', 'https://cancel', 'user@example.com');
        $this->assertSame(['card'], $client->checkout->sessions->lastParams['payment_method_types'] ?? null);
        $this->assertSame(7, $client->checkout->sessions->lastParams['subscription_data']['trial_period_days'] ?? null);
    }

    public function testCreateCheckoutSessionWithCustomerIdAndReference(): void
    {
        $client = new FakeStripeClient();
        $service = new StripeService(client: $client);
        $service->createCheckoutSession('price_123', 'https://success', 'https://cancel', null, 'cus_123', 'tenant1');
        $this->assertSame('cus_123', $client->checkout->sessions->lastParams['customer'] ?? null);
        $this->assertSame('tenant1', $client->checkout->sessions->lastParams['client_reference_id'] ?? null);
    }

    public function testCreateEmbeddedCheckoutSession(): void
    {
        $client = new FakeStripeClient();
        $service = new StripeService(client: $client);
        $secret = $service->createCheckoutSession('price_123', 'https://return', null, null, null, null, true);
        $this->assertSame('embedded', $client->checkout->sessions->lastParams['ui_mode'] ?? null);
        $this->assertSame('https://return', $client->checkout->sessions->lastParams['return_url'] ?? null);
        $this->assertSame('cs_test', $secret);
    }
}
