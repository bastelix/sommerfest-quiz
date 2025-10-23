<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\ResultService;
use App\Service\ConfigService;
use PDO;
use Tests\TestCase;

class ResultServiceTest extends TestCase
{
    public function testAddIncrementsAttemptForSameCatalog(): void {
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
                points INTEGER NOT NULL DEFAULT 0,
                total INTEGER NOT NULL,
                max_points INTEGER NOT NULL DEFAULT 0,
                time INTEGER NOT NULL,
                started_at INTEGER,
                duration_sec INTEGER,
                expected_duration_sec INTEGER,
                duration_ratio REAL,
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
        $service = new ResultService($pdo);

        $first = $service->add(['name' => 'TeamA', 'catalog' => 'cat1']);
        $this->assertSame(1, $first['attempt']);

        $second = $service->add(['name' => 'TeamA', 'catalog' => 'cat1']);
        $this->assertSame(2, $second['attempt']);
    }

    public function testAddDoesNotIncrementAcrossCatalogs(): void {
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
                points INTEGER NOT NULL DEFAULT 0,
                total INTEGER NOT NULL,
                max_points INTEGER NOT NULL DEFAULT 0,
                time INTEGER NOT NULL,
                started_at INTEGER,
                duration_sec INTEGER,
                expected_duration_sec INTEGER,
                duration_ratio REAL,
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
        $service = new ResultService($pdo);

        $first = $service->add(['name' => 'TeamA', 'catalog' => 'cat1']);
        $this->assertSame(1, $first['attempt']);

        $other = $service->add(['name' => 'TeamA', 'catalog' => 'cat2']);
        $this->assertSame(1, $other['attempt']);
    }

    public function testAddPersistsStartAndDuration(): void {
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
                points INTEGER NOT NULL DEFAULT 0,
                total INTEGER NOT NULL,
                max_points INTEGER NOT NULL DEFAULT 0,
                time INTEGER NOT NULL,
                started_at INTEGER,
                duration_sec INTEGER,
                expected_duration_sec INTEGER,
                duration_ratio REAL,
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
        $service = new ResultService($pdo);

        $entry = $service->add([
            'name' => 'TeamX',
            'catalog' => 'cat1',
            'total' => 0,
            'time' => 2000,
            'startedAt' => 1950,
        ]);

        $stmt = $pdo->query('SELECT started_at, duration_sec, time FROM results');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(1950, (int) $row['started_at']);
        $this->assertSame(50, (int) $row['duration_sec']);
        $this->assertSame(2000, (int) $row['time']);
        $this->assertSame(1950, $entry['started_at']);
        $this->assertSame(50, $entry['duration_sec']);
    }

    public function testExistsChecksPlayerCatalogPair(): void {
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
                points INTEGER NOT NULL DEFAULT 0,
                total INTEGER NOT NULL,
                max_points INTEGER NOT NULL DEFAULT 0,
                time INTEGER NOT NULL,
                started_at INTEGER,
                duration_sec INTEGER,
                expected_duration_sec INTEGER,
                duration_ratio REAL,
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
        $service = new ResultService($pdo);

        $service->add(['name' => 'TeamA', 'catalog' => 'cat1']);
        $this->assertTrue($service->exists('TeamA', 'cat1'));
        $this->assertFalse($service->exists('TeamB', 'cat1'));
    }

    public function testMarkPuzzleUpdatesEntry(): void {
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
                points INTEGER NOT NULL DEFAULT 0,
                total INTEGER NOT NULL,
                max_points INTEGER NOT NULL DEFAULT 0,
                time INTEGER NOT NULL,
                started_at INTEGER,
                duration_sec INTEGER,
                expected_duration_sec INTEGER,
                duration_ratio REAL,
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
        $service = new ResultService($pdo);

        $service->add(['name' => 'TeamA', 'catalog' => 'cat1']);
        $ts = time();
        $res = $service->markPuzzle('TeamA', 'cat1', $ts);
        $this->assertTrue($res);
        $stmt = $pdo->query('SELECT puzzleTime FROM results');
        $this->assertSame($ts, (int)$stmt->fetchColumn());
    }

