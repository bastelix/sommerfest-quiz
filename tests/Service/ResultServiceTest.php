<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\ResultService;
use App\Service\ConfigService;
use PDO;
use Tests\TestCase;

class ResultServiceTest extends TestCase
{
    public function testAddIncrementsAttemptForSameCatalog(): void
    {
        $pdo = $this->createMigratedPdo();
        $cfg = new ConfigService($pdo);
        $service = new ResultService($pdo, $cfg);

        $first = $service->add(['name' => 'TeamA', 'catalog' => 'cat1']);
        $this->assertSame(1, $first['attempt']);

        $second = $service->add(['name' => 'TeamA', 'catalog' => 'cat1']);
        $this->assertSame(2, $second['attempt']);
    }

    public function testAddDoesNotIncrementAcrossCatalogs(): void
    {
        $pdo = $this->createMigratedPdo();
        $cfg = new ConfigService($pdo);
        $service = new ResultService($pdo, $cfg);

        $first = $service->add(['name' => 'TeamA', 'catalog' => 'cat1']);
        $this->assertSame(1, $first['attempt']);

        $other = $service->add(['name' => 'TeamA', 'catalog' => 'cat2']);
        $this->assertSame(1, $other['attempt']);
    }

    public function testMarkPuzzleUpdatesEntry(): void
    {
        $pdo = $this->createMigratedPdo();
        $cfg = new ConfigService($pdo);
        $service = new ResultService($pdo, $cfg);

        $service->add(['name' => 'TeamA', 'catalog' => 'cat1']);
        $ts = time();
        $res = $service->markPuzzle('TeamA', 'cat1', $ts);
        $this->assertTrue($res);
        $stmt = $pdo->query('SELECT puzzleTime FROM results');
        $this->assertSame($ts, (int)$stmt->fetchColumn());
    }

    public function testMarkPuzzleReturnsTrueIfAlreadySolved(): void
    {
        $pdo = $this->createMigratedPdo();
        $cfg = new ConfigService($pdo);
        $service = new ResultService($pdo, $cfg);

        $service->add(['name' => 'TeamA', 'catalog' => 'cat1', 'puzzleTime' => 123]);
        $res = $service->markPuzzle('TeamA', 'cat1', 456);
        $this->assertTrue($res);
        $stmt = $pdo->query('SELECT puzzleTime FROM results');
        $this->assertSame('123', $stmt->fetchColumn());
    }

    public function testSetPhotoUpdatesEntry(): void
    {
        $pdo = $this->createMigratedPdo();
        $cfg = new ConfigService($pdo);
        $service = new ResultService($pdo, $cfg);

        $service->add(['name' => 'TeamA', 'catalog' => 'cat1']);
        $service->setPhoto('TeamA', 'cat1', '/photo/test.jpg');
        $stmt = $pdo->query('SELECT photo FROM results');
        $this->assertSame('/photo/test.jpg', $stmt->fetchColumn());
    }

    public function testAddStoresQuestionResults(): void
    {
        $pdo = $this->createMigratedPdo();
        $pdo->exec("INSERT INTO catalogs(uid,sort_order,slug,file,name) VALUES('u1',1,'cat1','c.json','C')");
        $pdo->exec("INSERT INTO questions(catalog_uid,sort_order,type,prompt) VALUES('u1',1,'text','Q1')");
        $pdo->exec("INSERT INTO questions(catalog_uid,sort_order,type,prompt) VALUES('u1',2,'text','Q2')");

        $cfg = new ConfigService($pdo);
        $service = new ResultService($pdo, $cfg);
        $service->add(['name' => 'Team', 'catalog' => 'cat1', 'correct' => 1, 'total' => 2, 'wrong' => [2]]);

        $stmt = $pdo->query('SELECT question_id, correct FROM question_results ORDER BY id');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(2, $rows);
        $this->assertSame('1', (string)$rows[0]['question_id']);
        $this->assertSame('1', (string)$rows[0]['correct']);
        $this->assertSame('2', (string)$rows[1]['question_id']);
        $this->assertSame('0', (string)$rows[1]['correct']);
    }

    public function testClearRemovesResultsAndQuestionResults(): void
    {
        $pdo = $this->createMigratedPdo();
        $cfg = new ConfigService($pdo);
        $service = new ResultService($pdo, $cfg);
        $service->add([ 'name' => 'Team', 'catalog' => 'cat1', 'correct' => 1, 'total' => 1 ]);
        $service->clear();
        $resCount = (int) $pdo->query('SELECT COUNT(*) FROM results')->fetchColumn();
        $qresCount = (int) $pdo->query('SELECT COUNT(*) FROM question_results')->fetchColumn();
        $this->assertSame(0, $resCount);
        $this->assertSame(0, $qresCount);
    }

    public function testPhotoAttachedAfterEventUidChange(): void
    {
        $pdo = $this->createMigratedPdo();
        $pdo->exec("INSERT INTO config(event_uid) VALUES('ev1')");
        $pdo->exec("INSERT INTO catalogs(uid,sort_order,slug,file,name) VALUES('u',1,'cat1','c.json','C')");
        $pdo->exec("INSERT INTO questions(catalog_uid,sort_order,type,prompt) VALUES('u',1,'text','Q1')");
        // table question_results already created by migration
        $cfg = new ConfigService($pdo);
        $service = new ResultService($pdo, $cfg);

        $service->add(['name' => 'TeamA', 'catalog' => 'cat1']);

        $cfg->setActiveEventUid('ev2');

        $service->setPhoto('TeamA', 'cat1', '/photo/img.jpg');

        $stmt = $pdo->query('SELECT photo, event_uid FROM results');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('/photo/img.jpg', $row['photo']);
        $this->assertSame('ev1', $row['event_uid']);
    }
}
