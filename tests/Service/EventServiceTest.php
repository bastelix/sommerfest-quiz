<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\EventService;
use App\Service\TenantService;
use PDO;
use Tests\TestCase;

class EventServiceTest extends TestCase
{
    private function createPdo(): PDO {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE events('
            . 'uid TEXT PRIMARY KEY, '
            . 'slug TEXT UNIQUE NOT NULL, '
            . 'name TEXT NOT NULL, '
            . 'start_date TEXT, '
            . 'end_date TEXT, '
            . 'description TEXT, '
            . 'published INTEGER, '
            . 'sort_order INTEGER DEFAULT 0'
            . ');'
        );
        $pdo->exec('CREATE TABLE config(id INTEGER PRIMARY KEY AUTOINCREMENT, event_uid TEXT);');
        $pdo->exec(
            'CREATE TABLE tenants('
            . 'uid TEXT, '
            . 'subdomain TEXT, '
            . 'plan TEXT, '
            . 'custom_limits TEXT, '
            . 'plan_started_at TEXT, '
            . 'plan_expires_at TEXT, '
            . 'stripe_customer_id TEXT, '
            . 'stripe_subscription_id TEXT, '
            . 'stripe_price_id TEXT, '
            . 'stripe_status TEXT, '
            . 'stripe_current_period_end TEXT, '
            . 'stripe_cancel_at_period_end INTEGER'
            . ');'
        );
        return $pdo;
    }

    public function testSaveAndGet(): void {
        $pdo = $this->createPdo();
        $service = new EventService($pdo);
        $data = [
            [
                'name' => 'Test Event',
                'start_date' => '2025-07-04T18:00',
                'end_date' => '2025-07-04T23:00',
                'description' => 'Demo',
                'published' => true,
            ],
        ];
        $service->saveAll($data);
        $count = (int) $pdo->query('SELECT COUNT(*) FROM config')->fetchColumn();
        $this->assertSame(1, $count);
        $rows = $service->getAll();
        $this->assertCount(1, $rows);
        $this->assertSame('Test Event', $rows[0]['name']);
        $this->assertSame('2025-07-04T18:00', $rows[0]['start_date']);
        $this->assertSame('2025-07-04T23:00', $rows[0]['end_date']);
        $this->assertTrue($rows[0]['published']);
    }

    public function testGetAllFormatsDates(): void {
        $pdo = $this->createPdo();
        $pdo->exec(
            "INSERT INTO events(uid,slug,name,start_date,end_date) " .
            "VALUES('a1','a1','Evt','2025-07-04 18:00:00+00','2025-07-04 20:00:00+00')"
        );
        $service = new EventService($pdo);
        $rows = $service->getAll();
        $this->assertSame('2025-07-04T18:00', $rows[0]['start_date']);
        $this->assertSame('2025-07-04T20:00', $rows[0]['end_date']);
    }

    public function testGetByUid(): void {
        $pdo = $this->createPdo();
        $service = new EventService($pdo);
        $uid = 'uid123';
        $service->saveAll([[ 'uid' => $uid, 'name' => 'Eins' ]]);
        $row = $service->getByUid($uid);
        $this->assertNotNull($row);
        $this->assertSame('Eins', $row['name']);
    }

    public function testActiveEventIsSetWhenSingleEvent(): void {
        $pdo = $this->createPdo();
        $svc = new EventService($pdo);
        $svc->saveAll([[ 'uid' => 'e1', 'name' => 'Single' ]]);
        $uid = $pdo->query('SELECT event_uid FROM active_event')->fetchColumn();
        $this->assertSame('e1', $uid);
    }

    public function testActiveEventNotTouchedWithMultipleEvents(): void {
        $pdo = $this->createPdo();
        $svc = new EventService($pdo);
        $svc->saveAll([
            ['uid' => 'e1', 'name' => 'One'],
            ['uid' => 'e2', 'name' => 'Two'],
        ]);
        $count = (int) $pdo->query('SELECT COUNT(*) FROM active_event')->fetchColumn();
        $this->assertSame(0, $count);
    }

