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
                'metadata' => ['plan' => 'starter'],
            ]],
        ]);
        $request = $this->createRequest('POST', '/stripe/webhook', [
            'Content-Type' => 'application/json',
        ]);
        $request->getBody()->write($payload !== false ? $payload : '');
        $request->getBody()->rewind();
        $response = $app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());

        $stmt = $pdo->query("SELECT stripe_customer_id, stripe_subscription_id, plan FROM tenants WHERE subdomain = 'foo'");
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->assertEquals('cus_123', $row['stripe_customer_id']);
        $this->assertEquals('sub_123', $row['stripe_subscription_id']);
        $this->assertEquals('starter', $row['plan']);
    }

    public function testCustomerSubscriptionUpdatedUpdatesDetails(): void
    {
        $app = $this->getAppInstance();
        $pdo = Database::connectFromEnv();
        Migrator::migrate($pdo, __DIR__ . '/../../migrations');
        $pdo->exec('DELETE FROM tenants');
        $pdo->exec(
            "INSERT INTO tenants(uid, subdomain, plan, billing_info, stripe_customer_id, created_at) "
            . "VALUES('u1', 'foo', NULL, NULL, 'cus_123', '')"
        );

        putenv('STRIPE_PRICE_STANDARD=price_standard');
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
        $request = $this->createRequest('POST', '/stripe/webhook', [
            'Content-Type' => 'application/json',
        ]);
        $request->getBody()->write($payload !== false ? $payload : '');
        $request->getBody()->rewind();
        $response = $app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());

        $stmt = $pdo->query("SELECT stripe_subscription_id, plan, stripe_price_id, stripe_status, stripe_current_period_end, stripe_cancel_at_period_end FROM tenants WHERE subdomain = 'foo'");
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->assertEquals('sub_123', $row['stripe_subscription_id']);
        $this->assertEquals('standard', $row['plan']);
        $this->assertEquals('price_standard', $row['stripe_price_id']);
        $this->assertEquals('active', $row['stripe_status']);
        $this->assertEquals(date('Y-m-d H:i:sP', 1234567890), $row['stripe_current_period_end']);
        $this->assertSame('0', (string) $row['stripe_cancel_at_period_end']);
    }

    public function testCustomerSubscriptionDeletedMarksStatusCanceled(): void
    {
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
        $request = $this->createRequest('POST', '/stripe/webhook', [
            'Content-Type' => 'application/json',
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
}
