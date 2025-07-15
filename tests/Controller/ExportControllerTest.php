<?php

declare(strict_types=1);

namespace Tests\Controller;

use App\Controller\ExportController;
use App\Service\CatalogService;
use App\Service\ConfigService;
use App\Service\ResultService;
use App\Service\TeamService;
use App\Service\PhotoConsentService;
use App\Service\EventService;
use PDO;
use Tests\TestCase;
use Slim\Psr7\Response;

class ExportControllerTest extends TestCase
{
    private function createServices(): array
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE events(' .
            'uid TEXT PRIMARY KEY, name TEXT, start_date TEXT, end_date TEXT, description TEXT' .
            ');'
        );
        $pdo->exec('CREATE TABLE config(event_uid TEXT);');
        $pdo->exec("INSERT INTO events(uid,name) VALUES('ev1','Event1')");
        $pdo->exec("INSERT INTO config(event_uid) VALUES('ev1')");
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE catalogs(
                uid TEXT PRIMARY KEY,
                sort_order INTEGER UNIQUE NOT NULL,
                slug TEXT UNIQUE NOT NULL,
                file TEXT NOT NULL,
                name TEXT NOT NULL,
                description TEXT,
                qrcode_url TEXT,
                raetsel_buchstabe TEXT,
                comment TEXT,
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
                UNIQUE(catalog_uid, sort_order)
            );
            SQL
        );
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE teams(
                sort_order INTEGER UNIQUE NOT NULL,
                name TEXT NOT NULL,
                uid TEXT PRIMARY KEY,
                event_uid TEXT
            );
            SQL
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
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE photo_consents(
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                team TEXT NOT NULL,
                time INTEGER NOT NULL,
                event_uid TEXT
            );
            SQL
        );

        $cfg = new ConfigService($pdo);
        return [
            new CatalogService($pdo, $cfg),
            $cfg,
            new ResultService($pdo, $cfg),
            new TeamService($pdo, $cfg),
            new PhotoConsentService($pdo, $cfg),
            new EventService($pdo, $cfg),
            $pdo,
        ];
    }

    public function testExportIncludesEvents(): void
    {
        [$catalog, $config, $results, $teams, $consents, $events] = $this->createServices();
        $tmp = sys_get_temp_dir() . '/export_' . uniqid();
        mkdir($tmp, 0777, true);

        $controller = new ExportController($config, $catalog, $results, $teams, $consents, $events, $tmp, $tmp);
        $req = $this->createRequest('POST', '/export');
        $res = $controller->post($req, new Response());
        $this->assertEquals(204, $res->getStatusCode());
        $this->assertFileExists($tmp . '/events.json');
        $data = json_decode(file_get_contents($tmp . '/events.json'), true);
        $this->assertCount(1, $data);
        $this->assertSame('Event1', $data[0]['name']);

        unlink($tmp . '/events.json');
        unlink($tmp . '/config.json');
        unlink($tmp . '/teams.json');
        unlink($tmp . '/results.json');
        unlink($tmp . '/photo_consents.json');
        rmdir($tmp . '/kataloge');
        rmdir($tmp);
    }
}
