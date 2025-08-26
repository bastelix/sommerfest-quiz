<?php

declare(strict_types=1);

namespace Tests\Controller;

use Tests\TestCase;
use App\Infrastructure\Database;
use App\Infrastructure\Migrations\Migrator;

class StripeWebhookControllerTest extends TestCase
{
    private function generateSignatureHeader(string $payload, string $secret): string
    {
        $timestamp = time();
        $signedPayload = $timestamp . '.' . $payload;
        $signature = hash_hmac('sha256', $signedPayload, $secret);

        return 't=' . $timestamp . ',v1=' . $signature;
    }

    public function testCheckoutSessionCompletedUpdatesCustomerId(): void
    {
        $secret = 'whsec_test';
        putenv('STRIPE_WEBHOOK_SECRET=' . $secret);
        $app = $this->getAppInstance();
        $pdo = Database::connectFromEnv();
        Migrator::migrate($pdo, __DIR__ . '/../../migrations');
        $pdo->exec('DELETE FROM tenants');
        $pdo->exec(
            "INSERT INTO tenants(uid, subdomain, plan, billing_info, stripe_customer_id, created_at) "
            . "VALUES('u1', 'foo', NULL, NULL, NULL, '')"
        );

        $payload = json_encode([
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'client_reference_id' => 'foo',
                'customer' => 'cus_123',
                'subscription' => 'sub_123',
            ]],
        ]);
        $sig = $this->generateSignatureHeader($payload !== false ? $payload : '', $secret);
        $request = $this->createRequest('POST', '/stripe/webhook', [
            'Content-Type' => 'application/json',
            'Stripe-Signature' => $sig,
        ]);
        $request->getBody()->write($payload !== false ? $payload : '');
        $request->getBody()->rewind();
        $response = $app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());

        $stmt = $pdo->query(
            "SELECT stripe_customer_id, stripe_subscription_id, plan FROM tenants WHERE subdomain = 'foo'"
        );
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->assertEquals('cus_123', $row['stripe_customer_id']);
        $this->assertEquals('sub_123', $row['stripe_subscription_id']);
        $this->assertNull($row['plan']);
    }

    public function testCustomerSubscriptionUpdatedUpdatesDetails(): void
    {
        $secret = 'whsec_test';
        putenv('STRIPE_WEBHOOK_SECRET=' . $secret);
        $app = $this->getAppInstance();
        $pdo = Database::connectFromEnv();
        Migrator::migrate($pdo, __DIR__ . '/../../migrations');
        $pdo->exec('DELETE FROM tenants');
        $pdo->exec(
            "INSERT INTO tenants(uid, subdomain, plan, billing_info, stripe_customer_id, created_at) "
            . "VALUES('u1', 'foo', NULL, NULL, 'cus_123', '')"
        );
        $payload = json_encode([
            'type' => 'customer.subscription.updated',
            'data' => ['object' => [
                'id' => 'sub_123',
                'customer' => 'cus_123',
                'items' => ['data' => [['price' => ['id' => 'price_standard']]]],
                'status' => 'active',
                'current_period_end' => 1234567890,
                'cancel_at_period_end' => false,
            ]],
        ]);
        $sig = $this->generateSignatureHeader($payload !== false ? $payload : '', $secret);
        $request = $this->createRequest('POST', '/stripe/webhook', [
            'Content-Type' => 'application/json',
            'Stripe-Signature' => $sig,
        ]);
        $request->getBody()->write($payload !== false ? $payload : '');
        $request->getBody()->rewind();
        $response = $app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());

        $stmt = $pdo->query(
            "SELECT stripe_subscription_id, plan, stripe_price_id, stripe_status, "
            . "stripe_current_period_end, stripe_cancel_at_period_end FROM tenants WHERE subdomain = 'foo'"
        );
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->assertEquals('sub_123', $row['stripe_subscription_id']);
        $this->assertNull($row['plan']);
        $this->assertEquals('price_standard', $row['stripe_price_id']);
        $this->assertEquals('active', $row['stripe_status']);
        $this->assertEquals(date('Y-m-d H:i:sP', 1234567890), $row['stripe_current_period_end']);
        $this->assertSame('0', (string) $row['stripe_cancel_at_period_end']);
    }

    public function testCustomerSubscriptionDeletedMarksStatusCanceled(): void
    {
        $secret = 'whsec_test';
        putenv('STRIPE_WEBHOOK_SECRET=' . $secret);
        $app = $this->getAppInstance();
        $pdo = Database::connectFromEnv();
        Migrator::migrate($pdo, __DIR__ . '/../../migrations');
        $pdo->exec('DELETE FROM tenants');
        $pdo->exec(
            "INSERT INTO tenants(uid, subdomain, plan, billing_info, stripe_customer_id, stripe_status, created_at) "
            . "VALUES('u1', 'foo', 'starter', NULL, 'cus_123', 'active', '')"
        );

        $payload = json_encode([
            'type' => 'customer.subscription.deleted',
            'data' => ['object' => [
                'customer' => 'cus_123',
            ]],
        ]);
        $sig = $this->generateSignatureHeader($payload !== false ? $payload : '', $secret);
        $request = $this->createRequest('POST', '/stripe/webhook', [
            'Content-Type' => 'application/json',
            'Stripe-Signature' => $sig,
        ]);
        $request->getBody()->write($payload !== false ? $payload : '');
        $request->getBody()->rewind();
        $response = $app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());

        $stmt = $pdo->query("SELECT plan, stripe_status FROM tenants WHERE subdomain = 'foo'");
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->assertNull($row['plan']);
        $this->assertEquals('canceled', $row['stripe_status']);
    }

    public function testMissingSecretReturns500(): void
    {
        putenv('STRIPE_WEBHOOK_SECRET');
        $app = $this->getAppInstance();
        $request = $this->createRequest('POST', '/stripe/webhook', [
            'Content-Type' => 'application/json',
        ]);
        $request->getBody()->write('{}');
        $request->getBody()->rewind();
        $response = $app->handle($request);
        $this->assertEquals(500, $response->getStatusCode());
    }
}
