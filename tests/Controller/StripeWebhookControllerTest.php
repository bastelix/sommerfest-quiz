<?php

declare(strict_types=1);

namespace Tests\Controller;

use Tests\TestCase;
use App\Infrastructure\Database;
use App\Infrastructure\Migrations\Migrator;
use Psr\Http\Message\ServerRequestInterface as Request;

class StripeWebhookControllerTest extends TestCase
{
    /**
     * Create a signed Stripe webhook request for the given payload.
     */
    private function createSignedRequest(string $payload): Request
    {
        $secret = getenv('STRIPE_WEBHOOK_SECRET') ?: '';
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
        $header = 't=' . $timestamp . ',v1=' . $signature;
        $request = $this->createRequest('POST', '/stripe/webhook', [
            'Content-Type' => 'application/json',
            'Stripe-Signature' => $header,
        ]);
        $request->getBody()->write($payload);
        $request->getBody()->rewind();
        return $request;
    }

    public function testCheckoutSessionCompletedUpdatesCustomerId(): void
    {
        putenv('STRIPE_WEBHOOK_SECRET=whsec_test');
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
        $request = $this->createSignedRequest($payload !== false ? $payload : '');
        $response = $app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());

        $stmt = $pdo->query(
            "SELECT stripe_customer_id, stripe_subscription_id, plan FROM tenants WHERE subdomain = 'foo'"
        );
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->assertEquals('cus_123', $row['stripe_customer_id']);
        $this->assertEquals('sub_123', $row['stripe_subscription_id']);
        $this->assertEquals('starter', $row['plan']);
    }

    public function testCustomerSubscriptionUpdatedUpdatesDetails(): void
    {
        putenv('STRIPE_WEBHOOK_SECRET=whsec_test');
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
        $request = $this->createSignedRequest($payload !== false ? $payload : '');
        $response = $app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());

        $stmt = $pdo->query(
            "SELECT stripe_subscription_id, plan, stripe_price_id, stripe_status, "
            . "stripe_current_period_end, stripe_cancel_at_period_end FROM tenants WHERE subdomain = 'foo'"
        );
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
        putenv('STRIPE_WEBHOOK_SECRET=whsec_test');
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
        $request = $this->createSignedRequest($payload !== false ? $payload : '');
        $response = $app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());

        $stmt = $pdo->query("SELECT plan, stripe_status FROM tenants WHERE subdomain = 'foo'");
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->assertNull($row['plan']);
        $this->assertEquals('canceled', $row['stripe_status']);
    }

    public function testInvoicePaidUpdatesStripeStatus(): void
    {
        putenv('STRIPE_WEBHOOK_SECRET=whsec_test');
        $app = $this->getAppInstance();
        $pdo = Database::connectFromEnv();
        Migrator::migrate($pdo, __DIR__ . '/../../migrations');
        $pdo->exec('DELETE FROM tenants');
        $pdo->exec(
            "INSERT INTO tenants(uid, subdomain, plan, billing_info, stripe_customer_id, stripe_status, created_at) "
            . "VALUES('u1', 'foo', NULL, NULL, 'cus_123', 'open', '')"
        );

        $payload = json_encode([
            'type' => 'invoice.paid',
            'data' => ['object' => [
                'customer' => 'cus_123',
            ]],
        ]);
        $request = $this->createSignedRequest($payload !== false ? $payload : '');
        $response = $app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());

        $stmt = $pdo->query("SELECT stripe_status FROM tenants WHERE subdomain = 'foo'");
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->assertEquals('paid', $row['stripe_status']);
    }

    public function testInvoicePaymentFailedSetsStripeStatusPastDue(): void
    {
        putenv('STRIPE_WEBHOOK_SECRET=whsec_test');
        $app = $this->getAppInstance();
        $pdo = Database::connectFromEnv();
        Migrator::migrate($pdo, __DIR__ . '/../../migrations');
        $pdo->exec('DELETE FROM tenants');
        $pdo->exec(
            "INSERT INTO tenants(uid, subdomain, plan, billing_info, stripe_customer_id, stripe_status, created_at) "
            . "VALUES('u1', 'foo', NULL, NULL, 'cus_123', 'open', '')"
        );

        $payload = json_encode([
            'type' => 'invoice.payment_failed',
            'data' => ['object' => [
                'customer' => 'cus_123',
            ]],
        ]);
        $request = $this->createSignedRequest($payload !== false ? $payload : '');
        $response = $app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());

        $stmt = $pdo->query("SELECT stripe_status FROM tenants WHERE subdomain = 'foo'");
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->assertEquals('past_due', $row['stripe_status']);
    }

    public function testMissingSecretReturns500AndLogs(): void
    {
        putenv('STRIPE_WEBHOOK_SECRET=');
        $logFile = __DIR__ . '/../../logs/stripe.log';
        @unlink($logFile);
        $app = $this->getAppInstance();
        $payload = json_encode([
            'type' => 'invoice.paid',
            'data' => ['object' => ['customer' => 'cus_123']],
        ]);
        $request = $this->createRequest('POST', '/stripe/webhook', [
            'Content-Type' => 'application/json',
        ]);
        $request->getBody()->write($payload !== false ? $payload : '');
        $request->getBody()->rewind();
        $response = $app->handle($request);
        $this->assertEquals(500, $response->getStatusCode());
        $log = file_get_contents($logFile);
        $this->assertStringContainsString('STRIPE_WEBHOOK_SECRET missing', (string) $log);
    }
}
