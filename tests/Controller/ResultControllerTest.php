<?php

declare(strict_types=1);

namespace Tests\Controller;

use Tests\TestCase;

class ResultControllerTest extends TestCase
{
    public function testQuestionResultsEndpoint(): void {
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/question-results.json');
        $response = $app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testQuestionResultsEndpointWithoutQuestionColumns(): void {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE question_results('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT,'
            . 'name TEXT NOT NULL,'
            . 'catalog TEXT NOT NULL,'
            . 'question_id INTEGER NOT NULL,'
            . 'attempt INTEGER NOT NULL,'
            . 'correct INTEGER NOT NULL,'
            . 'points INTEGER NOT NULL DEFAULT 0,'
            . 'time_left_sec INTEGER,'
            . 'final_points INTEGER NOT NULL DEFAULT 0,'
            . 'efficiency REAL NOT NULL DEFAULT 0,'
            . 'is_correct INTEGER,'
            . 'scoring_version INTEGER NOT NULL DEFAULT 1,'
            . 'answer_text TEXT,'
            . 'photo TEXT,'
            . 'consent BOOLEAN,'
            . 'event_uid TEXT'
            . ')'
        );
        $pdo->exec(
            'CREATE TABLE questions('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT,'
            . 'catalog_uid TEXT NOT NULL,'
            . 'sort_order INTEGER,'
            . 'type TEXT NOT NULL,'
            . 'prompt TEXT NOT NULL,'
            . 'options TEXT,'
            . 'answers TEXT,'
            . 'terms TEXT,'
            . 'items TEXT'
            . ')'
        );
        $pdo->exec(
            'CREATE TABLE catalogs('
            . 'uid TEXT PRIMARY KEY,'
            . 'sort_order INTEGER,'
            . 'slug TEXT,'
            . 'file TEXT,'
            . 'name TEXT,'
            . 'description TEXT,'
            . 'raetsel_buchstabe TEXT,'
            . 'event_uid TEXT'
            . ')'
        );

        $pdo->exec("INSERT INTO catalogs(uid,name) VALUES('cat-1','Demo Catalog')");
        $pdo->exec(
            "INSERT INTO questions(catalog_uid,sort_order,type,prompt,options,answers,terms,items) "
            . "VALUES('cat-1',1,'choice','Prompt','[]','[]','[]','[]')"
        );
        $questionId = (int) $pdo->lastInsertId();
        $pdo->exec(
            "INSERT INTO question_results("
            . "name,catalog,question_id,attempt,correct,points,final_points,efficiency) "
            . "VALUES('Team Demo','cat-1',$questionId,1,1,2,2,0.5)"
        );

        $config = new \App\Service\ConfigService($pdo);
        $service = new \App\Service\ResultService($pdo);
        $teams = new \App\Service\TeamService($pdo, $config);
        $catalogs = new \App\Service\CatalogService($pdo, $config);
        $events = new \App\Service\EventService($pdo, $config);
        $controller = new \App\Controller\ResultController(
            $service,
            $config,
            $teams,
            $catalogs,
            sys_get_temp_dir(),
            $events
        );

        $request = $this->createRequest('GET', '/question-results.json');
        $response = $controller->getQuestions($request, new \Slim\Psr7\Response());

        $this->assertSame(200, $response->getStatusCode());
        $payload = (string) $response->getBody();
        $data = json_decode($payload, true);
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);
        $first = $data[0];
        $this->assertArrayHasKey('questionPoints', $first);
        $this->assertSame(0, $first['questionPoints']);
        $this->assertArrayHasKey('questionCountdown', $first);
        $this->assertNull($first['questionCountdown']);
    }

    public function testDownloadOmitsEpochForZeroTime(): void {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
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
            CREATE TABLE catalogs(
                uid TEXT PRIMARY KEY,
                sort_order INTEGER,
                slug TEXT,
                file TEXT,
                name TEXT,
                description TEXT,
                raetsel_buchstabe TEXT,
                event_uid TEXT
            );
            SQL
        );
        $pdo->exec(
            "INSERT INTO results(name,catalog,attempt,correct,points,total,max_points,time,puzzleTime,photo) " .
            "VALUES('Team Zero','catalog-1',1,4,7,10,15,0,0,NULL)"
        );

