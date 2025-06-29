<?php

declare(strict_types=1);

namespace Tests\Controller;

use App\Controller\LogoController;
use App\Service\ConfigService;
use Tests\TestCase;
use Slim\Psr7\Response;
use Slim\Psr7\UploadedFile;

class LogoControllerTest extends TestCase
{
    public function testGetNotFound(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE config(displayErrorDetails INTEGER, QRUser INTEGER, logoPath TEXT, pageTitle TEXT, header TEXT, subheader TEXT, backgroundColor TEXT, buttonColor TEXT, CheckAnswerButton TEXT, adminUser TEXT, adminPass TEXT, QRRestrict INTEGER, competitionMode INTEGER, teamResults INTEGER, photoUpload INTEGER, puzzleWordEnabled INTEGER, puzzleWord TEXT, puzzleFeedback TEXT, inviteText TEXT);');
        $cfg = new ConfigService($pdo);
        $controller = new LogoController($cfg);
        $request = $this->createRequest('GET', '/logo.png');
        $response = $controller->get($request, new Response());

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testPostAndGet(): void
    {
        $tmpConfig = tempnam(sys_get_temp_dir(), 'cfg');
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE config(displayErrorDetails INTEGER, QRUser INTEGER, logoPath TEXT, pageTitle TEXT, header TEXT, subheader TEXT, backgroundColor TEXT, buttonColor TEXT, CheckAnswerButton TEXT, adminUser TEXT, adminPass TEXT, QRRestrict INTEGER, competitionMode INTEGER, teamResults INTEGER, photoUpload INTEGER, puzzleWordEnabled INTEGER, puzzleWord TEXT, puzzleFeedback TEXT, inviteText TEXT);');
        $cfg = new ConfigService($pdo);
        $controller = new LogoController($cfg);
        $logoFile = tempnam(sys_get_temp_dir(), 'logo');
        imagepng(imagecreatetruecolor(10, 10), $logoFile);
        $stream = fopen($logoFile, 'rb');
        $uploaded = new UploadedFile($stream, filesize($logoFile), UPLOAD_ERR_OK, 'logo.png', 'image/png');
        $request = $this->createRequest('POST', '/logo.png');
        $request = $request->withUploadedFiles(['file' => $uploaded]);

        $postResponse = $controller->post($request, new Response());
        $this->assertEquals(204, $postResponse->getStatusCode());

        $getResponse = $controller->get($this->createRequest('GET', '/logo.png'), new Response());
        $this->assertEquals(200, $getResponse->getStatusCode());

        unlink($tmpConfig);
        unlink($logoFile);
        unlink(sys_get_temp_dir() . '/logo.png');
    }

    public function testPostAndGetWebp(): void
    {
        $tmpConfig = tempnam(sys_get_temp_dir(), 'cfg');
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE config(displayErrorDetails INTEGER, QRUser INTEGER, logoPath TEXT, pageTitle TEXT, header TEXT, subheader TEXT, backgroundColor TEXT, buttonColor TEXT, CheckAnswerButton TEXT, adminUser TEXT, adminPass TEXT, QRRestrict INTEGER, competitionMode INTEGER, teamResults INTEGER, photoUpload INTEGER, puzzleWordEnabled INTEGER, puzzleWord TEXT, puzzleFeedback TEXT, inviteText TEXT);');
        $cfg = new ConfigService($pdo);
        $controller = new LogoController($cfg);
        $logoFile = tempnam(sys_get_temp_dir(), 'logo');
        file_put_contents($logoFile, 'dummy');
        $stream = fopen($logoFile, 'rb');
        $uploaded = new UploadedFile($stream, filesize($logoFile), UPLOAD_ERR_OK, 'logo.webp', 'image/webp');
        $request = $this->createRequest('POST', '/logo.png');
        $request = $request->withUploadedFiles(['file' => $uploaded]);

        $postResponse = $controller->post($request, new Response());
        $this->assertEquals(204, $postResponse->getStatusCode());

        $getResponse = $controller->get($this->createRequest('GET', '/logo.webp'), new Response());
        $this->assertEquals(200, $getResponse->getStatusCode());

        unlink($tmpConfig);
        unlink($logoFile);
        unlink(sys_get_temp_dir() . '/logo.webp');
    }
}
