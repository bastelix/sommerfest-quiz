<?php
declare(strict_types=1);

namespace Tests\Controller;

use App\Controller\ImportController;
use App\Service\CatalogService;
use App\Service\ConfigService;
use App\Service\ResultService;
use App\Service\TeamService;
use App\Service\PhotoConsentService;
use Tests\TestCase;
use Slim\Psr7\Response;
use PDO;

class ImportControllerTest extends TestCase
{
    private function createServices(): array
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE config(displayErrorDetails INTEGER, QRUser INTEGER, logoPath TEXT, pageTitle TEXT, header TEXT, subheader TEXT, backgroundColor TEXT, buttonColor TEXT, CheckAnswerButton TEXT, adminUser TEXT, adminPass TEXT, QRRestrict INTEGER, competitionMode INTEGER, teamResults INTEGER, photoUpload INTEGER, puzzleWordEnabled INTEGER, puzzleWord TEXT, puzzleFeedback TEXT, inviteText TEXT);');
        $pdo->exec('CREATE TABLE catalogs(uid TEXT PRIMARY KEY, sort_order INTEGER UNIQUE NOT NULL, slug TEXT UNIQUE NOT NULL, file TEXT NOT NULL, name TEXT NOT NULL, description TEXT, qrcode_url TEXT, raetsel_buchstabe TEXT, comment TEXT);');
        $pdo->exec('CREATE TABLE questions(id INTEGER PRIMARY KEY AUTOINCREMENT, catalog_uid TEXT NOT NULL, sort_order INTEGER, type TEXT NOT NULL, prompt TEXT NOT NULL, options TEXT, answers TEXT, terms TEXT, items TEXT, UNIQUE(catalog_uid, sort_order));');
        $pdo->exec('CREATE TABLE teams(sort_order INTEGER UNIQUE NOT NULL, name TEXT NOT NULL, uid TEXT PRIMARY KEY);');
        $pdo->exec('CREATE TABLE results(id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, catalog TEXT NOT NULL, attempt INTEGER NOT NULL, correct INTEGER NOT NULL, total INTEGER NOT NULL, time INTEGER NOT NULL, puzzleTime INTEGER, photo TEXT);');
        $pdo->exec('CREATE TABLE photo_consents(id INTEGER PRIMARY KEY AUTOINCREMENT, team TEXT NOT NULL, time INTEGER NOT NULL);');

        return [
            new CatalogService($pdo),
            new ConfigService($pdo),
            new ResultService($pdo),
            new TeamService($pdo),
            new PhotoConsentService($pdo),
            $pdo,
        ];
    }

    public function testImport(): void
    {
        [$catalog, $config, $results, $teams, $consents] = $this->createServices();
        $tmp = sys_get_temp_dir() . '/import_' . uniqid();
        mkdir($tmp . '/kataloge', 0777, true);
        file_put_contents($tmp . '/kataloge/catalogs.json', json_encode([
            ['uid'=>'u1','id'=>'c1','slug'=>'c1','file'=>'c1.json','name'=>'Cat']
        ], JSON_PRETTY_PRINT));
        file_put_contents($tmp . '/kataloge/c1.json', json_encode([
            ['type'=>'text','prompt'=>'Q']
        ], JSON_PRETTY_PRINT));

        $controller = new ImportController($catalog, $config, $results, $teams, $consents, $tmp, $tmp);
        $request = $this->createRequest('POST', '/import');
        $response = $controller->post($request, new Response());
        $this->assertEquals(204, $response->getStatusCode());
        $questions = json_decode($catalog->read('c1.json'), true);
        $this->assertCount(1, $questions);
        $this->assertSame('Q', $questions[0]['prompt']);

        unlink($tmp . '/kataloge/c1.json');
        unlink($tmp . '/kataloge/catalogs.json');
        rmdir($tmp . '/kataloge');
        rmdir($tmp);
    }
}
