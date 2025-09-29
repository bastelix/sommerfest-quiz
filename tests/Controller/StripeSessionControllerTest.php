<?php

declare(strict_types=1);

namespace Tests\Controller;

use App\Service\StripeService;
use Tests\TestCase;

final class StripeSessionControllerTest extends TestCase
{
    public function testUsesSessionIdFromRoute(): void {
        $app = $this->getAppInstance();
        $service = new class extends StripeService {
            public array $args = [];

            public function __construct() {
            }

            public function getCheckoutSessionInfo(string $sessionId): array {
                $this->args[] = $sessionId;
                return [
                    'paid' => true,
                    'customer_id' => null,
                    'client_reference_id' => null,
                    'plan' => 'starter',
                ];
            }
        };
        $request = $this->createRequest('GET', '/onboarding/checkout/sess_123')
            ->withHeader('X-Requested-With', 'fetch');
        $request = $request->withAttribute('stripeService', $service);
        $response = $app->handle($request);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['sess_123'], $service->args);
        $data = json_decode((string) $response->getBody(), true);
        $this->assertTrue($data['paid']);
        $this->assertSame('starter', $data['plan']);
    }
}
