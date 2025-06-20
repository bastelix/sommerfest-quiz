<?php
declare(strict_types=1);

namespace Tests\Controller;

use App\Controller\ImportController;
use App\Service\CatalogService;
use Tests\TestCase;
use Slim\Psr7\Response;
use PDO;

class ImportControllerTest extends TestCase
{
    private function createService(): CatalogService
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE catalogs(uid TEXT PRIMARY KEY, id TEXT UNIQUE NOT NULL, slug TEXT UNIQUE NOT NULL, file TEXT NOT NULL, name TEXT NOT NULL, description TEXT, qrcode_url TEXT, raetsel_buchstabe TEXT, comment TEXT);');
        $pdo->exec('CREATE TABLE questions(id INTEGER PRIMARY KEY AUTOINCREMENT, catalog_id TEXT NOT NULL, type TEXT NOT NULL, prompt TEXT NOT NULL, options TEXT, answers TEXT, terms TEXT, items TEXT);');
        return new CatalogService($pdo);
    }

    public function testImport(): void
    {
        $service = $this->createService();
        $tmp = sys_get_temp_dir() . '/import_' . uniqid();
        mkdir($tmp . '/kataloge', 0777, true);
        file_put_contents($tmp . '/kataloge/catalogs.json', json_encode([
            ['uid'=>'u1','id'=>'c1','slug'=>'c1','file'=>'c1.json','name'=>'Cat']
        ], JSON_PRETTY_PRINT));
        file_put_contents($tmp . '/kataloge/c1.json', json_encode([
            ['type'=>'text','prompt'=>'Q']
        ], JSON_PRETTY_PRINT));

        $controller = new ImportController($service, $tmp);
        $request = $this->createRequest('POST', '/import');
        $response = $controller->post($request, new Response());
        $this->assertEquals(204, $response->getStatusCode());
        $questions = json_decode($service->read('c1.json'), true);
        $this->assertCount(1, $questions);
        $this->assertSame('Q', $questions[0]['prompt']);

        unlink($tmp . '/kataloge/c1.json');
        unlink($tmp . '/kataloge/catalogs.json');
        rmdir($tmp . '/kataloge');
        rmdir($tmp);
    }
}
