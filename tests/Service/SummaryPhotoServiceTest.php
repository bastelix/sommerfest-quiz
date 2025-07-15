<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\SummaryPhotoService;
use App\Service\ConfigService;
use PDO;
use Tests\TestCase;

class SummaryPhotoServiceTest extends TestCase
{
    public function testAddAndGetAll(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("CREATE TABLE summary_photos(id INTEGER PRIMARY KEY AUTOINCREMENT,name TEXT,path TEXT,time INTEGER,event_uid TEXT);");
        $pdo->exec('CREATE TABLE config(event_uid TEXT);');
        $cfg = new ConfigService($pdo);
        $cfg->saveConfig(['event_uid' => 'ev1']);
        $svc = new SummaryPhotoService($pdo, $cfg);
        $svc->add('Team', '/photo/img.jpg', 1);
        $rows = $svc->getAll();
        $this->assertCount(1, $rows);
        $this->assertSame('Team', $rows[0]['name']);
    }
}
