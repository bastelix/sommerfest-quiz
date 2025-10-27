<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\PlayerService;
use App\Support\UsernameBlockedException;
use App\Support\UsernameGuard;
use PDO;
use PHPUnit\Framework\TestCase;

class PlayerServiceTest extends TestCase
{
    public function testSaveUpdatesResultNamesWhenRenamingCaseOnly(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE players('
            . 'event_uid TEXT NOT NULL,'
            . 'player_uid TEXT NOT NULL,'
            . 'player_name TEXT NOT NULL,'
            . 'contact_email TEXT NULL,'
            . 'consent_granted_at TEXT NULL,'
            . 'PRIMARY KEY (event_uid, player_uid)'
            . ');'
        );
        $pdo->exec(
            'CREATE TABLE results('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT,'
            . 'event_uid TEXT,'
            . 'player_uid TEXT,'
            . 'name TEXT NOT NULL'
            . ');'
        );
        $pdo->exec(
            'CREATE TABLE question_results('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT,'
            . 'event_uid TEXT,'
            . 'player_uid TEXT,'
            . 'name TEXT NOT NULL'
            . ');'
        );

        $pdo->prepare('INSERT INTO players(event_uid, player_uid, player_name) VALUES(?,?,?)')
            ->execute(['event-1', 'player-1', 'ALICE']);
        $pdo->prepare('INSERT INTO results(event_uid, name) VALUES(?, ?)')
            ->execute(['event-1', 'ALICE']);
        $pdo->prepare('INSERT INTO question_results(event_uid, name) VALUES(?, ?)')
            ->execute(['event-1', 'ALICE']);

        $service = new PlayerService($pdo);
        $service->save('event-1', 'Alice', 'player-1');

        $playerName = $pdo->query('SELECT player_name FROM players WHERE event_uid = "event-1" AND player_uid = "player-1"')
            ->fetchColumn();
        $resultName = $pdo->query('SELECT name FROM results WHERE event_uid = "event-1"')
            ->fetchColumn();
        $questionResultName = $pdo->query('SELECT name FROM question_results WHERE event_uid = "event-1"')
            ->fetchColumn();

        self::assertSame('Alice', $playerName);
        self::assertSame('Alice', $resultName);
        self::assertSame('Alice', $questionResultName);
    }

    public function testSaveRejectsBlockedNames(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE players('
            . 'event_uid TEXT NOT NULL,'
            . 'player_uid TEXT NOT NULL,'
            . 'player_name TEXT NOT NULL,'
            . 'contact_email TEXT NULL,'
            . 'consent_granted_at TEXT NULL,'
            . 'PRIMARY KEY (event_uid, player_uid)'
            . ');'
        );
        $pdo->exec('CREATE TABLE results(id INTEGER PRIMARY KEY AUTOINCREMENT, event_uid TEXT, player_uid TEXT, name TEXT NOT NULL);');
        $pdo->exec('CREATE TABLE question_results(id INTEGER PRIMARY KEY AUTOINCREMENT, event_uid TEXT, player_uid TEXT, name TEXT NOT NULL);');

        $guard = new UsernameGuard(['usernames' => ['verboten']]);
        $service = new PlayerService($pdo, $guard);

        $this->expectException(UsernameBlockedException::class);

        $service->save('event-1', 'Verboten', 'player-1');
    }
}
