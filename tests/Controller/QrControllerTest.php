<?php

declare(strict_types=1);

namespace Tests\Controller;

use Tests\TestCase;
use Slim\Psr7\Response;
use Slim\Psr7\UploadedFile;
use App\Service\QrCodeService;
use Psr\Http\Message\ServerRequestInterface as Request;

class QrControllerTest extends TestCase
{
    protected function setUp(): void {
        parent::setUp();
        putenv('MAIN_DOMAIN=example.com');
        $_ENV['MAIN_DOMAIN'] = 'example.com';
    }

    protected function tearDown(): void {
        putenv('MAIN_DOMAIN');
        unset($_ENV['MAIN_DOMAIN']);
        parent::tearDown();
    }

    protected function createRequest(
        string $method,
        string $path,
        array $headers = ['HTTP_ACCEPT' => 'text/html'],
        ?array $cookies = null,
        array $serverParams = []
    ): Request {
        $request = parent::createRequest($method, $path, $headers, $cookies, $serverParams);
        $uri = $request->getUri()->withHost('example.com');
        return $request->withHeader('Host', 'example.com')->withUri($uri);
    }

    public function testQrImageIsGenerated(): void {
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/qr.png')->withQueryParams(['t' => 'Test']);
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('image/png', $response->getHeaderLine('Content-Type'));
        $this->assertNotEmpty((string) $response->getBody());
        $this->assertSame('public, max-age=31536000, immutable', $response->getHeaderLine('Cache-Control'));
        $this->assertMatchesRegularExpression('/^"[0-9a-f]{64}"$/', $response->getHeaderLine('ETag'));
        $this->assertStringEndsWith('GMT', $response->getHeaderLine('Last-Modified'));
    }

    public function testQrImageFallbackWhenTextMissing(): void {
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/qr.png');
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('image/png', $response->getHeaderLine('Content-Type'));
        $this->assertNotEmpty((string) $response->getBody());
        $this->assertSame('public, max-age=31536000, immutable', $response->getHeaderLine('Cache-Control'));
        $this->assertMatchesRegularExpression('/^"[0-9a-f]{64}"$/', $response->getHeaderLine('ETag'));
        $this->assertStringEndsWith('GMT', $response->getHeaderLine('Last-Modified'));
    }

    public function testQrImageSvgFormat(): void {
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/qr.png')
            ->withQueryParams(['t' => 'Demo', 'format' => 'svg']);
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('image/svg+xml', $response->getHeaderLine('Content-Type'));
        $this->assertNotEmpty((string) $response->getBody());
    }

    public function testQrImageAllowsCustomColors(): void {
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/qr.png')
            ->withQueryParams([
                't' => 'Demo',
                'fg' => 'ff0000',
                'bg' => '00ff00',
            ]);
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('image/png', $response->getHeaderLine('Content-Type'));
        $this->assertNotEmpty((string) $response->getBody());
        $this->assertSame('public, max-age=31536000, immutable', $response->getHeaderLine('Cache-Control'));
        $this->assertMatchesRegularExpression('/^"[0-9a-f]{64}"$/', $response->getHeaderLine('ETag'));
        $this->assertStringEndsWith('GMT', $response->getHeaderLine('Last-Modified'));
    }

    public function testCatalogQrDefaults(): void {
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/qr/catalog');
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('image/png', $response->getHeaderLine('Content-Type'));
        $this->assertNotEmpty((string) $response->getBody());
        $this->assertSame('public, max-age=31536000, immutable', $response->getHeaderLine('Cache-Control'));
        $this->assertMatchesRegularExpression('/^"[0-9a-f]{64}"$/', $response->getHeaderLine('ETag'));
        $this->assertStringEndsWith('GMT', $response->getHeaderLine('Last-Modified'));
    }

    public function testEventQrDefaults(): void {
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/qr/event')->withQueryParams(['event' => '1']);
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('image/png', $response->getHeaderLine('Content-Type'));
        $this->assertNotEmpty((string) $response->getBody());
        $this->assertSame('public, max-age=31536000, immutable', $response->getHeaderLine('Cache-Control'));
        $this->assertMatchesRegularExpression('/^"[0-9a-f]{64}"$/', $response->getHeaderLine('ETag'));
        $this->assertStringEndsWith('GMT', $response->getHeaderLine('Last-Modified'));
    }

    public function testTeamQrDefaults(): void {
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/qr/team');
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('image/png', $response->getHeaderLine('Content-Type'));
        $this->assertNotEmpty((string) $response->getBody());
        $this->assertSame('public, max-age=31536000, immutable', $response->getHeaderLine('Cache-Control'));
        $this->assertMatchesRegularExpression('/^"[0-9a-f]{64}"$/', $response->getHeaderLine('ETag'));
        $this->assertStringEndsWith('GMT', $response->getHeaderLine('Last-Modified'));
    }

