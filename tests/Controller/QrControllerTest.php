<?php

declare(strict_types=1);

namespace Tests\Controller;

use Tests\TestCase;
use Slim\Psr7\Response;
use Slim\Psr7\UploadedFile;

class QrControllerTest extends TestCase
{
    public function testQrImageIsGenerated(): void
    {
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/qr.png?t=Test');
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('image/png', $response->getHeaderLine('Content-Type'));
        $this->assertNotEmpty((string) $response->getBody());
    }

    public function testQrPdfIsGenerated(): void
    {
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/qr.pdf?t=Test');
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/pdf', $response->getHeaderLine('Content-Type'));
        $this->assertNotEmpty((string) $response->getBody());
    }

    public function testPdfUsesUploadedLogo(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE config(displayErrorDetails INTEGER, QRUser INTEGER, logoPath TEXT, pageTitle TEXT, header TEXT, subheader TEXT, backgroundColor TEXT, buttonColor TEXT, CheckAnswerButton TEXT, adminUser TEXT, adminPass TEXT, QRRestrict INTEGER, competitionMode INTEGER, teamResults INTEGER, photoUpload INTEGER, puzzleWordEnabled INTEGER, puzzleWord TEXT, puzzleFeedback TEXT, inviteText TEXT);');
        $cfg = new \App\Service\ConfigService($pdo);
        $teams = new \App\Service\TeamService($pdo);
        $qr = new \App\Controller\QrController($cfg, $teams);
        $logo = new \App\Controller\LogoController($cfg);

        $req = $this->createRequest('GET', '/qr.pdf?t=Demo');
        $initial = $qr->pdf($req, new Response());
        $original = (string)$initial->getBody();

        $logoFile = tempnam(sys_get_temp_dir(), 'logo');
        imagepng(imagecreatetruecolor(10, 10), $logoFile);
        $stream = fopen($logoFile, 'rb');
        $uploaded = new UploadedFile($stream, filesize($logoFile), UPLOAD_ERR_OK, 'logo.png', 'image/png');
        $upReq = $this->createRequest('POST', '/logo.png')->withUploadedFiles(['file' => $uploaded]);
        $logo->post($upReq, new Response());

        $updated = $qr->pdf($req, new Response());
        $this->assertNotEquals($original, (string)$updated->getBody());

        unlink($logoFile);
        unlink(dirname(__DIR__, 2) . '/data/logo.png');
    }

    public function testInvitePlaceholderIsReplaced(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE config(displayErrorDetails INTEGER, QRUser INTEGER, logoPath TEXT, pageTitle TEXT, header TEXT, subheader TEXT, backgroundColor TEXT, buttonColor TEXT, CheckAnswerButton TEXT, adminUser TEXT, adminPass TEXT, QRRestrict INTEGER, competitionMode INTEGER, teamResults INTEGER, photoUpload INTEGER, puzzleWordEnabled INTEGER, puzzleWord TEXT, puzzleFeedback TEXT, inviteText TEXT);');
        $pdo->exec("INSERT INTO config(inviteText) VALUES('Hallo [Team]!');");

        $cfg = new \App\Service\ConfigService($pdo);
        $teams = new \App\Service\TeamService($pdo);
        $qr  = new \App\Controller\QrController($cfg, $teams);

        $req = $this->createRequest('GET', '/qr.pdf?t=Demo');
        $response = $qr->pdf($req, new Response());
        $pdf = (string)$response->getBody();

        $this->assertStringContainsString('Hallo Demo!', $pdf);
    }

    public function testAllInvitationsPdfIsGenerated(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE config(displayErrorDetails INTEGER, QRUser INTEGER, logoPath TEXT, pageTitle TEXT, header TEXT, subheader TEXT, backgroundColor TEXT, buttonColor TEXT, CheckAnswerButton TEXT, adminUser TEXT, adminPass TEXT, QRRestrict INTEGER, competitionMode INTEGER, teamResults INTEGER, photoUpload INTEGER, puzzleWordEnabled INTEGER, puzzleWord TEXT, puzzleFeedback TEXT, inviteText TEXT);');
        $pdo->exec('CREATE TABLE teams(sort_order INTEGER UNIQUE NOT NULL, name TEXT NOT NULL, uid TEXT PRIMARY KEY);');
        $pdo->exec("INSERT INTO teams(sort_order,name,uid) VALUES(1,'A','1'),(2,'B','2')");

        $cfg = new \App\Service\ConfigService($pdo);
        $teams = new \App\Service\TeamService($pdo);
        $qr  = new \App\Controller\QrController($cfg, $teams);

        $req = $this->createRequest('GET', '/invites.pdf');
        $response = $qr->pdfAll($req, new Response());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/pdf', $response->getHeaderLine('Content-Type'));
        $this->assertNotEmpty((string)$response->getBody());
    }
}
