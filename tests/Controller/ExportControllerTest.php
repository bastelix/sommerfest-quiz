<?php

declare(strict_types=1);

namespace Tests\Controller;

use App\Controller\ExportController;
use App\Service\CatalogService;
use App\Service\ConfigService;
use App\Service\ResultService;
use App\Service\TeamService;
use App\Service\PhotoConsentService;
use App\Service\SummaryPhotoService;
use App\Service\EventService;
use PDO;
use Tests\TestCase;
use Slim\Psr7\Response;

class ExportControllerTest extends TestCase
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

    public function testExportIncludesEvents(): void
    {
        [$catalog, $config, $results, $teams, $consents, $summary, $events] = $this->createServices();
        $tmp = sys_get_temp_dir() . '/export_' . uniqid();
        mkdir($tmp, 0777, true);
        $this->assertDirectoryExists($tmp);

        $controller = new ExportController(
            $config,
            $catalog,
            $results,
            $teams,
            $consents,
            $summary,
            $events,
            $tmp,
            $tmp
        );
        $req = $this->createRequest('POST', '/export');
        $res = $controller->post($req, new Response());
        $this->assertEquals(204, $res->getStatusCode());
        $this->assertFileExists($tmp . '/events.json');
        $data = json_decode(file_get_contents($tmp . '/events.json'), true);
        $this->assertCount(1, $data);
        $this->assertSame('Event1', $data[0]['name']);

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($tmp, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $path) {
            if ($path->isDir()) {
                rmdir($path->getPathname());
            } else {
                unlink($path->getPathname());
            }
        }
        rmdir($tmp);
        $this->assertDirectoryDoesNotExist($tmp);
    }
}