    public function testMarkPuzzleReturnsTrueIfAlreadySolved(): void {
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
                points INTEGER NOT NULL DEFAULT 0,
                total INTEGER NOT NULL,
                max_points INTEGER NOT NULL DEFAULT 0,
                time INTEGER NOT NULL,
                started_at INTEGER,
                duration_sec INTEGER,
                expected_duration_sec INTEGER,
                duration_ratio REAL,
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
        $service = new ResultService($pdo);

        $service->add(['name' => 'TeamA', 'catalog' => 'cat1', 'puzzleTime' => 123]);
        $res = $service->markPuzzle('TeamA', 'cat1', 456);
        $this->assertTrue($res);
        $stmt = $pdo->query('SELECT puzzleTime FROM results');
        $this->assertSame(123, (int)$stmt->fetchColumn());
    }

    public function testSetPhotoUpdatesEntry(): void {
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
                points INTEGER NOT NULL DEFAULT 0,
                total INTEGER NOT NULL,
                max_points INTEGER NOT NULL DEFAULT 0,
                time INTEGER NOT NULL,
                started_at INTEGER,
                duration_sec INTEGER,
                expected_duration_sec INTEGER,
                duration_ratio REAL,
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
        $service = new ResultService($pdo);

        $service->add(['name' => 'TeamA', 'catalog' => 'cat1']);
        $service->setPhoto('TeamA', 'cat1', '/photo/test.jpg');
        $stmt = $pdo->query('SELECT photo FROM results');
        $this->assertSame('/photo/test.jpg', $stmt->fetchColumn());
    }

    public function testAddStoresQuestionResults(): void {
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
                points INTEGER NOT NULL DEFAULT 0,
                total INTEGER NOT NULL,
                max_points INTEGER NOT NULL DEFAULT 0,
                time INTEGER NOT NULL,
                started_at INTEGER,
                duration_sec INTEGER,
                expected_duration_sec INTEGER,
                duration_ratio REAL,
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
                points INTEGER NOT NULL DEFAULT 0,
                time_left_sec INTEGER,
                final_points INTEGER NOT NULL DEFAULT 0,
                efficiency REAL NOT NULL DEFAULT 0,
                is_correct INTEGER,
                scoring_version INTEGER NOT NULL DEFAULT 1,
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
                points INTEGER,
                options TEXT,
                answers TEXT,
                terms TEXT,
                items TEXT,
                countdown INTEGER,
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
        $service = new ResultService($pdo);
        $service->add(['name' => 'Team', 'catalog' => 'cat1', 'correct' => 1, 'total' => 2, 'wrong' => [2]]);

        $stmt = $pdo->query(
            'SELECT question_id, correct, points, final_points, efficiency, time_left_sec, is_correct, scoring_version '
            . 'FROM question_results ORDER BY id'
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(2, $rows);
        $this->assertSame('1', (string)$rows[0]['question_id']);
        $this->assertSame('1', (string)$rows[0]['correct']);
        $this->assertSame('1', (string)$rows[0]['points']);
        $this->assertSame('1', (string)$rows[0]['final_points']);
        $this->assertSame(1.0, (float)$rows[0]['efficiency']);
        $this->assertNull($rows[0]['time_left_sec']);
        $this->assertSame('1', (string)$rows[0]['is_correct']);
        $this->assertSame('1', (string)$rows[0]['scoring_version']);
        $this->assertSame('2', (string)$rows[1]['question_id']);
        $this->assertSame('0', (string)$rows[1]['correct']);
        $this->assertSame('0', (string)$rows[1]['points']);
        $this->assertSame('0', (string)$rows[1]['final_points']);
        $this->assertSame(0.0, (float)$rows[1]['efficiency']);
        $this->assertNull($rows[1]['time_left_sec']);
        $this->assertSame('0', (string)$rows[1]['is_correct']);
        $this->assertSame('1', (string)$rows[1]['scoring_version']);
    }

    public function testAddQuestionResultsRespectsIsCorrectFlag(): void {
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
                points INTEGER NOT NULL DEFAULT 0,
                total INTEGER NOT NULL,
                max_points INTEGER NOT NULL DEFAULT 0,
                time INTEGER NOT NULL,
                started_at INTEGER,
                duration_sec INTEGER,
                expected_duration_sec INTEGER,
                duration_ratio REAL,
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
                points INTEGER NOT NULL DEFAULT 0,
                time_left_sec INTEGER,
                final_points INTEGER NOT NULL DEFAULT 0,
                efficiency REAL NOT NULL DEFAULT 0,
                is_correct INTEGER,
                scoring_version INTEGER NOT NULL DEFAULT 1,
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
                points INTEGER,
                options TEXT,
                answers TEXT,
                terms TEXT,
                items TEXT,
                countdown INTEGER,
                cards TEXT,
                right_label TEXT,
                left_label TEXT
            );
            SQL
        );
        $pdo->exec("INSERT INTO catalogs(uid,sort_order,slug,file,name) VALUES('cat-1',1,'cat1','c.json','C1')");
        $pdo->exec("INSERT INTO questions(catalog_uid,sort_order,type,prompt,points,countdown) VALUES('cat-1',1,'text','Q1',3,10)");

