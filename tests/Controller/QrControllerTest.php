<?php

declare(strict_types=1);

namespace Tests\Controller;

use Tests\TestCase;
use Slim\Psr7\Response;
use Slim\Psr7\UploadedFile;
use App\Service\QrCodeService;

class QrControllerTest extends TestCase
{
    public function testQrImageIsGenerated(): void
    {
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/qr.png')->withQueryParams(['t' => 'Test']);
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('image/png', $response->getHeaderLine('Content-Type'));
        $this->assertNotEmpty((string) $response->getBody());
    }

    public function testQrImageFallbackWhenTextMissing(): void
    {
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/qr.png');
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('image/png', $response->getHeaderLine('Content-Type'));
        $this->assertNotEmpty((string) $response->getBody());
    }

    public function testQrImageSvgFormat(): void
    {
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/qr.png')
            ->withQueryParams(['t' => 'Demo', 'format' => 'svg']);
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('image/svg+xml', $response->getHeaderLine('Content-Type'));
        $this->assertNotEmpty((string) $response->getBody());
    }

    public function testQrImageAllowsCustomColors(): void
    {
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/qr.png')
            ->withQueryParams([
                't' => 'Demo',
                'fg' => 'ff0000',
                'bg' => '00ff00',
                'logoText' => 'TEST',
            ]);
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('image/png', $response->getHeaderLine('Content-Type'));
        $this->assertNotEmpty((string) $response->getBody());
    }

    public function testCatalogQrDefaults(): void
    {
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/qr/catalog');
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('image/png', $response->getHeaderLine('Content-Type'));
        $this->assertNotEmpty((string) $response->getBody());
    }

    public function testEventQrDefaults(): void
    {
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/qr/event');
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('image/png', $response->getHeaderLine('Content-Type'));
        $this->assertNotEmpty((string) $response->getBody());
    }

    public function testTeamQrDefaults(): void
    {
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/qr/team');
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('image/png', $response->getHeaderLine('Content-Type'));
        $this->assertNotEmpty((string) $response->getBody());
    }

    public function testTeamQrSvgFormat(): void
    {
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/qr/team')
            ->withQueryParams(['format' => 'svg']);
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('image/svg+xml', $response->getHeaderLine('Content-Type'));
        $this->assertNotEmpty((string) $response->getBody());
    }

    public function testQrPdfIsGenerated(): void
    {
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/qr.pdf')->withQueryParams(['t' => 'Test']);
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/pdf', $response->getHeaderLine('Content-Type'));
        $pdf = (string) $response->getBody();
        $this->assertNotEmpty($pdf);
    }

    public function testPdfUsesUploadedLogo(): void
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
        $pdo->exec(
            'CREATE TABLE events(' .
            'uid TEXT PRIMARY KEY, name TEXT, start_date TEXT, end_date TEXT, description TEXT' .
            ');'
        );
        $pdo->exec("INSERT INTO events(uid,name,description) VALUES('1','Event','Sub')");
        $cfg = new \App\Service\ConfigService($pdo);
        $cfg->setActiveEventUid('1');
        $teams = new \App\Service\TeamService($pdo, $cfg);
        $events = new \App\Service\EventService($pdo);
        $catalogs = new \App\Service\CatalogService($pdo, $cfg);
        $qr = new \App\Controller\QrController($cfg, $teams, $events, $catalogs, new QrCodeService());
        $logo = new \App\Controller\LogoController($cfg);

        $req = $this->createRequest('GET', '/qr.pdf')->withQueryParams(['t' => 'Demo']);
        $initial = $qr->pdf($req, new Response());
        $original = (string)$initial->getBody();
        $this->assertStringContainsString('Event', $original);

        $logoFile = tempnam(sys_get_temp_dir(), 'logo');
        imagepng(imagecreatetruecolor(10, 10), $logoFile);
        $uploaded = new UploadedFile(
            $logoFile,
            'logo.png',
            'image/png',
            filesize($logoFile),
            UPLOAD_ERR_OK
        );
        $upReq = $this->createRequest('POST', '/logo.png')->withUploadedFiles(['file' => $uploaded]);
        $logo->post($upReq, new Response());

        $updated = $qr->pdf($req, new Response());
        $this->assertNotEquals($original, (string)$updated->getBody());
        $this->assertStringContainsString('Event', (string)$updated->getBody());

