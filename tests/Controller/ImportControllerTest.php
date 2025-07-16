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
        $pdo->exec("INSERT INTO events(uid,name) VALUES('ev1','Event1')");
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
}
