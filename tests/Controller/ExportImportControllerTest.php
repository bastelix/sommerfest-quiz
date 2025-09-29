<?php

declare(strict_types=1);

namespace Tests\Controller;

use App\Controller\ExportController;
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

class ExportImportControllerTest extends TestCase
{
    private function createServices(): array {
        $pdo = $this->createDatabase();
        $pdo->exec("INSERT INTO events(uid,slug,name) VALUES('ev1','ev1','Event1')");
        $pdo->exec("INSERT INTO config(event_uid) VALUES('ev1')");

        $cfg = new ConfigService($pdo);
        return [
            new CatalogService($pdo, $cfg),
            $cfg,
            new ResultService($pdo),
            new TeamService($pdo, $cfg),
            new PhotoConsentService($pdo, $cfg),
            new SummaryPhotoService($pdo, $cfg),
            new EventService($pdo, $cfg),
            $pdo,
        ];
    }

    public function testQuestionResultsRoundTrip(): void {
        [$catalog, $config, $results, $teams, $consents, $summary, $events, $pdo] = $this->createServices();
        // prepare catalog and questions
        $pdo->exec("INSERT INTO catalogs(uid,sort_order,slug,file,name) VALUES('u1',1,'c1','c1.json','Cat')");
        $pdo->exec("INSERT INTO questions(catalog_uid,sort_order,type,prompt) VALUES('u1',1,'text','Q1')");
        $pdo->exec("INSERT INTO questions(catalog_uid,sort_order,type,prompt) VALUES('u1',2,'text','Q2')");

        // add a result which also stores question_results
        $results->add(['name' => 'Team', 'catalog' => 'c1', 'correct' => 2, 'total' => 2]);

        $dir = sys_get_temp_dir() . '/round_' . uniqid();
        mkdir($dir . '/kataloge', 0777, true);

        $export = new ExportController(
            $config,
            $catalog,
            $results,
            $teams,
            $consents,
            $summary,
            $events,
            $dir,
            $dir
        );
        $ref = new \ReflectionMethod(ExportController::class, 'exportToDir');
        $ref->setAccessible(true);
        $ref->invoke($export, $dir);

        $this->assertFileExists($dir . '/question_results.json');

        // clear database
        $results->clear();

        $import = new ImportController(
            $catalog,
            $config,
            $results,
            $teams,
            $consents,
            $summary,
            $events,
            $dir,
            $dir
        );
        $ref2 = new \ReflectionMethod(ImportController::class, 'importFromDir');
        $ref2->setAccessible(true);
        $ref2->invoke($import, $dir, new Response());

        $count = (int) $pdo->query('SELECT COUNT(*) FROM question_results')->fetchColumn();
        $this->assertSame(2, $count);

        // cleanup
        unlink($dir . '/question_results.json');
        unlink($dir . '/results.json');
        unlink($dir . '/teams.json');
        unlink($dir . '/photo_consents.json');
        unlink($dir . '/config.json');
        unlink($dir . '/kataloge/c1.json');
        unlink($dir . '/kataloge/catalogs.json');
        rmdir($dir . '/kataloge');
        rmdir($dir);
    }
}
