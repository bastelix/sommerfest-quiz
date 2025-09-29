<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\TeamService;
use App\Service\ConfigService;
use App\Service\TenantService;
use PDO;
use Tests\TestCase;

class TeamServiceTest extends TestCase
{
    public function testSaveAllStoresUid(): void {
        $pdo = $this->createDatabase();
        $pdo->exec("INSERT INTO events(uid,slug,name) VALUES('ev1','ev1','Event1')");
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

    public function testSaveAllWithoutEvent(): void {
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

    public function testGetAllWithoutActiveEventReturnsEmpty(): void {
        $pdo = $this->createDatabase();
        $pdo->exec("INSERT INTO events(uid,slug,name) VALUES('e1','e1','Event1')");
        $pdo->exec("INSERT INTO events(uid,slug,name) VALUES('e2','e2','Event2')");
        $pdo->exec("INSERT INTO teams(uid,event_uid,sort_order,name) VALUES('t1','e1',1,'T1')");
        $pdo->exec("INSERT INTO teams(uid,event_uid,sort_order,name) VALUES('t2','e2',2,'T2')");
        $cfg = new ConfigService($pdo);
        $cfg->setActiveEventUid('');
        $svc = new TeamService($pdo, $cfg);
        $this->assertSame([], $svc->getAll());
    }

    public function testSaveAllRespectsTeamLimit(): void {
        $pdo = $this->createDatabase();
        $pdo->exec("INSERT INTO events(uid,slug,name) VALUES('e1','e1','Event1')");
        $pdo->exec("INSERT INTO config(event_uid) VALUES('e1')");
        $cfg = new ConfigService($pdo);
        $cfg->setActiveEventUid('e1');

        $pdo->exec("INSERT INTO tenants(uid, subdomain, plan) VALUES('t1','sub1','starter')");
        $tenantSvc = new TenantService($pdo);
        $svc = new TeamService($pdo, $cfg, $tenantSvc, 'sub1');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('max-teams-exceeded');

        $svc->saveAll(['A', 'B', 'C', 'D', 'E', 'F']);
    }

    public function testSaveAllRespectsStandardTeamLimit(): void {
        $pdo = $this->createDatabase();
        $pdo->exec("INSERT INTO events(uid,slug,name) VALUES('e1','e1','Event1')");
        $pdo->exec("INSERT INTO config(event_uid) VALUES('e1')");
        $cfg = new ConfigService($pdo);
        $cfg->setActiveEventUid('e1');

        $pdo->exec("INSERT INTO tenants(uid, subdomain, plan) VALUES('t1','sub1','standard')");
        $tenantSvc = new TenantService($pdo);
        $svc = new TeamService($pdo, $cfg, $tenantSvc, 'sub1');

        $teams = [];
        for ($i = 0; $i < 11; $i++) {
            $teams[] = 'T' . $i;
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('max-teams-exceeded');

        $svc->saveAll($teams);
    }

    public function testCustomLimitOverridesTeamLimit(): void {
        $pdo = $this->createDatabase();
        $pdo->exec("INSERT INTO events(uid,slug,name) VALUES('e1','e1','Event1')");
        $pdo->exec("INSERT INTO config(event_uid) VALUES('e1')");
        $cfg = new ConfigService($pdo);
        $cfg->setActiveEventUid('e1');

        $pdo->exec(
            "INSERT INTO tenants(uid, subdomain, plan, custom_limits) " .
            "VALUES('t2','sub2','starter','{\"maxTeamsPerEvent\":6}')"
        );
        $tenantSvc = new TenantService($pdo);
        $svc = new TeamService($pdo, $cfg, $tenantSvc, 'sub2');

        $svc->saveAll(['A', 'B', 'C', 'D', 'E', 'F']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('max-teams-exceeded');
        $svc->saveAll(['A', 'B', 'C', 'D', 'E', 'F', 'G']);
    }
}
