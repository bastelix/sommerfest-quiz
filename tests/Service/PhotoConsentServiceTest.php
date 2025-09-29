<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\PhotoConsentService;
use App\Service\ConfigService;
use PDO;
use Tests\TestCase;

class PhotoConsentServiceTest extends TestCase
{
    public function testAddConsentAppendsEntry(): void {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE photo_consents(
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                team TEXT NOT NULL,
                time INTEGER NOT NULL,
                event_uid TEXT
            );
            SQL
        );
        $pdo->exec('CREATE TABLE config(event_uid TEXT);');
        $cfg = new ConfigService($pdo);
        $cfg->saveConfig(['event_uid' => 'e1']);
        $svc = new PhotoConsentService($pdo, $cfg);
        $svc->add('TeamA', 123);
        $svc->add('TeamB', 456);
        $stmt = $pdo->query('SELECT team,time FROM photo_consents ORDER BY id');
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(2, $data);
        $this->assertSame('TeamA', $data[0]['team']);
        $this->assertSame(456, (int)$data[1]['time']);
    }

    public function testGetAllReturnsStoredConsents(): void {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE photo_consents(
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                team TEXT NOT NULL,
                time INTEGER NOT NULL,
                event_uid TEXT
            );
            SQL
        );
        $pdo->exec('CREATE TABLE config(event_uid TEXT);');
        $cfg = new ConfigService($pdo);
        $cfg->saveConfig(['event_uid' => 'e1']);
        $svc = new PhotoConsentService($pdo, $cfg);
        $svc->add('TeamA', 1);
        $svc->add('TeamB', 2);

        $data = $svc->getAll();
        $this->assertCount(2, $data);
        $this->assertSame('TeamA', $data[0]['team']);
        $this->assertSame(2, (int)$data[1]['time']);
    }

    public function testAddStoresEventUid(): void {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE photo_consents(
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                team TEXT NOT NULL,
                time INTEGER NOT NULL,
                event_uid TEXT
            );
            SQL
        );
        $pdo->exec('CREATE TABLE config(event_uid TEXT);');
        $cfg = new ConfigService($pdo);
        $cfg->saveConfig(['event_uid' => 'ev1']);
        $svc = new PhotoConsentService($pdo, $cfg);
        $svc->add('TeamA', 1);
        $uid = $pdo->query('SELECT event_uid FROM photo_consents')->fetchColumn();
        $this->assertSame('ev1', $uid);
    }

    public function testGetAllFiltersByEventUid(): void {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE photo_consents(
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                team TEXT NOT NULL,
                time INTEGER NOT NULL,
                event_uid TEXT
            );
            SQL
        );
        $pdo->exec('CREATE TABLE config(event_uid TEXT);');
        $cfg = new ConfigService($pdo);
        $cfg->saveConfig(['event_uid' => 'ev1']);
        $svc = new PhotoConsentService($pdo, $cfg);
        $pdo->exec("INSERT INTO photo_consents(team,time,event_uid) VALUES('A',1,'ev1')");
        $pdo->exec("INSERT INTO photo_consents(team,time,event_uid) VALUES('B',2,'ev2')");
        $rows = $svc->getAll();
        $this->assertCount(1, $rows);
        $this->assertSame('A', $rows[0]['team']);
    }

    public function testSaveAllReplacesOnlyCurrentEvent(): void {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE photo_consents(
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                team TEXT NOT NULL,
                time INTEGER NOT NULL,
                event_uid TEXT
            );
            SQL
        );
        $pdo->exec('CREATE TABLE config(event_uid TEXT);');
        $cfg = new ConfigService($pdo);
        $cfg->saveConfig(['event_uid' => 'ev1']);
        $svc = new PhotoConsentService($pdo, $cfg);
        $pdo->exec("INSERT INTO photo_consents(team,time,event_uid) VALUES('A',1,'ev1')");
        $pdo->exec("INSERT INTO photo_consents(team,time,event_uid) VALUES('B',2,'ev2')");
        $svc->saveAll([[ 'team' => 'C', 'time' => 3 ]]);
        $countEv2 = (int)$pdo->query("SELECT COUNT(*) FROM photo_consents WHERE event_uid='ev2'")->fetchColumn();
        $this->assertSame(1, $countEv2);
        $rows = $pdo->query("SELECT team FROM photo_consents WHERE event_uid='ev1'")->fetchAll(PDO::FETCH_COLUMN);
        $this->assertCount(1, $rows);
        $this->assertSame('C', $rows[0]);
    }
}
