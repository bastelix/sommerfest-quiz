<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\ResultService;
use PDO;
use Tests\TestCase;

class ResultServiceTest extends TestCase
{
    public function testAddIncrementsAttemptForSameCatalog(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE results(id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, catalog TEXT NOT NULL, attempt INTEGER NOT NULL, correct INTEGER NOT NULL, total INTEGER NOT NULL, time INTEGER NOT NULL, puzzleTime INTEGER, photo TEXT);');
        $service = new ResultService($pdo);

        $first = $service->add(['name' => 'TeamA', 'catalog' => 'cat1']);
        $this->assertSame(1, $first['attempt']);

        $second = $service->add(['name' => 'TeamA', 'catalog' => 'cat1']);
        $this->assertSame(2, $second['attempt']);
    }

    public function testAddDoesNotIncrementAcrossCatalogs(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE results(id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, catalog TEXT NOT NULL, attempt INTEGER NOT NULL, correct INTEGER NOT NULL, total INTEGER NOT NULL, time INTEGER NOT NULL, puzzleTime INTEGER, photo TEXT);');
        $service = new ResultService($pdo);

        $first = $service->add(['name' => 'TeamA', 'catalog' => 'cat1']);
        $this->assertSame(1, $first['attempt']);

        $other = $service->add(['name' => 'TeamA', 'catalog' => 'cat2']);
        $this->assertSame(1, $other['attempt']);
    }

    public function testMarkPuzzleUpdatesEntry(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE results(id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, catalog TEXT NOT NULL, attempt INTEGER NOT NULL, correct INTEGER NOT NULL, total INTEGER NOT NULL, time INTEGER NOT NULL, puzzleTime INTEGER, photo TEXT);');
        $service = new ResultService($pdo);

        $service->add(['name' => 'TeamA', 'catalog' => 'cat1']);
        $ts = time();
        $service->markPuzzle('TeamA', 'cat1', $ts);
        $stmt = $pdo->query('SELECT puzzleTime FROM results');
        $this->assertSame($ts, (int)$stmt->fetchColumn());
    }

    public function testSetPhotoUpdatesEntry(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE results(id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, catalog TEXT NOT NULL, attempt INTEGER NOT NULL, correct INTEGER NOT NULL, total INTEGER NOT NULL, time INTEGER NOT NULL, puzzleTime INTEGER, photo TEXT);');
        $service = new ResultService($pdo);

        $service->add(['name' => 'TeamA', 'catalog' => 'cat1']);
        $service->setPhoto('TeamA', 'cat1', '/photo/test.jpg');
        $stmt = $pdo->query('SELECT photo FROM results');
        $this->assertSame('/photo/test.jpg', $stmt->fetchColumn());
    }

    public function testAddStoresQuestionResults(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE results(id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, catalog TEXT NOT NULL, attempt INTEGER NOT NULL, correct INTEGER NOT NULL, total INTEGER NOT NULL, time INTEGER NOT NULL, puzzleTime INTEGER, photo TEXT);');
        $pdo->exec('CREATE TABLE question_results(id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, catalog TEXT NOT NULL, question_id INTEGER NOT NULL, attempt INTEGER NOT NULL, correct INTEGER NOT NULL);');
        $pdo->exec('CREATE TABLE catalogs(uid TEXT PRIMARY KEY, sort_order INTEGER, slug TEXT, file TEXT, name TEXT);');
        $pdo->exec('CREATE TABLE questions(id INTEGER PRIMARY KEY AUTOINCREMENT, catalog_uid TEXT NOT NULL, sort_order INTEGER, type TEXT NOT NULL, prompt TEXT NOT NULL, options TEXT, answers TEXT, terms TEXT, items TEXT);');
        $pdo->exec("INSERT INTO catalogs(uid,sort_order,slug,file,name) VALUES('u1',1,'cat1','c.json','C')");
        $pdo->exec("INSERT INTO questions(catalog_uid,sort_order,type,prompt) VALUES('u1',1,'text','Q1')");
        $pdo->exec("INSERT INTO questions(catalog_uid,sort_order,type,prompt) VALUES('u1',2,'text','Q2')");

        $service = new ResultService($pdo);
        $service->add(['name' => 'Team', 'catalog' => 'cat1', 'correct' => 1, 'total' => 2, 'wrong' => [2]]);

        $stmt = $pdo->query('SELECT question_id, correct FROM question_results ORDER BY id');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(2, $rows);
        $this->assertSame('1', (string)$rows[0]['question_id']);
        $this->assertSame('1', (string)$rows[0]['correct']);
        $this->assertSame('2', (string)$rows[1]['question_id']);
        $this->assertSame('0', (string)$rows[1]['correct']);
    }
}
