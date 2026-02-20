<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Domain\Plan;
use App\Exception\QuotaExceededException;
use App\Service\QuotaService;
use PDO;
use Tests\TestCase;

class QuotaServiceTest extends TestCase
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
            . 'stripe_sub_id TEXT, '
            . 'status TEXT DEFAULT "active", '
            . 'design_config TEXT DEFAULT "{}", '
            . 'created_at TEXT, '
            . 'updated_at TEXT'
            . ')'
        );

        $pdo->exec(
            'CREATE TABLE namespace_quota_usage ('
            . 'namespace_id TEXT NOT NULL, '
            . 'metric TEXT NOT NULL, '
            . 'current_value INTEGER NOT NULL DEFAULT 0, '
            . 'last_updated TEXT, '
            . 'PRIMARY KEY (namespace_id, metric)'
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

    private function seedPlanLimits(PDO $pdo): void
    {
        $stmt = $pdo->prepare('INSERT INTO plan_limits (plan, metric, max_value) VALUES (?, ?, ?)');
        foreach (Plan::cases() as $plan) {
            foreach ($plan->limits() as $metric => $max) {
                $stmt->execute([$plan->value, $metric, $max]);
            }
        }
    }

    private function createNamespace(PDO $pdo, string $id, string $plan = 'free'): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO namespace_projects (id, slug, owner_user_id, display_name, plan) '
            . 'VALUES (?, ?, 1, ?, ?)'
        );
        $stmt->execute([$id, 'test-' . $id, 'Test Namespace', $plan]);
    }

    public function testCanCreateWithinLimit(): void
    {
        $pdo = $this->createPdo();
        $this->seedPlanLimits($pdo);
        $this->createNamespace($pdo, 'ns-1', 'free');

        $service = new QuotaService($pdo);

        // Free plan allows 1 event
        $this->assertTrue($service->canCreate('ns-1', 'events'));
    }

    public function testCanCreateExceeded(): void
    {
        $pdo = $this->createPdo();
        $this->seedPlanLimits($pdo);
        $this->createNamespace($pdo, 'ns-1', 'free');

        $service = new QuotaService($pdo);

        // Set usage to the limit (1 event for free plan)
        $service->setUsage('ns-1', 'events', 1);

        $this->assertFalse($service->canCreate('ns-1', 'events'));
    }

    public function testAssertCanCreateThrowsException(): void
    {
        $pdo = $this->createPdo();
        $this->seedPlanLimits($pdo);
        $this->createNamespace($pdo, 'ns-1', 'free');

        $service = new QuotaService($pdo);
        $service->setUsage('ns-1', 'events', 1);

        $this->expectException(QuotaExceededException::class);
        $service->assertCanCreate('ns-1', 'events');
    }

    public function testQuotaExceededExceptionProperties(): void
    {
        $pdo = $this->createPdo();
        $this->seedPlanLimits($pdo);
        $this->createNamespace($pdo, 'ns-1', 'free');

        $service = new QuotaService($pdo);
        $service->setUsage('ns-1', 'events', 1);

        try {
            $service->assertCanCreate('ns-1', 'events');
            $this->fail('Expected QuotaExceededException');
        } catch (QuotaExceededException $e) {
            $this->assertSame('events', $e->metric);
            $this->assertSame('free', $e->plan);
            $this->assertSame(1, $e->currentValue);
            $this->assertSame(1, $e->maxValue);
        }
    }

    public function testIncrementAndDecrement(): void
    {
        $pdo = $this->createPdo();
        $this->seedPlanLimits($pdo);
        $this->createNamespace($pdo, 'ns-1', 'starter');

        $service = new QuotaService($pdo);

        $this->assertSame(0, $service->getUsage('ns-1', 'events'));

        $service->increment('ns-1', 'events');
        $this->assertSame(1, $service->getUsage('ns-1', 'events'));

        $service->increment('ns-1', 'events', 2);
        $this->assertSame(3, $service->getUsage('ns-1', 'events'));

        $service->decrement('ns-1', 'events');
        $this->assertSame(2, $service->getUsage('ns-1', 'events'));
    }

    public function testDecrementNeverBelowZero(): void
    {
        $pdo = $this->createPdo();
        $this->seedPlanLimits($pdo);
        $this->createNamespace($pdo, 'ns-1', 'free');

        $service = new QuotaService($pdo);

        $service->setUsage('ns-1', 'events', 0);
        $service->decrement('ns-1', 'events');

        $this->assertSame(0, $service->getUsage('ns-1', 'events'));
    }

    public function testSetUsage(): void
    {
        $pdo = $this->createPdo();
        $this->seedPlanLimits($pdo);
        $this->createNamespace($pdo, 'ns-1', 'free');

        $service = new QuotaService($pdo);

        $service->setUsage('ns-1', 'events', 5);
        $this->assertSame(5, $service->getUsage('ns-1', 'events'));

        // Overwrite
        $service->setUsage('ns-1', 'events', 3);
        $this->assertSame(3, $service->getUsage('ns-1', 'events'));

        // Negative clamped to 0
        $service->setUsage('ns-1', 'events', -1);
        $this->assertSame(0, $service->getUsage('ns-1', 'events'));
    }

    public function testGetQuotaOverview(): void
    {
        $pdo = $this->createPdo();
        $this->seedPlanLimits($pdo);
        $this->createNamespace($pdo, 'ns-1', 'free');

        $service = new QuotaService($pdo);
        $service->setUsage('ns-1', 'events', 1);

        $overview = $service->getQuotaOverview('ns-1');

        $this->assertCount(count(Plan::allMetrics()), $overview);

        $eventsEntry = null;
        foreach ($overview as $entry) {
            if ($entry['metric'] === 'events') {
                $eventsEntry = $entry;
                break;
            }
        }

        $this->assertNotNull($eventsEntry);
        $this->assertSame(1, $eventsEntry['current_value']);
        $this->assertSame(1, $eventsEntry['max_value']); // Free plan: 1 event
    }

    public function testFallbackToPlanEnumWhenNoTableRows(): void
    {
        $pdo = $this->createPdo();
        // Don't seed plan_limits - should fall back to Plan enum
        $this->createNamespace($pdo, 'ns-1', 'starter');

        $service = new QuotaService($pdo);

        // Starter plan allows 3 events (from Plan enum)
        $this->assertTrue($service->canCreate('ns-1', 'events'));
        $service->setUsage('ns-1', 'events', 3);
        $this->assertFalse($service->canCreate('ns-1', 'events'));
    }

    public function testStarterPlanLimits(): void
    {
        $pdo = $this->createPdo();
        $this->seedPlanLimits($pdo);
        $this->createNamespace($pdo, 'ns-1', 'starter');

        $service = new QuotaService($pdo);

        // Starter allows 3 events
        $service->setUsage('ns-1', 'events', 2);
        $this->assertTrue($service->canCreate('ns-1', 'events'));

        $service->setUsage('ns-1', 'events', 3);
        $this->assertFalse($service->canCreate('ns-1', 'events'));
    }

    public function testStandardPlanLimits(): void
    {
        $pdo = $this->createPdo();
        $this->seedPlanLimits($pdo);
        $this->createNamespace($pdo, 'ns-1', 'standard');

        $service = new QuotaService($pdo);

        // Standard allows 50 events
        $service->setUsage('ns-1', 'events', 49);
        $this->assertTrue($service->canCreate('ns-1', 'events'));

        $service->setUsage('ns-1', 'events', 50);
        $this->assertFalse($service->canCreate('ns-1', 'events'));
    }

    public function testZeroLimitBlocksCreation(): void
    {
        $pdo = $this->createPdo();
        $this->seedPlanLimits($pdo);
        $this->createNamespace($pdo, 'ns-1', 'free');

        $service = new QuotaService($pdo);

        // Free plan has 0 chatbots allowed
        $this->assertFalse($service->canCreate('ns-1', 'chatbots'));
    }

    public function testUnknownNamespaceFallsToFreePlan(): void
    {
        $pdo = $this->createPdo();
        $this->seedPlanLimits($pdo);

        $service = new QuotaService($pdo);

        // Unknown namespace defaults to 'free' plan
        $this->assertTrue($service->canCreate('nonexistent', 'events'));
    }

    public function testIncrementWithZeroAmountIsNoop(): void
    {
        $pdo = $this->createPdo();
        $this->seedPlanLimits($pdo);
        $this->createNamespace($pdo, 'ns-1', 'free');

        $service = new QuotaService($pdo);
        $service->setUsage('ns-1', 'events', 1);

        $service->increment('ns-1', 'events', 0);
        $this->assertSame(1, $service->getUsage('ns-1', 'events'));

        $service->decrement('ns-1', 'events', 0);
        $this->assertSame(1, $service->getUsage('ns-1', 'events'));
    }

    public function testMultipleMetricsIndependent(): void
    {
        $pdo = $this->createPdo();
        $this->seedPlanLimits($pdo);
        $this->createNamespace($pdo, 'ns-1', 'starter');

        $service = new QuotaService($pdo);

        $service->increment('ns-1', 'events', 2);
        $service->increment('ns-1', 'teams', 10);

        $this->assertSame(2, $service->getUsage('ns-1', 'events'));
        $this->assertSame(10, $service->getUsage('ns-1', 'teams'));
    }
}
