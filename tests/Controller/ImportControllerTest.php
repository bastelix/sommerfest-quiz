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
        $pdo = $this->createDatabase();
        $pdo->exec("INSERT INTO events(uid,slug,name) VALUES('ev1','ev1','Event1')");
        $pdo->exec("INSERT INTO config(event_uid) VALUES('ev1')");

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

    public function testImportTwiceDoesNotDuplicateSummaryPhotos(): void
    {
        [
            $catalog,
            $config,
            $results,
            $teams,
            $consents,
            $summary,
            $events,
            $pdo
        ] = $this->createServices();

        $tmp = sys_get_temp_dir() . '/import_' . uniqid();
        mkdir($tmp . '/kataloge', 0777, true);
        file_put_contents($tmp . '/kataloge/catalogs.json', json_encode([], JSON_PRETTY_PRINT));
        file_put_contents(
            $tmp . '/summary_photos.json',
            json_encode([
                ['name' => 'A', 'path' => '/a.jpg', 'time' => 1],
                ['name' => 'B', 'path' => '/b.jpg', 'time' => 2],
            ], JSON_PRETTY_PRINT)
        );

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

        $controller->post($request, new Response());
        $count = (int) $pdo->query('SELECT COUNT(*) FROM summary_photos')->fetchColumn();
        $this->assertSame(2, $count);

        $controller->post($request, new Response());
        $count2 = (int) $pdo->query('SELECT COUNT(*) FROM summary_photos')->fetchColumn();
        $this->assertSame(2, $count2);

        unlink($tmp . '/summary_photos.json');
        unlink($tmp . '/kataloge/catalogs.json');
        rmdir($tmp . '/kataloge');
        rmdir($tmp);
    }

    public function testImportRejectsInvalidName(): void
    {
        [$catalog, $config, $results, $teams, $consents, $summary, $events] = $this->createServices();
        $tmp = sys_get_temp_dir() . '/import_' . uniqid();
        mkdir($tmp, 0777, true);

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

        $request = $this->createRequest('POST', '/import/../etc');
        $response = $controller->import($request, new Response(), ['name' => '../etc']);

        $this->assertEquals(400, $response->getStatusCode());

        rmdir($tmp);
    }

    public function testImportRejectsInvalidJsonFile(): void
    {
        [$catalog, $config, $results, $teams, $consents, $summary, $events] = $this->createServices();
        $tmp = sys_get_temp_dir() . '/import_' . uniqid();
        mkdir($tmp . '/kataloge', 0777, true);
        file_put_contents($tmp . '/kataloge/catalogs.json', '{invalid');

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

        $this->assertEquals(400, $response->getStatusCode());
        $err = json_decode((string) $response->getBody(), true);
        $this->assertIsArray($err);
        $this->assertArrayHasKey('error', $err);

        unlink($tmp . '/kataloge/catalogs.json');
        rmdir($tmp . '/kataloge');
        rmdir($tmp);
    }

    public function testRestoreDefaults(): void
    {
        [$catalog, $config, $results, $teams, $consents, $summary, $events] = $this->createServices();
        $base = sys_get_temp_dir() . '/import_' . uniqid();
        $default = dirname($base) . '/data-default';
        mkdir($default . '/kataloge', 0777, true);
        file_put_contents($default . '/kataloge/catalogs.json', json_encode([
            ['uid' => 'u1', 'id' => 'c1', 'slug' => 'c1', 'file' => 'c1.json', 'name' => 'Cat']
        ], JSON_PRETTY_PRINT));
        file_put_contents($default . '/kataloge/c1.json', json_encode([
            ['type' => 'text', 'prompt' => 'Q']
        ], JSON_PRETTY_PRINT));

        $controller = new ImportController(
            $catalog,
            $config,
            $results,
            $teams,
            $consents,
            $summary,
            $events,
            $base,
            $base
        );
        $request = $this->createRequest('POST', '/restore-default');
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, '{}');
        rewind($stream);
        $stream = (new \Slim\Psr7\Factory\StreamFactory())->createStreamFromResource($stream);
        $request = $request->withBody($stream);
        $response = $controller->restoreDefaults($request, new Response());
        $this->assertEquals(204, $response->getStatusCode());

        $questions = json_decode($catalog->read('c1.json'), true);
        $this->assertCount(1, $questions);
        $this->assertSame('Q', $questions[0]['prompt']);

        unlink($default . '/kataloge/c1.json');
        unlink($default . '/kataloge/catalogs.json');
        rmdir($default . '/kataloge');
        rmdir($default);
    }

    public function testRestoreDefaultsRejectsInvalidJson(): void
    {
        [$catalog, $config, $results, $teams, $consents, $summary, $events] = $this->createServices();
        $base = sys_get_temp_dir() . '/import_' . uniqid();
        mkdir($base, 0777, true);

        $controller = new ImportController(
            $catalog,
            $config,
            $results,
            $teams,
            $consents,
            $summary,
            $events,
            $base,
            $base
        );
        $request = $this->createRequest('POST', '/restore-default');
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, '{invalid');
        rewind($stream);
        $stream = (new \Slim\Psr7\Factory\StreamFactory())->createStreamFromResource($stream);
        $request = $request->withBody($stream);

        $response = $controller->restoreDefaults($request, new Response());

        $this->assertEquals(400, $response->getStatusCode());
        $err = json_decode((string) $response->getBody(), true);
        $this->assertIsArray($err);
        $this->assertArrayHasKey('error', $err);

        rmdir($base);
    }
}
