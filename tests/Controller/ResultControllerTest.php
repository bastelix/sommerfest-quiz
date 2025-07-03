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
        $pdo->exec('CREATE TABLE config(displayErrorDetails INTEGER, QRUser INTEGER, logoPath TEXT, pageTitle TEXT, header TEXT, subheader TEXT, backgroundColor TEXT, buttonColor TEXT, CheckAnswerButton TEXT, adminUser TEXT, adminPass TEXT, QRRestrict INTEGER, competitionMode INTEGER, teamResults INTEGER, photoUpload INTEGER, puzzleWordEnabled INTEGER, puzzleWord TEXT, puzzleFeedback TEXT, inviteText TEXT);');
        $pdo->exec("INSERT INTO config(header) VALUES('Event');");
        $pdo->exec('CREATE TABLE results(id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, catalog TEXT NOT NULL, attempt INTEGER NOT NULL, correct INTEGER NOT NULL, total INTEGER NOT NULL, time INTEGER NOT NULL, puzzleTime INTEGER, photo TEXT);');
        $pdo->exec("INSERT INTO results(name,catalog,attempt,correct,total,time) VALUES('Team1','cat',1,3,5,0)");
        $pdo->exec('CREATE TABLE question_results(id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, catalog TEXT NOT NULL, question_id INTEGER NOT NULL, attempt INTEGER NOT NULL, correct INTEGER NOT NULL, answer_text TEXT, photo TEXT, consent BOOLEAN);');
        $pdo->exec("INSERT INTO question_results(name,catalog,question_id,attempt,correct,photo) VALUES('Team1','cat',1,1,1,'/path/foo.jpg')");
        $pdo->exec('CREATE TABLE teams(sort_order INTEGER UNIQUE NOT NULL, name TEXT NOT NULL, uid TEXT PRIMARY KEY);');
        $pdo->exec("INSERT INTO teams(sort_order,name,uid) VALUES(1,'Team1','1')");

        $cfg = new \App\Service\ConfigService($pdo);
        $svc = new \App\Service\ResultService($pdo);
        $teams = new \App\Service\TeamService($pdo);
        $catalogs = new \App\Service\CatalogService($pdo);
        $ctrl = new \App\Controller\ResultController($svc, $cfg, $teams, $catalogs, sys_get_temp_dir());

        $req = $this->createRequest('GET', '/results.pdf');
        $response = $ctrl->pdf($req, new \Slim\Psr7\Response());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/pdf', $response->getHeaderLine('Content-Type'));
        $pdf = (string)$response->getBody();
        $this->assertNotEmpty($pdf);
        $this->assertStringContainsString('Event', $pdf);
        $this->assertStringContainsString('Team1', $pdf);
        $this->assertStringContainsString('Punkte: 3 von 5', $pdf);
        $this->assertGreaterThan(
            1,
            substr_count($pdf, '/Subtype /Image'),
            'Photo should be embedded in PDF'
        );
    }
}
