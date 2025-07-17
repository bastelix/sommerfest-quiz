<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\TeamService;
use App\Service\ConfigService;
use PDO;
use Tests\TestCase;

class TeamServiceTest extends TestCase
{
    public function testSaveAllStoresUid(): void
    {
        $pdo = $this->createDatabase();
        $pdo->exec("INSERT INTO events(uid,name) VALUES('ev1','Event1')");
        $pdo->exec("INSERT INTO config(event_uid) VALUES('ev1')");
        $cfg = new ConfigService($pdo);
        $cfg->setActiveEventUid('ev1');
        $svc = new TeamService($pdo, $cfg);

        $svc->saveAll(['A', 'B']);
        $rows = $pdo->query('SELECT uid,name,event_uid FROM teams ORDER BY sort_order')
            ->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(2, $rows);
        $this->assertSame('A', $rows[0]['name']);
        $this->assertNotEmpty($rows[0]['uid']);
        $this->assertSame('ev1', $rows[0]['event_uid']);
        $this->assertNotSame($rows[0]['uid'], $rows[1]['uid']);
    }

    public function testSaveAllWithoutEvent(): void
    {
        $pdo = $this->createDatabase();
        $cfg = new ConfigService($pdo);
        $cfg->setActiveEventUid('');
        $svc = new TeamService($pdo, $cfg);
        $svc->saveAll(['Team']);
        $row = $pdo->query('SELECT uid,event_uid FROM teams')->fetch(PDO::FETCH_ASSOC);
        $this->assertNotFalse($row);
        $this->assertNotEmpty($row['uid']);
        $this->assertNull($row['event_uid']);
    }
}
