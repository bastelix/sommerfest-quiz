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
        $pdo->exec('CREATE TABLE events(uid TEXT PRIMARY KEY, name TEXT NOT NULL, date TEXT, description TEXT);');
        return $pdo;
    }

    public function testSaveAndGet(): void
    {
        $pdo = $this->createPdo();
        $service = new EventService($pdo);
        $data = [
            ['name' => 'Test Event', 'date' => '2025-07-04', 'description' => 'Demo'],
        ];
        $service->saveAll($data);
        $rows = $service->getAll();
        $this->assertCount(1, $rows);
        $this->assertSame('Test Event', $rows[0]['name']);
        $this->assertSame('2025-07-04', $rows[0]['date']);
    }
}
