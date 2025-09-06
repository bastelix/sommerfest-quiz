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
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE results(
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                catalog TEXT NOT NULL,
                attempt INTEGER NOT NULL,
                correct INTEGER NOT NULL,
                total INTEGER NOT NULL,
                time INTEGER NOT NULL,
                puzzleTime INTEGER,
                photo TEXT,
                event_uid TEXT
            );
            SQL
        );
        $pdo->exec(
            'CREATE TABLE catalogs(' .
            'uid TEXT PRIMARY KEY, sort_order INTEGER, slug TEXT, file TEXT, name TEXT, event_uid TEXT' .
            ');'
        );
        $pdo->exec('CREATE TABLE config(event_uid TEXT);');
        $cfg = new ConfigService($pdo);
        $service = new ResultService($pdo, $cfg);

        $first = $service->add(['name' => 'TeamA', 'catalog' => 'cat1']);
        $this->assertSame(1, $first['attempt']);

        $second = $service->add(['name' => 'TeamA', 'catalog' => 'cat1']);
        $this->assertSame(2, $second['attempt']);
    }

    public function testAddDoesNotIncrementAcrossCatalogs(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE results(
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                catalog TEXT NOT NULL,
                attempt INTEGER NOT NULL,
                correct INTEGER NOT NULL,
                total INTEGER NOT NULL,
                time INTEGER NOT NULL,
                puzzleTime INTEGER,
                photo TEXT,
                event_uid TEXT
            );
            SQL
        );
        $pdo->exec(
            'CREATE TABLE catalogs(' .
            'uid TEXT PRIMARY KEY, sort_order INTEGER, slug TEXT, file TEXT, name TEXT, event_uid TEXT' .
            ');'
        );
        $pdo->exec('CREATE TABLE config(event_uid TEXT);');
        $cfg = new ConfigService($pdo);
        $service = new ResultService($pdo, $cfg);

        $first = $service->add(['name' => 'TeamA', 'catalog' => 'cat1']);
        $this->assertSame(1, $first['attempt']);

        $other = $service->add(['name' => 'TeamA', 'catalog' => 'cat2']);
        $this->assertSame(1, $other['attempt']);
    }

    public function testMarkPuzzleUpdatesEntry(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE results(
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                catalog TEXT NOT NULL,
                attempt INTEGER NOT NULL,
                correct INTEGER NOT NULL,
                total INTEGER NOT NULL,
                time INTEGER NOT NULL,
                puzzleTime INTEGER,
                photo TEXT,
                event_uid TEXT
            );
            SQL
        );
        $pdo->exec(
            'CREATE TABLE catalogs(' .
            'uid TEXT PRIMARY KEY, sort_order INTEGER, slug TEXT, file TEXT, name TEXT, event_uid TEXT' .
            ');'
        );
        $pdo->exec('CREATE TABLE config(event_uid TEXT);');
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
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE results(
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                catalog TEXT NOT NULL,
                attempt INTEGER NOT NULL,
                correct INTEGER NOT NULL,
                total INTEGER NOT NULL,
                time INTEGER NOT NULL,
                puzzleTime INTEGER,
                photo TEXT,
                event_uid TEXT
            );
            SQL
        );
        $pdo->exec(
            'CREATE TABLE catalogs(' .
            'uid TEXT PRIMARY KEY, sort_order INTEGER, slug TEXT, file TEXT, name TEXT, event_uid TEXT' .
            ');'
        );
        $pdo->exec('CREATE TABLE config(event_uid TEXT);');
        $cfg = new ConfigService($pdo);
        $service = new ResultService($pdo, $cfg);

        $service->add(['name' => 'TeamA', 'catalog' => 'cat1', 'puzzleTime' => 123]);
        $res = $service->markPuzzle('TeamA', 'cat1', 456);
        $this->assertTrue($res);
        $stmt = $pdo->query('SELECT puzzleTime FROM results');
        $this->assertSame(123, (int)$stmt->fetchColumn());
    }

    public function testSetPhotoUpdatesEntry(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE results(
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                catalog TEXT NOT NULL,
                attempt INTEGER NOT NULL,
                correct INTEGER NOT NULL,
                total INTEGER NOT NULL,
                time INTEGER NOT NULL,
                puzzleTime INTEGER,
                photo TEXT,
                event_uid TEXT
            );
            SQL
        );
        $pdo->exec(
            'CREATE TABLE catalogs(' .
            'uid TEXT PRIMARY KEY, sort_order INTEGER, slug TEXT, file TEXT, name TEXT, event_uid TEXT' .
            ');'
        );
        $pdo->exec('CREATE TABLE config(event_uid TEXT);');
        $cfg = new ConfigService($pdo);
        $service = new ResultService($pdo, $cfg);

        $service->add(['name' => 'TeamA', 'catalog' => 'cat1']);
        $service->setPhoto('TeamA', 'cat1', '/photo/test.jpg');
        $stmt = $pdo->query('SELECT photo FROM results');
        $this->assertSame('/photo/test.jpg', $stmt->fetchColumn());
    }

    public function testAddStoresQuestionResults(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE results(
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                catalog TEXT NOT NULL,
                attempt INTEGER NOT NULL,
                correct INTEGER NOT NULL,
                total INTEGER NOT NULL,
                time INTEGER NOT NULL,
                puzzleTime INTEGER,
                photo TEXT,
                event_uid TEXT
            );
            SQL
        );
        $pdo->exec(
            'CREATE TABLE catalogs(' .
            'uid TEXT PRIMARY KEY, sort_order INTEGER, slug TEXT, file TEXT, name TEXT, event_uid TEXT' .
            ');'
        );
        $pdo->exec('CREATE TABLE config(event_uid TEXT);');
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE question_results(
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                catalog TEXT NOT NULL,
                question_id INTEGER NOT NULL,
                attempt INTEGER NOT NULL,
                correct INTEGER NOT NULL,
                answer_text TEXT,
                photo TEXT,
                consent INTEGER,
                event_uid TEXT
            );
            SQL
        );
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE questions(
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                catalog_uid TEXT NOT NULL,
                sort_order INTEGER,
                type TEXT NOT NULL,
                prompt TEXT NOT NULL,
                options TEXT,
                answers TEXT,
                terms TEXT,
                items TEXT,
                cards TEXT,
                right_label TEXT,
                left_label TEXT
            );
            SQL
        );
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
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE results(
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                catalog TEXT NOT NULL,
                attempt INTEGER NOT NULL,
                correct INTEGER NOT NULL,
                total INTEGER NOT NULL,
                time INTEGER NOT NULL,
                puzzleTime INTEGER,
                photo TEXT,
                event_uid TEXT
            );
            SQL
        );
        $pdo->exec(
            'CREATE TABLE catalogs(' .
            'uid TEXT PRIMARY KEY, sort_order INTEGER, slug TEXT, file TEXT, name TEXT, event_uid TEXT' .
            ');'
        );
        $pdo->exec('CREATE TABLE config(event_uid TEXT);');
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE question_results(
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                catalog TEXT NOT NULL,
                question_id INTEGER NOT NULL,
                attempt INTEGER NOT NULL,
                correct INTEGER NOT NULL,
                answer_text TEXT,
                photo TEXT,
                consent INTEGER,
                event_uid TEXT
            );
            SQL
        );
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
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE results(
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                catalog TEXT NOT NULL,
                attempt INTEGER NOT NULL,
                correct INTEGER NOT NULL,
                total INTEGER NOT NULL,
                time INTEGER NOT NULL,
                puzzleTime INTEGER,
                photo TEXT,
                event_uid TEXT
            );
            SQL
        );
        $pdo->exec('CREATE TABLE config(event_uid TEXT);');
        $pdo->exec("INSERT INTO config(event_uid) VALUES('ev1')");
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE catalogs(
                uid TEXT PRIMARY KEY,
                sort_order INTEGER,
                slug TEXT,
                file TEXT,
                name TEXT,
                event_uid TEXT
            );
            SQL
        );
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE questions(
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                catalog_uid TEXT NOT NULL,
                sort_order INTEGER,
                type TEXT NOT NULL,
                prompt TEXT NOT NULL,
                options TEXT,
                answers TEXT,
                terms TEXT,
                items TEXT,
                cards TEXT,
                right_label TEXT,
                left_label TEXT
            );
            SQL
        );
        $pdo->exec("INSERT INTO catalogs(uid,sort_order,slug,file,name) VALUES('u',1,'cat1','c.json','C')");
        $pdo->exec("INSERT INTO questions(catalog_uid,sort_order,type,prompt) VALUES('u',1,'text','Q1')");
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE question_results(
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                catalog TEXT NOT NULL,
                question_id INTEGER NOT NULL,
                attempt INTEGER NOT NULL,
                correct INTEGER NOT NULL,
                answer_text TEXT,
                photo TEXT,
                consent INTEGER,
                event_uid TEXT
            );
            SQL
        );
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

    public function testGetAllAssociatesCatalogNameWithEvent(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE results(
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                catalog TEXT NOT NULL,
                attempt INTEGER NOT NULL,
                correct INTEGER NOT NULL,
                total INTEGER NOT NULL,
                time INTEGER NOT NULL,
                puzzleTime INTEGER,
                photo TEXT,
                event_uid TEXT
            );
            SQL
        );
        $pdo->exec(
            'CREATE TABLE catalogs(' .
            'uid TEXT PRIMARY KEY, sort_order INTEGER, slug TEXT, file TEXT, name TEXT, event_uid TEXT' .
            ');'
        );
        $pdo->exec('CREATE TABLE config(event_uid TEXT);');
        $cfg = new ConfigService($pdo);
        $cfg->setActiveEventUid('ev1');
        $service = new ResultService($pdo, $cfg);

        $pdo->exec("INSERT INTO results(name,catalog,attempt,correct,total,time,event_uid) VALUES('Team1','cat',1,0,0,0,'ev1')");
        $pdo->exec("INSERT INTO results(name,catalog,attempt,correct,total,time,event_uid) VALUES('Team2','cat',1,0,0,0,'ev2')");

        $pdo->exec("INSERT INTO catalogs(uid,sort_order,slug,file,name,event_uid) VALUES('u1',1,'cat','c.json','Catalog 1','ev1')");
        $pdo->exec("INSERT INTO catalogs(uid,sort_order,slug,file,name,event_uid) VALUES('u2',2,'cat','c.json','Catalog 2','ev2')");

        $rows = $service->getAll();
        $this->assertCount(1, $rows);
        $this->assertSame('Catalog 1', $rows[0]['catalogName']);
    }

    public function testGetQuestionResultsAssociatesCatalogNameWithEvent(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE question_results(
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                catalog TEXT NOT NULL,
                question_id INTEGER NOT NULL,
                attempt INTEGER NOT NULL,
                correct INTEGER NOT NULL,
                answer_text TEXT,
                photo TEXT,
                consent INTEGER,
                event_uid TEXT
            );
            SQL
        );
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE questions(
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                catalog_uid TEXT NOT NULL,
                sort_order INTEGER,
                type TEXT NOT NULL,
                prompt TEXT NOT NULL,
                options TEXT,
                answers TEXT,
                terms TEXT,
                items TEXT,
                cards TEXT,
                right_label TEXT,
                left_label TEXT
            );
            SQL
        );
        $pdo->exec(
            'CREATE TABLE catalogs(' .
            'uid TEXT PRIMARY KEY, sort_order INTEGER, slug TEXT, file TEXT, name TEXT, event_uid TEXT' .
            ');'
        );
        $pdo->exec('CREATE TABLE config(event_uid TEXT);');
        $cfg = new ConfigService($pdo);
        $service = new ResultService($pdo, $cfg);

        $pdo->exec("INSERT INTO questions(id,catalog_uid,sort_order,type,prompt) VALUES(1,'u1',1,'text','Q1')");

        $pdo->exec("INSERT INTO question_results(name,catalog,question_id,attempt,correct,event_uid) VALUES('Team1','cat',1,1,1,'ev1')");
        $pdo->exec("INSERT INTO question_results(name,catalog,question_id,attempt,correct,event_uid) VALUES('Team2','cat',1,1,1,'ev2')");

        $pdo->exec("INSERT INTO catalogs(uid,sort_order,slug,file,name,event_uid) VALUES('u1',1,'cat','c.json','Catalog 1','ev1')");
        $pdo->exec("INSERT INTO catalogs(uid,sort_order,slug,file,name,event_uid) VALUES('u2',2,'cat','c.json','Catalog 2','ev2')");

        $rows = $service->getQuestionResults();
        $this->assertCount(2, $rows);
        $this->assertSame('Catalog 1', $rows[0]['catalogName']);
        $this->assertSame('Catalog 2', $rows[1]['catalogName']);
    }

    public function testQueriesReturnEmptyWithoutActiveEvent(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE results(' .
            'id INTEGER PRIMARY KEY AUTOINCREMENT,' .
            'name TEXT NOT NULL,' .
            'catalog TEXT NOT NULL,' .
            'attempt INTEGER NOT NULL,' .
            'correct INTEGER NOT NULL,' .
            'total INTEGER NOT NULL,' .
            'time INTEGER NOT NULL,' .
            'puzzleTime INTEGER,' .
            'photo TEXT,' .
            'event_uid TEXT' .
            ')'
        );
        $pdo->exec(
            'CREATE TABLE question_results(' .
            'id INTEGER PRIMARY KEY AUTOINCREMENT,' .
            'name TEXT NOT NULL,' .
            'catalog TEXT NOT NULL,' .
            'question_id INTEGER NOT NULL,' .
            'attempt INTEGER NOT NULL,' .
            'correct INTEGER NOT NULL,' .
            'answer_text TEXT,' .
            'photo TEXT,' .
            'consent INTEGER,' .
            'event_uid TEXT' .
            ')'
        );
        $pdo->exec(
            'CREATE TABLE questions(' .
            'id INTEGER PRIMARY KEY AUTOINCREMENT,' .
            'catalog_uid TEXT NOT NULL,' .
            'sort_order INTEGER,' .
            'type TEXT NOT NULL,' .
            'prompt TEXT NOT NULL' .
            ')'
        );
        $pdo->exec('CREATE TABLE catalogs(uid TEXT PRIMARY KEY, sort_order INTEGER, slug TEXT, file TEXT, name TEXT, event_uid TEXT);');
        $pdo->exec('CREATE TABLE config(event_uid TEXT);');

        $pdo->exec("INSERT INTO results(name,catalog,attempt,correct,total,time,event_uid) VALUES('T','c',1,1,1,1,'ev1')");
        $pdo->exec("INSERT INTO question_results(name,catalog,question_id,attempt,correct,event_uid) VALUES('T','c',1,1,1,'ev1')");
        $pdo->exec("INSERT INTO questions(id,catalog_uid,sort_order,type,prompt) VALUES(1,'c',1,'text','Q')");
        $pdo->exec("INSERT INTO catalogs(uid,sort_order,slug,file,name,event_uid) VALUES('c',1,'c','c.json','C','ev1')");

        $cfg = new ConfigService($pdo);
        $cfg->setActiveEventUid('');
        $service = new ResultService($pdo, $cfg);

        $this->assertSame([], $service->getAll());
        $this->assertSame([], $service->getQuestionResults());
    }
}
