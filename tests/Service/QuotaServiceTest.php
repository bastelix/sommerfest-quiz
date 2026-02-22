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

        // Tables for hybrid recount
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS events ('
            . 'uid TEXT PRIMARY KEY, slug TEXT UNIQUE NOT NULL, '
            . 'namespace TEXT NOT NULL DEFAULT "default", '
            . 'name TEXT NOT NULL, start_date TEXT, end_date TEXT, '
            . 'description TEXT, published INTEGER DEFAULT 0, sort_order INTEGER DEFAULT 0'
            . ')'
        );
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS teams ('
            . 'uid TEXT PRIMARY KEY, sort_order INTEGER NOT NULL, '
            . 'name TEXT NOT NULL, namespace TEXT DEFAULT "default", '
            . 'event_uid TEXT REFERENCES events(uid) ON DELETE CASCADE'
            . ')'
        );
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS catalogs ('
            . 'uid TEXT PRIMARY KEY, sort_order INTEGER NOT NULL, '
            . 'slug TEXT UNIQUE NOT NULL, name TEXT NOT NULL, '
            . 'namespace TEXT DEFAULT "default", event_uid TEXT'
            . ')'
        );
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS questions ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT, '
            . 'catalog_uid TEXT NOT NULL, sort_order INTEGER, '
            . 'question TEXT NOT NULL, answer1 TEXT, answer2 TEXT, answer3 TEXT, answer4 TEXT'
            . ')'
        );
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS pages ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT, '
            . 'namespace TEXT NOT NULL DEFAULT "default", '
            . 'slug TEXT NOT NULL, title TEXT NOT NULL, content TEXT NOT NULL'
            . ')'
        );
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS marketing_page_wiki_articles ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT, '
            . 'page_id INTEGER NOT NULL, title TEXT NOT NULL, body TEXT NOT NULL'
            . ')'
        );
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS landing_news ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT, '
            . 'page_id INTEGER NOT NULL, title TEXT NOT NULL, body TEXT NOT NULL'
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

    public function testGetPlanBySlug(): void
    {
        $pdo = $this->createPdo();
        $this->seedPlanLimits($pdo);
        $this->createNamespace($pdo, 'ns-1', 'starter');

        $service = new QuotaService($pdo);

        $this->assertSame('starter', $service->getPlanBySlug('test-ns-1'));
    }

    public function testGetPlanBySlugUnknownReturnsFree(): void
    {
        $pdo = $this->createPdo();
        $service = new QuotaService($pdo);

        $this->assertSame('free', $service->getPlanBySlug('nonexistent'));
    }

    public function testRecountBySlugCountsFromRealTables(): void
    {
        $pdo = $this->createPdo();
        $this->seedPlanLimits($pdo);
        $this->createNamespace($pdo, 'ns-1', 'starter');

        // Insert real data
        $pdo->exec("INSERT INTO events (uid, slug, namespace, name) VALUES ('e1', 'event-1', 'test-ns-1', 'Event 1')");
        $pdo->exec("INSERT INTO events (uid, slug, namespace, name) VALUES ('e2', 'event-2', 'test-ns-1', 'Event 2')");
        $pdo->exec("INSERT INTO events (uid, slug, namespace, name) VALUES ('e3', 'event-3', 'other', 'Event 3')");
        $pdo->exec("INSERT INTO teams (uid, sort_order, name, namespace, event_uid) VALUES ('t1', 1, 'Team 1', 'test-ns-1', 'e1')");
        $pdo->exec("INSERT INTO catalogs (uid, sort_order, slug, name, namespace) VALUES ('c1', 1, 'cat-1', 'Catalog 1', 'test-ns-1')");
        $pdo->exec("INSERT INTO questions (catalog_uid, sort_order, question) VALUES ('c1', 1, 'What?')");
        $pdo->exec("INSERT INTO pages (namespace, slug, title, content) VALUES ('test-ns-1', 'page-1', 'Page 1', 'content')");

        $service = new QuotaService($pdo);
        $counts = $service->recountBySlug('test-ns-1');

        $this->assertSame(2, $counts['events']);
        $this->assertSame(1, $counts['teams']);
        $this->assertSame(1, $counts['catalogs']);
        $this->assertSame(1, $counts['questions']);
        $this->assertSame(1, $counts['pages']);
        $this->assertSame(0, $counts['wiki_entries']);
        $this->assertSame(0, $counts['news_articles']);
    }

    public function testRecountBySlugSyncsToQuotaUsage(): void
    {
        $pdo = $this->createPdo();
        $this->seedPlanLimits($pdo);
        $this->createNamespace($pdo, 'ns-1', 'starter');

        $pdo->exec("INSERT INTO events (uid, slug, namespace, name) VALUES ('e1', 'event-1', 'test-ns-1', 'Event 1')");

        $service = new QuotaService($pdo);
        $service->recountBySlug('test-ns-1');

        // Should be synced to namespace_quota_usage
        $this->assertSame(1, $service->getUsage('ns-1', 'events'));
    }

    public function testGetOverviewBySlug(): void
    {
        $pdo = $this->createPdo();
        $this->seedPlanLimits($pdo);
        $this->createNamespace($pdo, 'ns-1', 'starter');

        $pdo->exec("INSERT INTO events (uid, slug, namespace, name) VALUES ('e1', 'event-1', 'test-ns-1', 'Event 1')");

        $service = new QuotaService($pdo);
        $overview = $service->getOverviewBySlug('test-ns-1');

        $this->assertSame('starter', $overview['plan']);
        $this->assertArrayHasKey('limits', $overview);
        $this->assertArrayHasKey('usage', $overview);
        $this->assertSame(1, $overview['usage']['events']);
        $this->assertSame(3, $overview['limits']['events']); // starter: 3 events
    }

    public function testGetOverviewBySlugUnknownNamespace(): void
    {
        $pdo = $this->createPdo();
        $this->seedPlanLimits($pdo);

        $service = new QuotaService($pdo);
        $overview = $service->getOverviewBySlug('nonexistent');

        $this->assertSame('free', $overview['plan']);
        $this->assertSame(0, $overview['usage']['events']);
        $this->assertSame(1, $overview['limits']['events']); // free: 1 event
    }
}