        unlink($logoFile);
        unlink(dirname(__DIR__, 2) . '/data/logo.png');
    }

    public function testInvitePlaceholderIsReplaced(): void
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
        $pdo->exec(
            'CREATE TABLE events(' .
            'uid TEXT PRIMARY KEY, name TEXT, start_date TEXT, end_date TEXT, description TEXT' .
            ');'
        );
        $pdo->exec(
            "INSERT INTO events(uid,name,start_date,end_date,description) " .
            "VALUES('1','Event','2024-01-01T10:00','2024-01-01T12:00','Desc')"
        );
        $pdo->exec(
            "INSERT INTO config(inviteText, event_uid) VALUES(" .
            "'Hallo [TEAM], willkommen zu [EVENT_NAME] am [EVENT_START] " .
            "bis [EVENT_END] - [EVENT_DESCRIPTION]!','1')"
        );

        $cfg = new \App\Service\ConfigService($pdo);
        $cfg->setActiveEventUid('1');
        $teams = new \App\Service\TeamService($pdo, $cfg);
        $events = new \App\Service\EventService($pdo);
        $catalogs = new \App\Service\CatalogService($pdo, $cfg);
        $qr  = new \App\Controller\QrController($cfg, $teams, $events, $catalogs, new QrCodeService());

        $req = $this->createRequest('GET', '/qr.pdf')->withQueryParams(['t' => 'Demo']);
        $response = $qr->pdf($req, new Response());
        $pdf = (string)$response->getBody();

        $expected = 'Hallo Demo, willkommen zu Event am 2024-01-01T10:00 bis 2024-01-01T12:00 - Desc!';
        $this->assertStringContainsString($expected, $pdf);
        $this->assertStringContainsString('Event', $pdf);
    }

    public function testInviteTextIsSanitizedInPdf(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE config(
                inviteText TEXT,
                event_uid TEXT
            );
            SQL
        );
        $pdo->exec(
            'CREATE TABLE events(' .
            'uid TEXT PRIMARY KEY, name TEXT, start_date TEXT, end_date TEXT, description TEXT' .
            ');'
        );
        $pdo->exec("INSERT INTO events(uid,name,description) VALUES('1','Event','Desc')");
        $pdo->exec(
            "INSERT INTO config(inviteText, event_uid) VALUES('<script>evil()</script>Hi [TEAM]','1')"
        );

        $cfg = new \App\Service\ConfigService($pdo);
        $cfg->setActiveEventUid('1');
        $teams = new \App\Service\TeamService($pdo, $cfg);
        $events = new \App\Service\EventService($pdo);
        $catalogs = new \App\Service\CatalogService($pdo, $cfg);
        $qr  = new \App\Controller\QrController($cfg, $teams, $events, $catalogs, new QrCodeService());

        $req = $this->createRequest('GET', '/qr.pdf')->withQueryParams(['t' => 'Demo']);
        $response = $qr->pdf($req, new Response());
        $pdf = (string)$response->getBody();

        $this->assertStringNotContainsString('<script', $pdf);
    }

    public function testAllInvitationsPdfIsGenerated(): void
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
        $pdo->exec(
            'CREATE TABLE events(' .
            'uid TEXT PRIMARY KEY, name TEXT, start_date TEXT, end_date TEXT, description TEXT' .
            ');'
        );
        $pdo->exec("INSERT INTO events(uid,name) VALUES('1','Event')");
        $pdo->exec(
            'CREATE TABLE teams(' .
            'sort_order INTEGER UNIQUE NOT NULL, name TEXT NOT NULL, uid TEXT PRIMARY KEY, event_uid TEXT' .
            ');'
        );
        $pdo->exec(
            "INSERT INTO teams(sort_order,name,uid,event_uid) VALUES(1,'A','1','1'),(2,'B','2','1')"
        );

        $cfg = new \App\Service\ConfigService($pdo);
        $cfg->setActiveEventUid('1');
        $teams = new \App\Service\TeamService($pdo, $cfg);
        $events = new \App\Service\EventService($pdo);
        $catalogs = new \App\Service\CatalogService($pdo, $cfg);
        $qr  = new \App\Controller\QrController($cfg, $teams, $events, $catalogs, new QrCodeService());

        $req = $this->createRequest('GET', '/invites.pdf');
        $response = $qr->pdfAll($req, new Response());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/pdf', $response->getHeaderLine('Content-Type'));
        $pdf = (string)$response->getBody();
        $this->assertNotEmpty($pdf);
        $this->assertEquals(2, substr_count($pdf, 'Event'));
    }

    public function testActiveEventSwitchUpdatesPdf(): void
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
        $pdo->exec(
            'CREATE TABLE events(' .
            'uid TEXT PRIMARY KEY, name TEXT, start_date TEXT, end_date TEXT, description TEXT' .
            ');'
        );
        $pdo->exec("INSERT INTO events(uid,name,description) VALUES('1','First','A'),('2','Second','B')");

        $cfg = new \App\Service\ConfigService($pdo);
        $teams = new \App\Service\TeamService($pdo, $cfg);
        $events = new \App\Service\EventService($pdo);
        $catalogs = new \App\Service\CatalogService($pdo, $cfg);
        $qr = new \App\Controller\QrController($cfg, $teams, $events, $catalogs, new QrCodeService());

        $cfg->setActiveEventUid('1');
        $req = $this->createRequest('GET', '/qr.pdf')->withQueryParams(['t' => 'Demo']);
        $res1 = $qr->pdf($req, new Response());
        $pdf1 = (string)$res1->getBody();
        $this->assertStringContainsString('First', $pdf1);

        $cfg->setActiveEventUid('2');
        $res2 = $qr->pdf($req, new Response());
        $pdf2 = (string)$res2->getBody();
        $this->assertStringContainsString('Second', $pdf2);
        $this->assertNotEquals($pdf1, $pdf2);
    }
}
