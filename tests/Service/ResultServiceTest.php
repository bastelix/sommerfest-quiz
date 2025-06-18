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
}
