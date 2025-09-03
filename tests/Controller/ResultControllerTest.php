<?php

declare(strict_types=1);

namespace Tests\Controller;

use Tests\TestCase;

class ResultControllerTest extends TestCase
{
    public function testQuestionResultsEndpoint(): void
    {
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/question-results.json');
        $response = $app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testResultsPdfIsGenerated(): void
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
                qrLabelBottom TEXT,
                qrLogoPath TEXT,
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
            'uid TEXT PRIMARY KEY, name TEXT, start_date TEXT, end_date TEXT, description TEXT, ' .
            'sort_order INTEGER DEFAULT 0);'
        );
        $pdo->exec("INSERT INTO events(uid,name,description) VALUES('1','Event','Sub')");
        $pdo->exec(
            'CREATE TABLE catalogs(' .
            'uid TEXT PRIMARY KEY, sort_order INTEGER, slug TEXT, file TEXT, name TEXT, description TEXT,' .
            ' raetsel_buchstabe TEXT, event_uid TEXT' .
            ');'
        );
        $pdo->exec(
            'CREATE TABLE questions(' .
            'id INTEGER PRIMARY KEY AUTOINCREMENT, catalog_uid TEXT NOT NULL, sort_order INTEGER,' .
            ' type TEXT NOT NULL, prompt TEXT NOT NULL, options TEXT, answers TEXT, terms TEXT, items TEXT' .
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
                total INTEGER NOT NULL,
                time INTEGER NOT NULL,
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
        $this->assertStringContainsString('Punkte: 0 von 0', $pdf);
    }

    public function testPdfReflectsActiveEvent(): void
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
                qrLabelBottom TEXT,
                qrLogoPath TEXT,
                qrRoundMode TEXT,
                qrLogoPunchout INTEGER,
                event_uid TEXT
            );
            SQL
        );
        $pdo->exec(
            'CREATE TABLE events(' .
            'uid TEXT PRIMARY KEY, name TEXT, start_date TEXT, end_date TEXT, ' .
            'description TEXT, sort_order INTEGER DEFAULT 0' .
            ');'
        );
        $pdo->exec(
            "INSERT INTO events(uid,name,description) VALUES('1','First','A'),('2','Second','B')"
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
            ' type TEXT NOT NULL, prompt TEXT NOT NULL, options TEXT, answers TEXT, terms TEXT, items TEXT' .
            ');'
        );
        $pdo->exec(
            'CREATE TABLE results(' .
            'id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, catalog TEXT NOT NULL, ' .
            'attempt INTEGER NOT NULL, correct INTEGER NOT NULL, total INTEGER NOT NULL, ' .
            'time INTEGER NOT NULL, puzzleTime INTEGER, photo TEXT, event_uid TEXT' .
            ');'
        );
        $pdo->exec(
            "INSERT INTO results(name,catalog,attempt,correct,total,time) VALUES('Team1','cat',1,1,1,0)"
        );
        $pdo->exec(
            'CREATE TABLE question_results(' .
            'id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, catalog TEXT NOT NULL, ' .
            'question_id INTEGER NOT NULL, attempt INTEGER NOT NULL, correct INTEGER NOT NULL, ' .
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
}
