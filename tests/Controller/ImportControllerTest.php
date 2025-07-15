<?php

declare(strict_types=1);

namespace Tests\Controller;

use App\Controller\ImportController;
use App\Service\CatalogService;
use App\Service\ConfigService;
use App\Service\ResultService;
use App\Service\TeamService;
use App\Service\PhotoConsentService;
use App\Service\SummaryPhotoService;
use App\Service\EventService;
use Tests\TestCase;
use Slim\Psr7\Response;
use PDO;

class ImportControllerTest extends TestCase
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
        $pdo->exec(
            'CREATE TABLE summary_photos(' .
            'id INTEGER PRIMARY KEY AUTOINCREMENT,' .
            'name TEXT,path TEXT,time INTEGER,event_uid TEXT);'
        );

        $cfg = new ConfigService($pdo);
        return [
            new CatalogService($pdo, $cfg),
            $cfg,
            new ResultService($pdo, $cfg),
            new TeamService($pdo, $cfg),
            new PhotoConsentService($pdo, $cfg),
            new SummaryPhotoService($pdo, $cfg),
            new EventService($pdo, $cfg),
            $pdo,
        ];
    }

    public function testImport(): void
    {
        [$catalog, $config, $results, $teams, $consents, $summary, $events] = $this->createServices();
        $tmp = sys_get_temp_dir() . '/import_' . uniqid();
        mkdir($tmp . '/kataloge', 0777, true);
        file_put_contents($tmp . '/kataloge/catalogs.json', json_encode([
            ['uid' => 'u1', 'id' => 'c1', 'slug' => 'c1', 'file' => 'c1.json', 'name' => 'Cat']
        ], JSON_PRETTY_PRINT));
        file_put_contents($tmp . '/kataloge/c1.json', json_encode([
            ['type' => 'text', 'prompt' => 'Q']
        ], JSON_PRETTY_PRINT));
        file_put_contents($tmp . '/photo_consents.json', json_encode([
            ['team' => 'T1', 'time' => 1]
        ], JSON_PRETTY_PRINT));
        file_put_contents($tmp . '/events.json', json_encode([
            ['uid' => 'ev2', 'name' => 'Event2']
        ], JSON_PRETTY_PRINT));

        $controller = new ImportController(
            $catalog,
            $config,
            $results,
            $teams,
            $consents,
            $summary,
            $events,
            $tmp,
            $tmp
        );
        $request = $this->createRequest('POST', '/import');
        $response = $controller->post($request, new Response());
        $this->assertEquals(204, $response->getStatusCode());
        $questions = json_decode($catalog->read('c1.json'), true);
        $this->assertCount(1, $questions);
        $this->assertSame('Q', $questions[0]['prompt']);
        $consentRows = $consents->getAll();
        $this->assertCount(1, $consentRows);
        $this->assertSame('T1', $consentRows[0]['team']);
        $eventRows = $events->getAll();
        $this->assertCount(1, $eventRows);
        $this->assertSame('Event2', $eventRows[0]['name']);

        unlink($tmp . '/kataloge/c1.json');
        unlink($tmp . '/kataloge/catalogs.json');
        unlink($tmp . '/events.json');
        unlink($tmp . '/photo_consents.json');
        rmdir($tmp . '/kataloge');
        rmdir($tmp);
    }
}