    public function testSaveAllRespectsStarterLimit(): void {
        $pdo = $this->createPdo();
        $pdo->exec("INSERT INTO tenants(uid, subdomain, plan) VALUES('t1','sub1','starter')");
        $tenantSvc = new TenantService($pdo);
        $svc = new EventService($pdo, null, $tenantSvc, 'sub1');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('max-events-exceeded');

        $svc->saveAll([
            ['name' => 'One'],
            ['name' => 'Two'],
        ]);
    }

    public function testSaveAllRespectsStandardLimit(): void {
        $pdo = $this->createPdo();
        $pdo->exec("INSERT INTO tenants(uid, subdomain, plan) VALUES('t2','sub2','standard')");
        $tenantSvc = new TenantService($pdo);
        $svc = new EventService($pdo, null, $tenantSvc, 'sub2');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('max-events-exceeded');

        $svc->saveAll([
            ['name' => 'A'],
            ['name' => 'B'],
            ['name' => 'C'],
            ['name' => 'D'],
        ]);
    }

    public function testCustomLimitOverridesPlan(): void {
        $pdo = $this->createPdo();
        $pdo->exec(
            "INSERT INTO tenants(uid, subdomain, plan, custom_limits) " .
            "VALUES('t3','sub3','starter','{\"maxEvents\":2}')"
        );
        $tenantSvc = new TenantService($pdo);
        $svc = new EventService($pdo, null, $tenantSvc, 'sub3');

        $svc->saveAll([
            ['name' => 'One'],
            ['name' => 'Two'],
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('max-events-exceeded');

        $svc->saveAll([
            ['name' => 'One'],
            ['name' => 'Two'],
            ['name' => 'Three'],
        ]);
    }

    public function testSaveAllAllowsReplacementAtLimit(): void {
        $pdo = $this->createPdo();
        $pdo->exec("INSERT INTO tenants(uid, subdomain, plan) VALUES('t4','sub4','professional')");
        $tenantSvc = new TenantService($pdo);
        $svc = new EventService($pdo, null, $tenantSvc, 'sub4');

        $initial = [];
        for ($i = 0; $i < 20; $i++) {
            $initial[] = ['uid' => 'e' . $i, 'name' => 'E' . $i];
        }
        $svc->saveAll($initial);

        $events = array_slice($initial, 0, 19);
        $events[] = ['name' => 'New'];

        $svc->saveAll($events);

        $count = (int) $pdo->query('SELECT COUNT(*) FROM events')->fetchColumn();
        $this->assertSame(20, $count);
    }

    public function testDraftEventsAreIgnoredOnSave(): void {
        $pdo = $this->createPdo();
        $service = new EventService($pdo);

        $service->saveAll([
            ['uid' => 'draft-1', 'name' => '__draft__draft-1', 'draft' => true],
            ['uid' => 'valid-1', 'name' => '  Valid Name  '],
        ]);

        $rows = $pdo->query('SELECT uid, name FROM events ORDER BY sort_order')->fetchAll(PDO::FETCH_ASSOC);
        $this->assertSame([
            ['uid' => 'valid-1', 'name' => 'Valid Name'],
        ], $rows);
    }

    public function testDraftOnlyPayloadKeepsExistingEvents(): void {
        $pdo = $this->createPdo();
        $service = new EventService($pdo);

        $service->saveAll([
            ['uid' => 'persist', 'name' => 'Keep Me'],
        ]);

        $service->saveAll([
            ['uid' => 'draft-1', 'name' => '__draft__draft-1', 'draft' => true],
            ['uid' => 'draft-2', 'name' => '  ', 'draft' => true],
        ]);

        $rows = $pdo->query('SELECT uid, name FROM events ORDER BY sort_order')->fetchAll(PDO::FETCH_ASSOC);
        $this->assertSame([
            ['uid' => 'persist', 'name' => 'Keep Me'],
        ], $rows);
    }

    public function testGetBySlugAndUidBySlug(): void {
        $pdo = $this->createPdo();
        $service = new EventService($pdo);
        $uid = str_repeat('a', 32);
        $service->saveAll([
            ['uid' => $uid, 'slug' => 'summer', 'name' => 'Sommer']
        ]);
        $row = $service->getBySlug('summer');
        $this->assertNotNull($row);
        $this->assertSame('Sommer', $row['name']);
        $foundUid = $service->uidBySlug('summer');
        $this->assertSame($uid, $foundUid);
    }
}
