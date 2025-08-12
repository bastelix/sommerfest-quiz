<?php

declare(strict_types=1);

namespace Tests\Controller;

use App\Controller\LogoController;
use App\Service\ConfigService;
use Tests\TestCase;
use Slim\Psr7\Response;
use Slim\Psr7\UploadedFile;
use Slim\Psr7\Stream;

class LogoControllerTest extends TestCase
{
    public function testGetFallbackLogo(): void
    {
        $pdo = $this->createDatabase();
        $cfg = new ConfigService($pdo);
        $cfg->saveConfig([]);
        $controller = new LogoController($cfg);
        @rename(dirname(__DIR__, 2) . '/data/logo.png', dirname(__DIR__, 2) . '/data/logo.png.bak');
        @rename(dirname(__DIR__, 2) . '/data/logo.webp', dirname(__DIR__, 2) . '/data/logo.webp.bak');
        $request = $this->createRequest('GET', '/logo.png');
        $response = $controller->get($request, new Response());

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame('image/svg+xml', $response->getHeaderLine('Content-Type'));
        $this->assertNotEmpty((string) $response->getBody());
        @rename(dirname(__DIR__, 2) . '/data/logo.png.bak', dirname(__DIR__, 2) . '/data/logo.png');
        @rename(dirname(__DIR__, 2) . '/data/logo.webp.bak', dirname(__DIR__, 2) . '/data/logo.webp');
    }

    public function testPostAndGet(): void
    {
        $tmpConfig = tempnam(sys_get_temp_dir(), 'cfg');
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
                event_uid TEXT
            );
            SQL
        );
        $cfg = new ConfigService($pdo);
        $controller = new LogoController($cfg);
        $logoFile = tempnam(sys_get_temp_dir(), 'logo');
        imagepng(imagecreatetruecolor(10, 10), $logoFile);
        $stream = fopen($logoFile, 'rb');
        $uploaded = new UploadedFile(new Stream($stream), 'logo.png', 'image/png', filesize($logoFile), UPLOAD_ERR_OK);
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
                event_uid TEXT
            );
            SQL
        );
        $cfg = new ConfigService($pdo);
        $controller = new LogoController($cfg);
        $logoFile = tempnam(sys_get_temp_dir(), 'logo');
        imagewebp(imagecreatetruecolor(10, 10), $logoFile);
        $stream = fopen($logoFile, 'rb');
        $uploaded = new UploadedFile(
            new Stream($stream),
            'logo.webp',
            'image/webp',
            filesize($logoFile),
            UPLOAD_ERR_OK
        );
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

    public function testLogoPerEvent(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE events(uid TEXT PRIMARY KEY, name TEXT, sort_order INTEGER DEFAULT 0);'
        );
        $pdo->exec(
            'CREATE TABLE active_event(event_uid TEXT PRIMARY KEY);'
        );
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
                event_uid TEXT
            );
            SQL
        );
        $pdo->exec("INSERT INTO events(uid,name) VALUES('e1','Eins'),('e2','Zwei')");

        $cfg = new ConfigService($pdo);
        $cfg->ensureConfigForEvent('e1');
        $cfg->ensureConfigForEvent('e2');
        $cfg->setActiveEventUid('e1');

        $controller = new LogoController($cfg);
        $logoFile = tempnam(sys_get_temp_dir(), 'logo');
        imagepng(imagecreatetruecolor(10, 10), $logoFile);
        $stream = fopen($logoFile, 'rb');
        $uploaded = new UploadedFile(new Stream($stream), 'logo.png', 'image/png', filesize($logoFile), UPLOAD_ERR_OK);
        $request = $this->createRequest('POST', '/logo.png');
        $request = $request->withUploadedFiles(['file' => $uploaded]);

        $controller->post($request, new Response());

        $cfg1 = json_decode($cfg->getJsonForEvent('e1'), true);
        $cfg2 = json_decode($cfg->getJsonForEvent('e2'), true);

        $this->assertSame('/logo-e1.png', $cfg1['logoPath']);
        $this->assertNull($cfg2['logoPath']);

        unlink($logoFile);
        unlink(dirname(__DIR__, 2) . '/data/logo-e1.png');
    }

    public function testGetWithDynamicFilename(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE active_event(event_uid TEXT PRIMARY KEY);');
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
                event_uid TEXT
            );
            SQL
        );
        $cfg = new ConfigService($pdo);
        $cfg->setActiveEventUid('dyn');

        $controller = new LogoController($cfg);
        $logoFile = tempnam(sys_get_temp_dir(), 'logo');
        imagepng(imagecreatetruecolor(10, 10), $logoFile);
        $stream = fopen($logoFile, 'rb');
        $uploaded = new UploadedFile(new Stream($stream), 'logo.png', 'image/png', filesize($logoFile), UPLOAD_ERR_OK);
        $request = $this->createRequest('POST', '/logo.png');
        $request = $request->withUploadedFiles(['file' => $uploaded]);

        $controller->post($request, new Response());

        $response = $controller->get($this->createRequest('GET', '/logo-dyn.png'), new Response());
        $this->assertEquals(200, $response->getStatusCode());

        unlink($logoFile);
        unlink(dirname(__DIR__, 2) . '/data/logo-dyn.png');
    }
}
