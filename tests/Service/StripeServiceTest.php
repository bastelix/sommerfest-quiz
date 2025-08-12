<?php

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
    public function testCreateCheckoutSessionAddsPaymentMethodTypes(): void
    {
        $client = new FakeStripeClient();
        $service = new StripeService(client: $client);
        $service->createCheckoutSession('price_123', 'https://success', 'https://cancel', 'user@example.com');
        $this->assertSame(['card'], $client->checkout->sessions->lastParams['payment_method_types'] ?? null);
    }
}