    public function testTeamQrConditionalRequest(): void {
        $app = $this->getAppInstance();
        $initial = $app->handle($this->createRequest('GET', '/qr/team'));

        $etag = $initial->getHeaderLine('ETag');
        $this->assertNotSame('', $etag);

        $response = $app->handle(
            $this->createRequest('GET', '/qr/team')->withHeader('If-None-Match', $etag)
        );

        $this->assertSame(304, $response->getStatusCode());
        $this->assertSame('', (string) $response->getBody());
        $this->assertSame('public, max-age=31536000, immutable', $response->getHeaderLine('Cache-Control'));
        $this->assertSame($etag, $response->getHeaderLine('ETag'));
    }

    public function testTeamQrSvgFormat(): void {
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/qr/team')
            ->withQueryParams(['format' => 'svg']);
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('image/svg+xml', $response->getHeaderLine('Content-Type'));
        $this->assertNotEmpty((string) $response->getBody());
    }

    public function testTeamQrWebpLogo(): void {
        $logoFile = dirname(__DIR__, 2) . '/data/test-logo.webp';
        imagewebp(imagecreatetruecolor(10, 10), $logoFile);

        $pdo = $this->getDatabase();
        $cfg = new \App\Service\ConfigService($pdo);
        $cfg->saveConfig(['qrLogoPath' => '/test-logo.webp']);

        $app = $this->getAppInstance();
        $response = $app->handle($this->createRequest('GET', '/qr/team'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('image/png', $response->getHeaderLine('Content-Type'));
        $this->assertNotEmpty((string) $response->getBody());

        @unlink($logoFile);
    }

    public function testQrPdfIsGenerated(): void {
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/qr.pdf')->withQueryParams(['t' => 'Test', 'event' => '1']);
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/pdf', $response->getHeaderLine('Content-Type'));
        $pdf = (string) $response->getBody();
        $this->assertNotEmpty($pdf);
    }

    public function testPdfUsesUploadedLogo(): void {
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
            "INSERT INTO events(uid,slug,name,description) VALUES('1','event','Event','Sub')"
        );
        $cfg = new \App\Service\ConfigService($pdo);
        $cfg->setActiveEventUid('1');
        $teams = new \App\Service\TeamService($pdo, $cfg);
        $events = new \App\Service\EventService($pdo);
        $catalogs = new \App\Service\CatalogService($pdo, $cfg);
        $results = new \App\Service\ResultService($pdo, $cfg);
        $qr = new \App\Controller\QrController($cfg, $teams, $events, $catalogs, new QrCodeService(), $results);
        $logo = new \App\Controller\LogoController($cfg);

        $req = $this->createRequest('GET', '/qr.pdf')->withQueryParams(['t' => 'Demo', 'event' => '1']);
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
        unlink(sys_get_temp_dir() . '/uploads/logo.png');
    }

    public function testInvitePlaceholderIsReplaced(): void {
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
            "INSERT INTO events(uid,slug,name,start_date,end_date,description) " .
            "VALUES('1','event','Event','2024-01-01T10:00','2024-01-01T12:00','Desc')"
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
        $results = new \App\Service\ResultService($pdo, $cfg);
        $qr  = new \App\Controller\QrController($cfg, $teams, $events, $catalogs, new QrCodeService(), $results);

        $req = $this->createRequest('GET', '/qr.pdf')->withQueryParams(['t' => 'Demo', 'event' => '1']);
        $response = $qr->pdf($req, new Response());
        $pdf = (string)$response->getBody();

        $expected = 'Hallo Demo, willkommen zu Event am 2024-01-01T10:00 bis 2024-01-01T12:00 - Desc!';
        $this->assertStringContainsString($expected, $pdf);
        $this->assertStringContainsString('Event', $pdf);
    }

    public function testInviteTextIsSanitizedInPdf(): void {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE config(
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
        $pdo->exec("INSERT INTO events(uid,slug,name,description) VALUES('1','event','Event','Desc')");
        $pdo->exec(
            "INSERT INTO config(inviteText, event_uid) VALUES('<script>evil()</script>Hi [TEAM]','1')"
        );

        $cfg = new \App\Service\ConfigService($pdo);
        $cfg->setActiveEventUid('1');
        $teams = new \App\Service\TeamService($pdo, $cfg);
        $events = new \App\Service\EventService($pdo);
        $catalogs = new \App\Service\CatalogService($pdo, $cfg);
        $results = new \App\Service\ResultService($pdo, $cfg);
        $qr  = new \App\Controller\QrController($cfg, $teams, $events, $catalogs, new QrCodeService(), $results);

        $req = $this->createRequest('GET', '/qr.pdf')->withQueryParams(['t' => 'Demo', 'event' => '1']);
        $response = $qr->pdf($req, new Response());
        $pdf = (string)$response->getBody();

        $this->assertStringNotContainsString('<script', $pdf);
    }

    public function testAllInvitationsPdfIsGenerated(): void {
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
        $pdo->exec("INSERT INTO events(uid,slug,name) VALUES('1','event','Event')");
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
        $results = new \App\Service\ResultService($pdo, $cfg);
        $qr  = new \App\Controller\QrController($cfg, $teams, $events, $catalogs, new QrCodeService(), $results);

        $req = $this->createRequest('GET', '/invites.pdf?event=1');
        $response = $qr->pdfAll($req, new Response());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/pdf', $response->getHeaderLine('Content-Type'));
        $pdf = (string)$response->getBody();
        $this->assertNotEmpty($pdf);
        $this->assertEquals(2, substr_count($pdf, 'Event'));
    }

    public function testInvitesPdfUsesResultsWhenTeamsMissing(): void {
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
            "INSERT INTO events(uid,slug,name) VALUES('1','event','Event')"
        );
        $pdo->exec(
            'CREATE TABLE teams(' .
            'sort_order INTEGER UNIQUE NOT NULL, name TEXT NOT NULL, uid TEXT PRIMARY KEY, event_uid TEXT' .
            ');'
        );
        $pdo->exec(
            'CREATE TABLE catalogs(' .
            'uid TEXT PRIMARY KEY, sort_order INTEGER, slug TEXT, file TEXT, name TEXT, event_uid TEXT' .
            ');'
        );
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE results(
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                catalog TEXT NOT NULL,
                attempt INTEGER,
                correct INTEGER,
                total INTEGER,
                time INTEGER,
                puzzleTime INTEGER,
                photo TEXT,
                event_uid TEXT
            );
            SQL
        );
        $pdo->exec(
            "INSERT INTO results(name,catalog,attempt,correct,total,time,event_uid) VALUES" .
            "('A','cat',1,0,0,0,'1'),('B','cat',1,0,0,0,'1')"
        );

        $cfg = new \App\Service\ConfigService($pdo);
        $cfg->setActiveEventUid('1');
        $teams = new \App\Service\TeamService($pdo, $cfg);
        $events = new \App\Service\EventService($pdo);
        $catalogs = new \App\Service\CatalogService($pdo, $cfg);
        $results = new \App\Service\ResultService($pdo, $cfg);
        $qr  = new \App\Controller\QrController($cfg, $teams, $events, $catalogs, new QrCodeService(), $results);

        $req = $this->createRequest('GET', '/invites.pdf?event=1');
        $response = $qr->pdfAll($req, new Response());

        $this->assertSame(200, $response->getStatusCode());
        $pdf = (string)$response->getBody();
        $this->assertNotEmpty($pdf);
        $this->assertEquals(2, substr_count($pdf, 'Event'));
    }

