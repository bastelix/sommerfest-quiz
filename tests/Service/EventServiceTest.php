<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\EventService;
use App\Service\TenantService;
use PDO;
use Tests\TestCase;

class EventServiceTest extends TestCase
{
    private function createPdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE events(' .
            'uid TEXT PRIMARY KEY, name TEXT NOT NULL, start_date TEXT, end_date TEXT, description TEXT, published INTEGER' .
            ');'
        );
        $pdo->exec('CREATE TABLE config(id INTEGER PRIMARY KEY AUTOINCREMENT, event_uid TEXT);');
        $pdo->exec('CREATE TABLE tenants(uid TEXT, subdomain TEXT, plan TEXT, custom_limits TEXT, plan_started_at TEXT, plan_expires_at TEXT);');
        return $pdo;
    }

    public function testSaveAndGet(): void
    {
        $pdo = $this->createPdo();
        $service = new EventService($pdo);
        $data = [
            [
                'name' => 'Test Event',
                'start_date' => '2025-07-04T18:00',
                'end_date' => '2025-07-04T23:00',
                'description' => 'Demo',
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
    }

    public function testGetAllFormatsDates(): void
    {
        $pdo = $this->createPdo();
        $pdo->exec(
            "INSERT INTO events(uid,name,start_date,end_date) " .
            "VALUES('a1','Evt','2025-07-04 18:00:00+00','2025-07-04 20:00:00+00')"
        );
        $service = new EventService($pdo);
        $rows = $service->getAll();
        $this->assertSame('2025-07-04T18:00', $rows[0]['start_date']);
        $this->assertSame('2025-07-04T20:00', $rows[0]['end_date']);
    }

    public function testGetByUid(): void
    {
        $pdo = $this->createPdo();
        $service = new EventService($pdo);
        $uid = 'uid123';
        $service->saveAll([[ 'uid' => $uid, 'name' => 'Eins' ]]);
        $row = $service->getByUid($uid);
        $this->assertNotNull($row);
        $this->assertSame('Eins', $row['name']);
    }

    public function testActiveEventIsSetWhenSingleEvent(): void
    {
        $pdo = $this->createPdo();
        $svc = new EventService($pdo);
        $svc->saveAll([[ 'uid' => 'e1', 'name' => 'Single' ]]);
        $uid = $pdo->query('SELECT event_uid FROM active_event')->fetchColumn();
        $this->assertSame('e1', $uid);
    }

    public function testActiveEventNotTouchedWithMultipleEvents(): void
    {
        $pdo = $this->createPdo();
        $svc = new EventService($pdo);
        $svc->saveAll([
            ['uid' => 'e1', 'name' => 'One'],
            ['uid' => 'e2', 'name' => 'Two'],
        ]);
        $count = (int) $pdo->query('SELECT COUNT(*) FROM active_event')->fetchColumn();
        $this->assertSame(0, $count);
    }

    public function testSaveAllRespectsStarterLimit(): void
    {
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

    public function testSaveAllRespectsStandardLimit(): void
    {
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

    public function testCustomLimitOverridesPlan(): void
    {
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
}