        putenv('DASHBOARD_TOKEN_SECRET=test-secret');
        $_ENV['DASHBOARD_TOKEN_SECRET'] = 'test-secret';
        $config = new \App\Service\ConfigService($pdo);
        $service = new \App\Service\ResultService($pdo);
        $teams = new \App\Service\TeamService($pdo, $config);
        $catalogs = new \App\Service\CatalogService($pdo, $config);
        $events = new \App\Service\EventService($pdo, $config);
        $controller = new \App\Controller\ResultController(
            $service,
            $config,
            $teams,
            $catalogs,
            sys_get_temp_dir(),
            $events
        );

        $request = $this->createRequest('GET', '/results.csv');
        $response = $controller->download($request, new \Slim\Psr7\Response());
        $body = (string) $response->getBody();
        $csv = preg_replace('/^\xEF\xBB\xBF/', '', $body) ?? $body;
        $lines = array_values(array_filter(explode("\n", trim($csv))));
        $this->assertCount(2, $lines);
        $rows = array_map(static fn(string $line): array => str_getcsv($line, ';'), $lines);

        $this->assertSame('', $rows[1][7]);
        $this->assertSame('', $rows[1][8]);
        $this->assertStringNotContainsString('1970-01-01', $csv);
    }

    public function testResultsPdfIsGenerated(): void {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE config(
                displayErrorDetails INTEGER,
                QRUser INTEGER,
                QRRemember INTEGER,
                logoPath TEXT,
                pageTitle TEXT,
                backgroundColor TEXT,
                buttonColor TEXT,
                startTheme TEXT,
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
                qrLabelLine1 TEXT,
                qrLabelLine2 TEXT,
                qrLogoPath TEXT,
                qrLogoWidth INTEGER,
                qrRoundMode TEXT,
                qrLogoPunchout INTEGER,
                qrRounded INTEGER,
                qrColorTeam TEXT,
                qrColorCatalog TEXT,
                qrColorEvent TEXT,
                event_uid TEXT
            );
            SQL
        );
        $pdo->exec(
            'CREATE TABLE events(' .
            'uid TEXT PRIMARY KEY, slug TEXT UNIQUE NOT NULL, name TEXT, start_date TEXT, end_date TEXT, ' .
            'description TEXT, sort_order INTEGER DEFAULT 0' .
            ');'
        );
        $pdo->exec(
            "INSERT INTO events(uid,slug,name,description) VALUES('1','1','Event','Sub')"
        );
        $pdo->exec(
            'CREATE TABLE catalogs(' .
            'uid TEXT PRIMARY KEY, sort_order INTEGER, slug TEXT, file TEXT, name TEXT, description TEXT,' .
            ' raetsel_buchstabe TEXT, event_uid TEXT' .
            ');'
        );
        $pdo->exec(
            'CREATE TABLE questions(' .
            'id INTEGER PRIMARY KEY AUTOINCREMENT, catalog_uid TEXT NOT NULL, sort_order INTEGER,' .
            ' type TEXT NOT NULL, prompt TEXT NOT NULL, options TEXT, answers TEXT, terms TEXT, items TEXT, countdown INTEGER,' .
            ' cards TEXT, right_label TEXT, left_label TEXT' .
            ');'
        );
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
        $pdo->exec("INSERT INTO results(name,catalog,attempt,correct,total,time) VALUES('Team1','cat',1,3,5,0)");
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
                consent BOOLEAN,
                event_uid TEXT
            );
            SQL
        );
        $pdo->exec(
            "INSERT INTO question_results(" .
            "name,catalog,question_id,attempt,correct,photo) " .
            "VALUES('Team1','cat',1,1,1,'/path/foo.jpg')"
        );
        $pdo->exec(
            'CREATE TABLE teams(' .
            'sort_order INTEGER UNIQUE NOT NULL, name TEXT NOT NULL, uid TEXT PRIMARY KEY, event_uid TEXT' .
            ');'
        );
        $pdo->exec("INSERT INTO teams(sort_order,name,uid,event_uid) VALUES(1,'Team1','1','1')");

        $cfg = new \App\Service\ConfigService($pdo);
        $cfg->setActiveEventUid('1');
        $svc = new \App\Service\ResultService($pdo, $cfg);
        $teams = new \App\Service\TeamService($pdo, $cfg);
        $catalogs = new \App\Service\CatalogService($pdo, $cfg);
        $events = new \App\Service\EventService($pdo);
        $ctrl = new \App\Controller\ResultController($svc, $cfg, $teams, $catalogs, sys_get_temp_dir(), $events);

        $req = $this->createRequest('GET', '/results.pdf');
        $response = $ctrl->pdf($req, new \Slim\Psr7\Response());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/pdf', $response->getHeaderLine('Content-Type'));
        $pdf = (string)$response->getBody();
        $this->assertNotEmpty($pdf);
        $this->assertStringContainsString('Event', $pdf);
        $this->assertStringContainsString('Team1', $pdf);
        $this->assertStringContainsString('Punkte: 0 von 5', $pdf);
    }

    public function testPdfReflectsActiveEvent(): void {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE config(
                displayErrorDetails INTEGER,
                QRUser INTEGER,
                QRRemember INTEGER,
                logoPath TEXT,
                pageTitle TEXT,
                backgroundColor TEXT,
                buttonColor TEXT,
                startTheme TEXT,
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
                qrLabelLine1 TEXT,
                qrLabelLine2 TEXT,
                qrLogoPath TEXT,
                qrLogoWidth INTEGER,
                qrRoundMode TEXT,
                qrLogoPunchout INTEGER,
                event_uid TEXT
            );
            SQL
        );
        $pdo->exec(
            'CREATE TABLE events(' .
            'uid TEXT PRIMARY KEY, slug TEXT UNIQUE NOT NULL, name TEXT, start_date TEXT, end_date TEXT, ' .
            'description TEXT, sort_order INTEGER DEFAULT 0' .
            ');'
        );
        $pdo->exec(
            "INSERT INTO events(uid,slug,name,description) VALUES('1','one','First','A'),('2','two','Second','B')"
        );
        $pdo->exec(
            'CREATE TABLE catalogs(' .
            'uid TEXT PRIMARY KEY, sort_order INTEGER, slug TEXT, file TEXT, name TEXT, description TEXT,' .
            ' raetsel_buchstabe TEXT, event_uid TEXT' .
            ');'
        );
        $pdo->exec(
            'CREATE TABLE questions(' .
            'id INTEGER PRIMARY KEY AUTOINCREMENT, catalog_uid TEXT NOT NULL, sort_order INTEGER,' .
            ' type TEXT NOT NULL, prompt TEXT NOT NULL, options TEXT, answers TEXT, terms TEXT, items TEXT, countdown INTEGER,' .
            ' cards TEXT, right_label TEXT, left_label TEXT' .
            ');'
        );
        $pdo->exec(
            'CREATE TABLE results(' .
            'id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, catalog TEXT NOT NULL, ' .
            'attempt INTEGER NOT NULL, correct INTEGER NOT NULL, points INTEGER NOT NULL DEFAULT 0, ' .
            'total INTEGER NOT NULL, max_points INTEGER NOT NULL DEFAULT 0, ' .
            'time INTEGER NOT NULL, started_at INTEGER, duration_sec INTEGER, expected_duration_sec INTEGER, duration_ratio REAL, ' .
            'puzzleTime INTEGER, photo TEXT, event_uid TEXT' .
            ');'
        );
        $pdo->exec(
            "INSERT INTO results(name,catalog,attempt,correct,total,time) VALUES('Team1','cat',1,1,1,0)"
        );
        $pdo->exec(
            'CREATE TABLE question_results(' .
            'id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, catalog TEXT NOT NULL, ' .
            'question_id INTEGER NOT NULL, attempt INTEGER NOT NULL, correct INTEGER NOT NULL, ' .
            'points INTEGER NOT NULL DEFAULT 0, time_left_sec INTEGER, final_points INTEGER NOT NULL DEFAULT 0, ' .
            'efficiency REAL NOT NULL DEFAULT 0, is_correct INTEGER, scoring_version INTEGER NOT NULL DEFAULT 1, ' .
            'answer_text TEXT, photo TEXT, consent BOOLEAN, event_uid TEXT' .
            ');'
        );
        $pdo->exec(
            'CREATE TABLE teams(' .
            'sort_order INTEGER UNIQUE NOT NULL, name TEXT NOT NULL, uid TEXT PRIMARY KEY, event_uid TEXT' .
            ');'
        );
        $pdo->exec("INSERT INTO teams(sort_order,name,uid,event_uid) VALUES(1,'Team1','1','1')");
        $pdo->exec("INSERT INTO teams(sort_order,name,uid,event_uid) VALUES(2,'Team2','2','2')");

        $cfg = new \App\Service\ConfigService($pdo);
        $teams = new \App\Service\TeamService($pdo, $cfg);
        $svc = new \App\Service\ResultService($pdo, $cfg);
        $catalogs = new \App\Service\CatalogService($pdo, $cfg);
        $events = new \App\Service\EventService($pdo);
        $ctrl = new \App\Controller\ResultController($svc, $cfg, $teams, $catalogs, sys_get_temp_dir(), $events);

        $cfg->setActiveEventUid('1');
        $req = $this->createRequest('GET', '/results.pdf');
        $res1 = $ctrl->pdf($req, new \Slim\Psr7\Response());
        $pdf1 = (string)$res1->getBody();
        $this->assertStringContainsString('First', $pdf1);

        $cfg->setActiveEventUid('2');
        $res2 = $ctrl->pdf($req, new \Slim\Psr7\Response());
        $pdf2 = (string)$res2->getBody();
        $this->assertStringContainsString('Second', $pdf2);
        $this->assertNotEquals($pdf1, $pdf2);
    }

    public function testTeamPdfUsesTeamSpecificEvent(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE config(
                displayErrorDetails INTEGER,
                QRUser INTEGER,
                QRRemember INTEGER,
                logoPath TEXT,
                pageTitle TEXT,
                backgroundColor TEXT,
                buttonColor TEXT,
                startTheme TEXT,
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
                qrLabelLine1 TEXT,
                qrLabelLine2 TEXT,
                qrLogoPath TEXT,
                qrLogoWidth INTEGER,
                qrRoundMode TEXT,
                qrLogoPunchout INTEGER,
                event_uid TEXT
            );
            SQL
        );
        $pdo->exec(
            'CREATE TABLE events(' .
            'uid TEXT PRIMARY KEY, slug TEXT UNIQUE NOT NULL, name TEXT, start_date TEXT, end_date TEXT, ' .
            'description TEXT, sort_order INTEGER DEFAULT 0' .
            ');'
        );
        $pdo->exec(
            "INSERT INTO events(uid,slug,name,description) VALUES('1','one','First','A'),('2','two','Second','B')"
        );
        $pdo->exec(
            'CREATE TABLE catalogs(' .
            'uid TEXT PRIMARY KEY, sort_order INTEGER, slug TEXT, file TEXT, name TEXT, description TEXT,' .
            ' raetsel_buchstabe TEXT, event_uid TEXT' .
            ');'
        );
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
            "INSERT INTO results(name,catalog,attempt,correct,points,total,max_points,time,event_uid) " .
            "VALUES('Team2','cat',1,4,7,10,10,0,'2')"
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
                consent BOOLEAN,
                event_uid TEXT
            );
            SQL
        );
        $pdo->exec(
            'CREATE TABLE teams(' .
            'sort_order INTEGER UNIQUE NOT NULL, name TEXT NOT NULL, uid TEXT PRIMARY KEY, event_uid TEXT' .
            ');'
        );
        $pdo->exec("INSERT INTO teams(sort_order,name,uid,event_uid) VALUES(1,'Team2','2','2')");

        $config = new \App\Service\ConfigService($pdo);
        $config->setActiveEventUid('1');
        $results = new \App\Service\ResultService($pdo);
        $teams = new \App\Service\TeamService($pdo, $config);
        $catalogs = new \App\Service\CatalogService($pdo, $config);
        $events = new \App\Service\EventService($pdo);
        $controller = new \App\Controller\ResultController($results, $config, $teams, $catalogs, sys_get_temp_dir(), $events);

        $request = $this->createRequest('GET', '/results.pdf?team=Team2');
        $response = $controller->pdf($request, new \Slim\Psr7\Response());

        $this->assertSame(200, $response->getStatusCode());
        $pdf = (string) $response->getBody();
        $this->assertStringContainsString('Second', $pdf);
        $this->assertStringContainsString('Team2', $pdf);
        $this->assertStringNotContainsString('First', $pdf);
    }
}
