<?php

declare(strict_types=1);

namespace Tests\Controller;

use App\Service\StripeService;
use Tests\TestCase;

class StripeCheckoutControllerTest extends TestCase
{
    public function testPostRequiresSubdomain(): void {
        putenv('STRIPE_SECRET_KEY=key');
        putenv('STRIPE_PUBLISHABLE_KEY=pub');
        putenv('STRIPE_WEBHOOK_SECRET=wh');
        putenv('STRIPE_PRICE_STARTER=starter');
        putenv('STRIPE_PRICE_STANDARD=price_standard');
        putenv('STRIPE_PRICE_PROFESSIONAL=pro');
        putenv('STRIPE_PRICING_TABLE_ID=tbl');
        $app = $this->getAppInstance();
        session_start();
        $_SESSION['csrf_token'] = 'tok';
        $request = $this->createRequest('POST', '/onboarding/checkout', [
            'Content-Type' => 'application/json',
            'X-CSRF-Token' => 'tok',
        ]);
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, json_encode(['plan' => 'standard', 'email' => 'u@example.com']));
        rewind($stream);
        $request = $request->withBody((new \Slim\Psr7\Factory\StreamFactory())->createStreamFromResource($stream));
        $response = $app->handle($request);
        $this->assertSame(422, $response->getStatusCode());
    }

    public function testPostStartsCheckoutWithReference(): void {
        putenv('STRIPE_SECRET_KEY=key');
        putenv('STRIPE_PUBLISHABLE_KEY=pub');
        putenv('STRIPE_WEBHOOK_SECRET=wh');
        putenv('STRIPE_PRICE_STARTER=starter');
        putenv('STRIPE_PRICE_STANDARD=price_standard');
        putenv('STRIPE_PRICE_PROFESSIONAL=pro');
        putenv('STRIPE_PRICING_TABLE_ID=tbl');
        $app = $this->getAppInstance();
        session_start();
        $_SESSION['csrf_token'] = 'tok';
        $service = new class extends StripeService {
            public array $args = [];

            public function __construct() {
            }
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
                $this->args['create'] = func_get_args();
                return 'https://example.com/checkout';
            }
        };
        $request = $this->createRequest('POST', '/onboarding/checkout', [
            'Content-Type' => 'application/json',
            'X-CSRF-Token' => 'tok',
        ]);
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, json_encode([
            'plan' => 'standard',
            'email' => 'u@example.com',
            'subdomain' => 'tenant1'
        ]));
        rewind($stream);
        $request = $request->withBody((new \Slim\Psr7\Factory\StreamFactory())->createStreamFromResource($stream));
        $request = $request->withAttribute('stripeService', $service);
        $response = $app->handle($request);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('standard', $service->args['create'][3] ?? null);
        $this->assertSame('u@example.com', $service->args['create'][4] ?? null);
        $this->assertNull($service->args['create'][5] ?? null);
        $this->assertSame('tenant1', $service->args['create'][6] ?? null);
        $this->assertSame(7, $service->args['create'][7] ?? null);
        $this->assertStringContainsString(
            '/onboarding/checkout/{CHECKOUT_SESSION_ID}',
            $service->args['create'][1] ?? ''
        );
    }

    public function testPostReturnsCheckoutUrl(): void {
        putenv('STRIPE_SECRET_KEY=key');
        putenv('STRIPE_PUBLISHABLE_KEY=pub');
        putenv('STRIPE_WEBHOOK_SECRET=wh');
        putenv('STRIPE_PRICE_STARTER=starter');
        putenv('STRIPE_PRICE_STANDARD=price_standard');
        putenv('STRIPE_PRICE_PROFESSIONAL=pro');
        putenv('STRIPE_PRICING_TABLE_ID=tbl');
        $app = $this->getAppInstance();
        session_start();
        $_SESSION['csrf_token'] = 'tok';
        $service = new class extends StripeService {
            public function __construct() {
            }
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
                return 'https://example.com/checkout';
            }
        };
        $request = $this->createRequest('POST', '/onboarding/checkout', [
            'Content-Type' => 'application/json',
            'X-CSRF-Token' => 'tok',
        ]);
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, json_encode([
            'plan' => 'standard',
            'email' => 'u@example.com',
            'subdomain' => 'tenant1'
        ]));
        rewind($stream);
        $request = $request->withBody((new \Slim\Psr7\Factory\StreamFactory())->createStreamFromResource($stream));
        $request = $request->withAttribute('stripeService', $service);
        $response = $app->handle($request);
        $this->assertSame(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertSame('https://example.com/checkout', $data['url'] ?? null);
    }
}
