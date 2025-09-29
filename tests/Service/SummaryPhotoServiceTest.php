<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\SummaryPhotoService;
use App\Service\ConfigService;
use PDO;
use Tests\TestCase;

class SummaryPhotoServiceTest extends TestCase
{
    public function testAddAndGetAll(): void {
        $pdo = $this->createDatabase();
        $cfg = new ConfigService($pdo);
        $cfg->saveConfig(['event_uid' => 'ev1']);
        $svc = new SummaryPhotoService($pdo, $cfg);
        $svc->add('Team', '/photo/img.jpg', 1);
        $rows = $svc->getAll();
        $this->assertCount(1, $rows);
        $this->assertSame('Team', $rows[0]['name']);
    }
}
