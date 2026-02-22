<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Domain\Plan;
use App\Service\NamespaceSubscriptionService;
use PDO;
use Tests\TestCase;

class NamespaceSubscriptionServiceTest extends TestCase
{
    private function createPdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $pdo->exec(
            'CREATE TABLE namespace_projects ('
            . 'id TEXT PRIMARY KEY, '
            . 'slug TEXT UNIQUE NOT NULL, '
            . 'owner_user_id INTEGER NOT NULL, '
            . 'display_name TEXT NOT NULL, '
            . 'plan TEXT NOT NULL DEFAULT "free", '
            . 'stripe_customer_id TEXT, '
            . 'stripe_sub_id TEXT, '
            . 'stripe_price_id TEXT, '
            . 'stripe_status TEXT, '
            . 'stripe_current_period_end TEXT, '
            . 'stripe_cancel_at_period_end INTEGER DEFAULT 0, '
            . 'status TEXT DEFAULT "active", '
            . 'design_config TEXT DEFAULT "{}", '
            . 'created_at TEXT, '
            . 'updated_at TEXT'
            . ')'
        );

        $pdo->exec(
            'CREATE TABLE plan_limits ('
            . 'plan TEXT NOT NULL, '
            . 'metric TEXT NOT NULL, '
            . 'max_value INTEGER NOT NULL, '
            . 'PRIMARY KEY (plan, metric)'
            . ')'
        );

        return $pdo;
    }

    public function testFindBySlugReturnsNullForMissing(): void
    {
        $pdo = $this->createPdo();
        $service = new NamespaceSubscriptionService($pdo);

        $this->assertNull($service->findBySlug('nonexistent'));
    }

    public function testFindBySlugReturnsNullForEmpty(): void
    {
        $pdo = $this->createPdo();
        $service = new NamespaceSubscriptionService($pdo);

        $this->assertNull($service->findBySlug(''));
    }

    public function testFindOrCreateCreatesNewProject(): void
    {
        $pdo = $this->createPdo();
        $service = new NamespaceSubscriptionService($pdo);

        $project = $service->findOrCreate('my-namespace');

        $this->assertSame('my-namespace', $project['slug']);
        $this->assertSame('free', $project['plan']);
        $this->assertSame('active', $project['status']);
    }

    public function testFindOrCreateReturnsExisting(): void
    {
        $pdo = $this->createPdo();
        $service = new NamespaceSubscriptionService($pdo);

        $first = $service->findOrCreate('my-namespace');
        $second = $service->findOrCreate('my-namespace');

        $this->assertSame($first['id'], $second['id']);
    }

    public function testGetPlanReturnsFreeForUnknown(): void
    {
        $pdo = $this->createPdo();
        $service = new NamespaceSubscriptionService($pdo);

        $this->assertSame('free', $service->getPlan('unknown'));
    }

    public function testGetPlanReturnsCorrectPlan(): void
    {
        $pdo = $this->createPdo();
        $service = new NamespaceSubscriptionService($pdo);
        $service->findOrCreate('my-ns');
        $service->updatePlan('my-ns', 'starter');

        $this->assertSame('starter', $service->getPlan('my-ns'));
    }

    public function testUpdatePlanRejectsInvalid(): void
    {
        $pdo = $this->createPdo();
        $service = new NamespaceSubscriptionService($pdo);
        $service->findOrCreate('my-ns');

        $this->expectException(\InvalidArgumentException::class);
        $service->updatePlan('my-ns', 'invalid-plan');
    }

    public function testUpdateStripeInfo(): void
    {
        $pdo = $this->createPdo();
        $service = new NamespaceSubscriptionService($pdo);
        $service->findOrCreate('my-ns');

        $service->updateStripeInfo('my-ns', [
            'stripe_customer_id' => 'cus_test123',
            'stripe_sub_id' => 'sub_test456',
            'stripe_status' => 'active',
        ]);

        $project = $service->findBySlug('my-ns');

        $this->assertSame('cus_test123', $project['stripe_customer_id']);
        $this->assertSame('sub_test456', $project['stripe_sub_id']);
        $this->assertSame('active', $project['stripe_status']);
    }

    public function testUpdateStripeInfoIgnoresUnknownFields(): void
    {
        $pdo = $this->createPdo();
        $service = new NamespaceSubscriptionService($pdo);
        $service->findOrCreate('my-ns');

        // Should not throw - unknown fields are silently ignored
        $service->updateStripeInfo('my-ns', [
            'unknown_field' => 'value',
        ]);

        $project = $service->findBySlug('my-ns');
        $this->assertNotNull($project);
    }

    public function testGetLimitsReturnsFreeLimitsForUnknown(): void
    {
        $pdo = $this->createPdo();
        $service = new NamespaceSubscriptionService($pdo);

        $limits = $service->getLimits('unknown');
        $freeLimits = Plan::FREE->limits();

        $this->assertSame($freeLimits['events'], $limits['events']);
    }

    public function testGetLimitsReturnsCorrectPlanLimits(): void
    {
        $pdo = $this->createPdo();
        $service = new NamespaceSubscriptionService($pdo);
        $service->findOrCreate('my-ns');
        $service->updatePlan('my-ns', 'standard');

        $limits = $service->getLimits('my-ns');
        $standardLimits = Plan::STANDARD->limits();

        $this->assertSame($standardLimits['events'], $limits['events']);
    }

    public function testListAll(): void
    {
        $pdo = $this->createPdo();
        $service = new NamespaceSubscriptionService($pdo);

        $service->findOrCreate('ns-a');
        $service->findOrCreate('ns-b');

        $all = $service->listAll();

        $this->assertCount(2, $all);
        $slugs = array_column($all, 'slug');
        $this->assertContains('ns-a', $slugs);
        $this->assertContains('ns-b', $slugs);
    }

    public function testGetSubscriptionStatusWithoutStripe(): void
    {
        $pdo = $this->createPdo();
        $service = new NamespaceSubscriptionService($pdo);
        $service->findOrCreate('my-ns');
        $service->updatePlan('my-ns', 'starter');

        $status = $service->getSubscriptionStatus('my-ns');

        $this->assertSame('starter', $status['plan']);
        $this->assertFalse($status['cancel_at_period_end']);
    }

    public function testSlugNormalization(): void
    {
        $pdo = $this->createPdo();
        $service = new NamespaceSubscriptionService($pdo);

        $service->findOrCreate('My-Namespace');
        $project = $service->findBySlug('my-namespace');

        $this->assertNotNull($project);
        $this->assertSame('my-namespace', $project['slug']);
    }
}