        $service = new ResultService($pdo);
        $service->add([
            'name' => 'Tester',
            'catalog' => 'cat1',
            'correct' => 0,
            'total' => 1,
            'wrong' => [],
            'answers' => [
                [
                    'isCorrect' => false,
                    'timeLeftSec' => 0,
                    'text' => 'Antwort',
                ],
            ],
        ]);

        $stmt = $pdo->query('SELECT correct, is_correct, time_left_sec FROM question_results ORDER BY id');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('0', (string) $row['correct']);
        $this->assertSame('0', (string) $row['is_correct']);
        $this->assertSame(0, (int) $row['time_left_sec']);
    }

    public function testAddStoresUppercaseSlugQuestionResults(): void {
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
                points INTEGER NOT NULL DEFAULT 0,
                total INTEGER NOT NULL,
                max_points INTEGER NOT NULL DEFAULT 0,
                time INTEGER NOT NULL,
                started_at INTEGER,
                duration_sec INTEGER,
                expected_duration_sec INTEGER,
                duration_ratio REAL,
                puzzleTime INTEGER,
                photo TEXT,
                event_uid TEXT
            );
            SQL
        );
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE question_results(
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                catalog TEXT NOT NULL,
                question_id INTEGER NOT NULL,
                attempt INTEGER NOT NULL,
                correct INTEGER NOT NULL,
                points INTEGER NOT NULL DEFAULT 0,
                time_left_sec INTEGER,
                final_points INTEGER NOT NULL DEFAULT 0,
                efficiency REAL NOT NULL DEFAULT 0,
                is_correct INTEGER,
                scoring_version INTEGER NOT NULL DEFAULT 1,
                answer_text TEXT,
                photo TEXT,
                consent INTEGER,
                event_uid TEXT
            );
            SQL
        );
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
                points INTEGER,
                options TEXT,
                answers TEXT,
                terms TEXT,
                items TEXT,
                countdown INTEGER,
                cards TEXT,
                right_label TEXT,
                left_label TEXT
            );
            SQL
        );
        $pdo->exec("INSERT INTO catalogs(uid,sort_order,slug,file,name) VALUES('upper-1',1,'MAIN-SLUG','c.json','Main');");
        $pdo->exec("INSERT INTO questions(catalog_uid,sort_order,type,prompt,points) VALUES('upper-1',1,'text','Q1',4);");
        $pdo->exec("INSERT INTO questions(catalog_uid,sort_order,type,prompt,points) VALUES('upper-1',2,'text','Q2',3);");

        $service = new ResultService($pdo);
        $entry = $service->add([
            'name' => 'Team Upper',
            'catalog' => 'MAIN-SLUG',
            'correct' => 2,
            'total' => 2,
            'wrong' => [],
        ]);

        $rows = $pdo->query('SELECT catalog, question_id, final_points FROM question_results ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(2, $rows);
        $this->assertSame('MAIN-SLUG', $rows[0]['catalog']);
        $this->assertSame('MAIN-SLUG', $rows[1]['catalog']);
        $this->assertSame(4, (int) $rows[0]['final_points']);
        $this->assertSame(3, (int) $rows[1]['final_points']);

        $this->assertSame(7, $entry['points']);
        $this->assertSame(7, $entry['max_points']);

        $stored = $pdo->query('SELECT points, max_points FROM results')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(7, (int) $stored['points']);
        $this->assertSame(7, (int) $stored['max_points']);
    }

    public function testAddStoresQuestionResultsWithMismatchedSlugCase(): void {
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
                points INTEGER NOT NULL DEFAULT 0,
                total INTEGER NOT NULL,
                max_points INTEGER NOT NULL DEFAULT 0,
                time INTEGER NOT NULL,
                started_at INTEGER,
                duration_sec INTEGER,
                expected_duration_sec INTEGER,
                duration_ratio REAL,
                puzzleTime INTEGER,
                photo TEXT,
                event_uid TEXT
            );
            SQL
        );
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE question_results(
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                catalog TEXT NOT NULL,
                question_id INTEGER NOT NULL,
                attempt INTEGER NOT NULL,
                correct INTEGER NOT NULL,
                points INTEGER NOT NULL DEFAULT 0,
                time_left_sec INTEGER,
                final_points INTEGER NOT NULL DEFAULT 0,
                efficiency REAL NOT NULL DEFAULT 0,
                is_correct INTEGER,
                scoring_version INTEGER NOT NULL DEFAULT 1,
                answer_text TEXT,
                photo TEXT,
                consent INTEGER,
                event_uid TEXT
            );
            SQL
        );
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
                points INTEGER,
                options TEXT,
                answers TEXT,
                terms TEXT,
                items TEXT,
                countdown INTEGER,
                cards TEXT,
                right_label TEXT,
                left_label TEXT
            );
            SQL
        );
        $pdo->exec("INSERT INTO catalogs(uid,sort_order,slug,file,name) VALUES('mixed-1',1,'main-slug','c.json','Main');");
        $pdo->exec("INSERT INTO questions(catalog_uid,sort_order,type,prompt,points,countdown) VALUES('mixed-1',1,'text','Q1',5,30);");
        $pdo->exec("INSERT INTO questions(catalog_uid,sort_order,type,prompt,points,countdown) VALUES('mixed-1',2,'text','Q2',7,20);");

        $service = new ResultService($pdo);
        $entry = $service->add([
            'name' => 'Team Mixed',
            'catalog' => 'MAIN-SLUG',
            'correct' => 2,
            'total' => 2,
            'wrong' => [],
            'answers' => [
                ['timeLeftSec' => 12],
                ['timeLeftSec' => 4],
            ],
        ]);

        $rows = $pdo->query('SELECT catalog, question_id, time_left_sec, final_points FROM question_results ORDER BY id')
            ->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(2, $rows);
        $this->assertSame('MAIN-SLUG', $rows[0]['catalog']);
        $this->assertSame('MAIN-SLUG', $rows[1]['catalog']);
        $this->assertSame(12, (int) $rows[0]['time_left_sec']);
        $this->assertSame(4, (int) $rows[1]['time_left_sec']);
        $this->assertSame(2, (int) $rows[0]['final_points']);
        $this->assertSame(1, (int) $rows[1]['final_points']);

        $this->assertSame(3, $entry['points']);
        $this->assertSame(12, $entry['max_points']);
        $this->assertSame(50, $entry['expectedDurationSec']);
        $this->assertSame(34, $entry['durationSec']);
        $this->assertEqualsWithDelta(0.68, $entry['durationRatio'], 0.0001);

        $stored = $pdo->query('SELECT points, max_points, expected_duration_sec, duration_sec, duration_ratio FROM results')
            ->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(3, (int) $stored['points']);
        $this->assertSame(12, (int) $stored['max_points']);
        $this->assertSame(50, (int) $stored['expected_duration_sec']);
        $this->assertSame(34, (int) $stored['duration_sec']);
        $this->assertEqualsWithDelta(0.68, (float) $stored['duration_ratio'], 0.0001);
    }

    public function testAddComputesExpectedDurationRatioFromAttemptDuration(): void {
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
                points INTEGER NOT NULL DEFAULT 0,
                total INTEGER NOT NULL,
                max_points INTEGER NOT NULL DEFAULT 0,
                time INTEGER NOT NULL,
                started_at INTEGER,
                duration_sec INTEGER,
                expected_duration_sec INTEGER,
                duration_ratio REAL,
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
                points INTEGER NOT NULL DEFAULT 0,
                time_left_sec INTEGER,
                final_points INTEGER NOT NULL DEFAULT 0,
                efficiency REAL NOT NULL DEFAULT 0,
                is_correct INTEGER,
                scoring_version INTEGER NOT NULL DEFAULT 1,
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
                points INTEGER,
                options TEXT,
                answers TEXT,
                terms TEXT,
                items TEXT,
                countdown INTEGER,
                cards TEXT,
                right_label TEXT,
                left_label TEXT
            );
            SQL
        );
        $pdo->exec("INSERT INTO catalogs(uid,sort_order,slug,file,name) VALUES('u1',1,'cat','c.json','C')");
        $pdo->exec("INSERT INTO questions(catalog_uid,sort_order,type,prompt,countdown) VALUES('u1',1,'text','Q1',30)");

        $service = new ResultService($pdo);
        $entry = $service->add([
            'name' => 'Team',
            'catalog' => 'cat',
            'correct' => 1,
            'total' => 1,
            'wrong' => [],
            'answers' => [
                ['timeLeftSec' => 10],
            ],
            'startedAt' => 1000,
            'time' => 1030,
        ]);

        $stmt = $pdo->query('SELECT expected_duration_sec, duration_ratio, duration_sec FROM results');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(30, (int) $row['expected_duration_sec']);
        $this->assertSame(30, (int) $row['duration_sec']);
        $this->assertEqualsWithDelta(1.0, (float) $row['duration_ratio'], 0.0001);
        $this->assertSame(30, $entry['expectedDurationSec']);
        $this->assertEqualsWithDelta(1.0, (float) $entry['durationRatio'], 0.0001);
    }

    public function testAddComputesRatioWhenDurationMissing(): void {
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
                points INTEGER NOT NULL DEFAULT 0,
                total INTEGER NOT NULL,
                max_points INTEGER NOT NULL DEFAULT 0,
                time INTEGER NOT NULL,
                started_at INTEGER,
                duration_sec INTEGER,
                expected_duration_sec INTEGER,
                duration_ratio REAL,
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
                points INTEGER NOT NULL DEFAULT 0,
                time_left_sec INTEGER,
                final_points INTEGER NOT NULL DEFAULT 0,
                efficiency REAL NOT NULL DEFAULT 0,
                is_correct INTEGER,
                scoring_version INTEGER NOT NULL DEFAULT 1,
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
                points INTEGER,
                options TEXT,
                answers TEXT,
                terms TEXT,
                items TEXT,
                countdown INTEGER,
                cards TEXT,
                right_label TEXT,
                left_label TEXT
            );
            SQL
        );
        $pdo->exec("INSERT INTO catalogs(uid,sort_order,slug,file,name) VALUES('u1',1,'cat','c.json','C')");
        $pdo->exec("INSERT INTO questions(catalog_uid,sort_order,type,prompt,countdown) VALUES('u1',1,'text','Q1',40)");

        $service = new ResultService($pdo);
        $entry = $service->add([
            'name' => 'Team',
            'catalog' => 'cat',
            'correct' => 1,
            'total' => 1,
            'wrong' => [],
            'answers' => [
                ['timeLeftSec' => 10],
            ],
        ]);

        $stmt = $pdo->query('SELECT expected_duration_sec, duration_ratio, duration_sec FROM results');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(40, (int) $row['expected_duration_sec']);
        $this->assertSame(30, (int) $row['duration_sec']);
        $this->assertEqualsWithDelta(0.75, (float) $row['duration_ratio'], 0.0001);
        $this->assertSame(40, $entry['expectedDurationSec']);
        $this->assertSame(30, $entry['durationSec']);
        $this->assertEqualsWithDelta(0.75, (float) $entry['durationRatio'], 0.0001);
    }

    public function testClearRemovesResultsAndQuestionResults(): void {
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
                started_at INTEGER,
                duration_sec INTEGER,
                expected_duration_sec INTEGER,
                duration_ratio REAL,
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
                points INTEGER NOT NULL DEFAULT 0,
                time_left_sec INTEGER,
                final_points INTEGER NOT NULL DEFAULT 0,
                efficiency REAL NOT NULL DEFAULT 0,
                is_correct INTEGER,
                scoring_version INTEGER NOT NULL DEFAULT 1,
                answer_text TEXT,
                photo TEXT,
                consent INTEGER,
                event_uid TEXT
            );
            SQL
        );
        $cfg = new ConfigService($pdo);
        $service = new ResultService($pdo);
        $service->add([ 'name' => 'Team', 'catalog' => 'cat1', 'correct' => 1, 'total' => 1 ]);
        $service->clear();
        $resCount = (int) $pdo->query('SELECT COUNT(*) FROM results')->fetchColumn();
        $qresCount = (int) $pdo->query('SELECT COUNT(*) FROM question_results')->fetchColumn();
        $this->assertSame(0, $resCount);
        $this->assertSame(0, $qresCount);
    }

    public function testPhotoAttachedAfterEventUidChange(): void {
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
                started_at INTEGER,
                duration_sec INTEGER,
                expected_duration_sec INTEGER,
                duration_ratio REAL,
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
                countdown INTEGER,
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
                points INTEGER NOT NULL DEFAULT 0,
                time_left_sec INTEGER,
                final_points INTEGER NOT NULL DEFAULT 0,
                efficiency REAL NOT NULL DEFAULT 0,
                is_correct INTEGER,
                scoring_version INTEGER NOT NULL DEFAULT 1,
                answer_text TEXT,
                photo TEXT,
                consent INTEGER,
                event_uid TEXT
            );
            SQL
        );
        $cfg = new ConfigService($pdo);
        $service = new ResultService($pdo);

        $service->add(['name' => 'TeamA', 'catalog' => 'cat1']);

        $cfg->setActiveEventUid('ev2');

        $service->setPhoto('TeamA', 'cat1', '/photo/img.jpg');

        $stmt = $pdo->query('SELECT photo, event_uid FROM results');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('/photo/img.jpg', $row['photo']);
        $this->assertSame('ev1', $row['event_uid']);
    }

    public function testGetAllAssociatesCatalogNameWithEvent(): void {
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
                started_at INTEGER,
                duration_sec INTEGER,
                expected_duration_sec INTEGER,
                duration_ratio REAL,
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
        $service = new ResultService($pdo);

        $pdo->exec(
            "INSERT INTO results(name,catalog,attempt,correct,total,time,event_uid) " .
            "VALUES('Team1','cat',1,0,0,0,'ev1')"
        );
        $pdo->exec(
            "INSERT INTO results(name,catalog,attempt,correct,total,time,event_uid) " .
            "VALUES('Team2','cat',1,0,0,0,'ev2')"
        );

        $pdo->exec(
            "INSERT INTO catalogs(uid,sort_order,slug,file,name,event_uid) " .
            "VALUES('u1',1,'cat','c.json','Catalog 1','ev1')"
        );
        $pdo->exec(
            "INSERT INTO catalogs(uid,sort_order,slug,file,name,event_uid) " .
            "VALUES('u2',2,'cat','c.json','Catalog 2','ev2')"
        );

        $rows = $service->getAll();
        $this->assertCount(1, $rows);
        $this->assertSame('Catalog 1', $rows[0]['catalogName']);
    }

    public function testGetQuestionResultsAssociatesCatalogNameWithEvent(): void {
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
                points INTEGER NOT NULL DEFAULT 0,
                time_left_sec INTEGER,
                final_points INTEGER NOT NULL DEFAULT 0,
                efficiency REAL NOT NULL DEFAULT 0,
                is_correct INTEGER,
                scoring_version INTEGER NOT NULL DEFAULT 1,
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
                countdown INTEGER,
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
        $service = new ResultService($pdo);

        $pdo->exec("INSERT INTO questions(id,catalog_uid,sort_order,type,prompt) VALUES(1,'u1',1,'text','Q1')");

        $pdo->exec(
            "INSERT INTO question_results(name,catalog,question_id,attempt,correct,event_uid) " .
            "VALUES('Team1','cat',1,1,1,'ev1')"
        );
        $pdo->exec(
            "INSERT INTO question_results(name,catalog,question_id,attempt,correct,event_uid) " .
            "VALUES('Team2','cat',1,1,1,'ev2')"
        );

        $pdo->exec(
            "INSERT INTO catalogs(uid,sort_order,slug,file,name,event_uid) " .
            "VALUES('u1',1,'cat','c.json','Catalog 1','ev1')"
        );
        $pdo->exec(
            "INSERT INTO catalogs(uid,sort_order,slug,file,name,event_uid) " .
            "VALUES('u2',2,'cat','c.json','Catalog 2','ev2')"
        );

        $rows = $service->getQuestionResults();
        $this->assertCount(2, $rows);
        $this->assertSame('Catalog 1', $rows[0]['catalogName']);
        $this->assertSame('Catalog 2', $rows[1]['catalogName']);
    }

    public function testQueriesReturnEmptyWithoutActiveEvent(): void {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE results(' .
            'id INTEGER PRIMARY KEY AUTOINCREMENT,' .
            'name TEXT NOT NULL,' .
            'catalog TEXT NOT NULL,' .
            'attempt INTEGER NOT NULL,' .
            'correct INTEGER NOT NULL,' .
            'points INTEGER NOT NULL DEFAULT 0,' .
            'total INTEGER NOT NULL,' .
            'max_points INTEGER NOT NULL DEFAULT 0,' .
            'time INTEGER NOT NULL,' .
            'started_at INTEGER,' .
            'duration_sec INTEGER,' .
            'expected_duration_sec INTEGER,' .
            'duration_ratio REAL,' .
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
            'points INTEGER NOT NULL DEFAULT 0,' .
            'time_left_sec INTEGER,' .
            'final_points INTEGER NOT NULL DEFAULT 0,' .
            'efficiency REAL NOT NULL DEFAULT 0,' .
            'is_correct INTEGER,' .
            'scoring_version INTEGER NOT NULL DEFAULT 1,' .
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
            'prompt TEXT NOT NULL,' .
            'countdown INTEGER' .
            ')'
        );
        $pdo->exec(
            'CREATE TABLE catalogs(' .
            'uid TEXT PRIMARY KEY, sort_order INTEGER, slug TEXT, file TEXT, name TEXT, event_uid TEXT' .
            ');'
        );
        $pdo->exec('CREATE TABLE config(event_uid TEXT);');

        $pdo->exec(
            "INSERT INTO results(name,catalog,attempt,correct,total,time,event_uid) " .
            "VALUES('T','c',1,1,1,1,'ev1')"
        );
        $pdo->exec(
            "INSERT INTO question_results(name,catalog,question_id,attempt,correct,event_uid) " .
            "VALUES('T','c',1,1,1,'ev1')"
        );
        $pdo->exec(
            "INSERT INTO questions(id,catalog_uid,sort_order,type,prompt) VALUES(1,'c',1,'text','Q')"
        );
        $pdo->exec(
            "INSERT INTO catalogs(uid,sort_order,slug,file,name,event_uid) VALUES('c',1,'c','c.json','C','ev1')"
        );

        $cfg = new ConfigService($pdo);
        $cfg->setActiveEventUid('');
        $service = new ResultService($pdo);

        $this->assertSame([], $service->getAll());
        $this->assertSame([], $service->getQuestionResults());
    }

    public function testGetAllHidesPuzzleTimeWhenMissing(): void {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE results(' .
            'id INTEGER PRIMARY KEY AUTOINCREMENT,' .
            'name TEXT NOT NULL,' .
            'catalog TEXT NOT NULL,' .
            'attempt INTEGER NOT NULL,' .
            'correct INTEGER NOT NULL,' .
            'points INTEGER NOT NULL DEFAULT 0,' .
            'total INTEGER NOT NULL,' .
            'max_points INTEGER NOT NULL DEFAULT 0,' .
            'time INTEGER NOT NULL,' .
            'started_at INTEGER,' .
            'duration_sec INTEGER,' .
            'expected_duration_sec INTEGER,' .
            'duration_ratio REAL,' .
            'puzzleTime INTEGER,' .
            'photo TEXT,' .
            'event_uid TEXT' .
            ')'
        );
        $pdo->exec(
            'CREATE TABLE catalogs(' .
            'uid TEXT PRIMARY KEY, sort_order INTEGER, slug TEXT, file TEXT, name TEXT, event_uid TEXT' .
            ');'
        );
        $pdo->exec(
            "INSERT INTO results(name,catalog,attempt,correct,points,total,max_points,time,puzzleTime) " .
            "VALUES('Team','slug',1,0,0,0,0,1700000000,0)"
        );
        $pdo->exec("INSERT INTO catalogs(uid,sort_order,slug,file,name) VALUES('slug',1,'slug','c.json','Slug');");

        $service = new ResultService($pdo);
        $rows = $service->getAll();

        $this->assertCount(1, $rows);
        $this->assertArrayHasKey('puzzleTime', $rows[0]);
        $this->assertNull($rows[0]['puzzleTime']);
    }
}
