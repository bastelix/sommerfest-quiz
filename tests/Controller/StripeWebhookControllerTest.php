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
            ]],
        ]);
        $request = $this->createRequest('POST', '/stripe/webhook', [
            'Content-Type' => 'application/json',
        ]);
        $request->getBody()->write($payload !== false ? $payload : '');
        $request->getBody()->rewind();
        $response = $app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());

        $stmt = $pdo->query("SELECT stripe_customer_id FROM tenants WHERE subdomain = 'foo'");
        $this->assertEquals('cus_123', $stmt->fetchColumn());
    }

    public function testCustomerDeletedRemovesCustomerAndPlan(): void
    {
        $app = $this->getAppInstance();
        $pdo = Database::connectFromEnv();
        Migrator::migrate($pdo, __DIR__ . '/../../migrations');
        $pdo->exec('DELETE FROM tenants');
        $pdo->exec(
            "INSERT INTO tenants(uid, subdomain, plan, billing_info, stripe_customer_id, created_at) "
            . "VALUES('u1', 'foo', 'starter', NULL, 'cus_123', '')"
        );

        $payload = json_encode([
            'type' => 'customer.deleted',
            'data' => ['object' => [
                'id' => 'cus_123',
            ]],
        ]);
        $request = $this->createRequest('POST', '/stripe/webhook', [
            'Content-Type' => 'application/json',
        ]);
        $request->getBody()->write($payload !== false ? $payload : '');
        $request->getBody()->rewind();
        $response = $app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());

        $stmt = $pdo->query("SELECT plan, stripe_customer_id FROM tenants WHERE subdomain = 'foo'");
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->assertNull($row['plan']);
        $this->assertNull($row['stripe_customer_id']);
    }

    public function testInvoicePaymentFailedCancelsPlan(): void
    {
        $app = $this->getAppInstance();
        $pdo = Database::connectFromEnv();
        Migrator::migrate($pdo, __DIR__ . '/../../migrations');
        $pdo->exec('DELETE FROM tenants');
        $pdo->exec(
            "INSERT INTO tenants(uid, subdomain, plan, billing_info, stripe_customer_id, created_at) "
            . "VALUES('u1', 'foo', 'starter', NULL, 'cus_123', '')"
        );

        $payload = json_encode([
            'type' => 'invoice.payment_failed',
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

        $stmt = $pdo->query("SELECT plan, stripe_customer_id FROM tenants WHERE subdomain = 'foo'");
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->assertNull($row['plan']);
        $this->assertEquals('cus_123', $row['stripe_customer_id']);
    }
}