    public function testInvitesPdfReturnsErrorWhenNoTeams(): void {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE config(
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
            "INSERT INTO events(uid,slug,name) VALUES('1','event','Event')"
        );
        $pdo->exec(
            'CREATE TABLE teams(' .
            'sort_order INTEGER UNIQUE NOT NULL, name TEXT NOT NULL, uid TEXT PRIMARY KEY, event_uid TEXT' .
            ');'
        );
        $pdo->exec(
            'CREATE TABLE catalogs(' .
            'uid TEXT PRIMARY KEY, sort_order INTEGER, slug TEXT, file TEXT, name TEXT, event_uid TEXT' .
            ');'
        );
        $pdo->exec(
            'CREATE TABLE results(' .
            'id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, catalog TEXT, attempt INTEGER, correct INTEGER, ' .
            'total INTEGER, time INTEGER, puzzleTime INTEGER, photo TEXT, event_uid TEXT' .
            ');'
        );

        $cfg = new \App\Service\ConfigService($pdo);
        $cfg->setActiveEventUid('1');
        $teams = new \App\Service\TeamService($pdo, $cfg);
        $events = new \App\Service\EventService($pdo);
        $catalogs = new \App\Service\CatalogService($pdo, $cfg);
        $results = new \App\Service\ResultService($pdo, $cfg);
        $qr  = new \App\Controller\QrController($cfg, $teams, $events, $catalogs, new QrCodeService(), $results);

        $req = $this->createRequest('GET', '/invites.pdf?event=1');
        $response = $qr->pdfAll($req, new Response());

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testActiveEventSwitchUpdatesPdf(): void {
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
            "INSERT INTO events(uid,slug,name,description) VALUES" .
            "('1','one','First','A'),('2','two','Second','B')"
        );

        $cfg = new \App\Service\ConfigService($pdo);
        $teams = new \App\Service\TeamService($pdo, $cfg);
        $events = new \App\Service\EventService($pdo);
        $catalogs = new \App\Service\CatalogService($pdo, $cfg);
        $results = new \App\Service\ResultService($pdo, $cfg);
        $qr = new \App\Controller\QrController($cfg, $teams, $events, $catalogs, new QrCodeService(), $results);

        $cfg->setActiveEventUid('1');
        $req1 = $this->createRequest('GET', '/qr.pdf')->withQueryParams(['t' => 'Demo', 'event' => '1']);
        $res1 = $qr->pdf($req1, new Response());
        $pdf1 = (string)$res1->getBody();
        $this->assertStringContainsString('First', $pdf1);

        $cfg->setActiveEventUid('2');
        $req2 = $this->createRequest('GET', '/qr.pdf')->withQueryParams(['t' => 'Demo', 'event' => '2']);
        $res2 = $qr->pdf($req2, new Response());
        $pdf2 = (string)$res2->getBody();
        $this->assertStringContainsString('Second', $pdf2);
        $this->assertNotEquals($pdf1, $pdf2);
    }
}
