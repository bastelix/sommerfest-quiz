<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\ConfigService;
use PDO;
use Tests\TestCase;
use Throwable;

class ConfigServiceTest extends TestCase
{
    public function testReadWriteConfig(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE config(
                displayErrorDetails INTEGER,
                loginRequired INTEGER,
                QRRemember INTEGER,
                logoPath TEXT,
                title TEXT,
                backgroundColor TEXT,
                buttonColor TEXT,
                CheckAnswerButton TEXT,
                QRRestrict INTEGER,
                randomNames INTEGER DEFAULT 1,
                competitionMode INTEGER,
                teamResults INTEGER,
                photoUpload INTEGER,
                puzzleWordEnabled INTEGER,
                puzzleWord TEXT,
                puzzleFeedback TEXT,
                inviteText TEXT,
                event_uid TEXT
            );
            SQL
        );
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('CREATE TABLE events(uid TEXT PRIMARY KEY)');
        $pdo->exec('CREATE TABLE active_event(event_uid TEXT PRIMARY KEY REFERENCES events(uid))');
        $pdo->exec("INSERT INTO events(uid) VALUES('ev1')");
        $service = new ConfigService($pdo);
        $data = ['event_uid' => 'ev1', 'pageTitle' => 'Demo', 'QRUser' => false, 'QRRemember' => true];

        $service->saveConfig($data);
        $json = $service->getJson();
        $this->assertNotNull($json);
        $cfg = $service->getConfig();
        $this->assertSame('Demo', $cfg['pageTitle']);
        $this->assertFalse($cfg['QRUser']);
        $this->assertTrue($cfg['QRRemember']);
    }

    public function testGetConfigReturnsEmptyWithoutActiveEvent(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE config(
                pageTitle TEXT,
                event_uid TEXT
            );
            SQL
        );
        $service = new ConfigService($pdo);
        $pdo->exec("INSERT INTO config(pageTitle,event_uid) VALUES('Demo','ev1')");

        $this->assertSame([], $service->getConfig());
    }

    public function testGetJsonReturnsNullIfEmpty(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE config(
                displayErrorDetails INTEGER,
                loginRequired INTEGER,
                QRRemember INTEGER,
                logoPath TEXT,
                title TEXT,
                backgroundColor TEXT,
                buttonColor TEXT,
                CheckAnswerButton TEXT,
                QRRestrict INTEGER,
                randomNames INTEGER DEFAULT 1,
                competitionMode INTEGER,
                teamResults INTEGER,
                photoUpload INTEGER,
                puzzleWordEnabled INTEGER,
                puzzleWord TEXT,
                puzzleFeedback TEXT,
                inviteText TEXT,
                event_uid TEXT
            );
            SQL
        );
        $service = new ConfigService($pdo);

        $this->assertNull($service->getJson());
        $this->assertSame([], $service->getConfig());
    }

    public function testSetActiveEventUidRollsBackOnInsertFailure(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE config(event_uid TEXT PRIMARY KEY)');
        $pdo->exec('CREATE TABLE active_event(event_uid TEXT PRIMARY KEY)');
        $pdo->exec("INSERT INTO active_event(event_uid) VALUES('foo')");
        $pdo->exec(
            "CREATE TRIGGER fail_insert BEFORE INSERT ON active_event " .
            "BEGIN SELECT RAISE(FAIL, 'no insert'); END;"
        );
        $service = new ConfigService($pdo);

        try {
            $service->setActiveEventUid('bar');
            $this->fail('Exception was not thrown');
        } catch (Throwable $e) {
            // expected
        }

        $uid = $pdo->query('SELECT event_uid FROM active_event')->fetchColumn();
        $this->assertSame('foo', $uid);
    }

    public function testSetActiveEventUidIgnoresUnknownEvent(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('CREATE TABLE events(uid TEXT PRIMARY KEY)');
        $pdo->exec('CREATE TABLE active_event(event_uid TEXT PRIMARY KEY REFERENCES events(uid))');
        $pdo->exec("INSERT INTO events(uid) VALUES('ev1')");
        $pdo->exec("INSERT INTO active_event(event_uid) VALUES('ev1')");
        $service = new ConfigService($pdo);

        $service->setActiveEventUid('ev2');

        $uid = $pdo->query('SELECT event_uid FROM active_event')->fetchColumn();
        $this->assertSame('ev1', $uid);
    }

    public function testSetActiveEventUidDoesNotInsertConfigForEmptyEvent(): void
    {
        $pdo = $this->createDatabase();
        $pdo->exec('PRAGMA foreign_keys = ON');
        $service = new ConfigService($pdo);

        $service->setActiveEventUid('');

        $count = (int) $pdo->query('SELECT COUNT(*) FROM config')->fetchColumn();
        $this->assertSame(0, $count);
    }
}
