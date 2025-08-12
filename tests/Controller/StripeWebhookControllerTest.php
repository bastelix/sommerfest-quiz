<?php

declare(strict_types=1);

namespace Tests\Controller;

use Tests\TestCase;
use App\Infrastructure\Database;
use App\Infrastructure\Migrations\Migrator;

class StripeWebhookControllerTest extends TestCase
{
    public function testCheckoutSessionCompletedUpdatesCustomerId(): void
    {
        $app = $this->getAppInstance();
        $pdo = Database::connectFromEnv();
        Migrator::migrate($pdo, __DIR__ . '/../../migrations');
        $pdo->exec(
            "INSERT INTO tenants(uid, subdomain, plan, billing_info, stripe_customer_id, created_at) "
            . "VALUES('u1', 'foo', NULL, NULL, NULL, '')"
        );

        putenv('STRIPE_WEBHOOK_SECRET=whsec_test');
        $payload = json_encode([
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'client_reference_id' => 'foo',
                'customer' => 'cus_123',
            ]],
        ]);

        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp . '.' . ($payload !== false ? $payload : ''), 'whsec_test');
        $sigHeader = 't=' . $timestamp . ',v1=' . $signature;

        $request = $this->createRequest('POST', '/stripe/webhook', [
            'Content-Type' => 'application/json',
            'Stripe-Signature' => $sigHeader,
        ]);
        $request->getBody()->write($payload !== false ? $payload : '');
        $request->getBody()->rewind();
        $response = $app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());

        $stmt = $pdo->query("SELECT stripe_customer_id FROM tenants WHERE subdomain = 'foo'");
        $this->assertEquals('cus_123', $stmt->fetchColumn());
    }
}
