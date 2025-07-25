<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\EventService;
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
            'uid TEXT PRIMARY KEY, name TEXT NOT NULL, start_date TEXT, end_date TEXT, description TEXT' .
            ');'
        );
        $pdo->exec('CREATE TABLE config(id INTEGER PRIMARY KEY AUTOINCREMENT, event_uid TEXT);');
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
}
