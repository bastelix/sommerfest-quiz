<?php

declare(strict_types=1);

namespace Tests\Controller;

use App\Controller\CatalogController;
use App\Service\CatalogService;
use Tests\TestCase;
use Slim\Psr7\Response;

class CatalogControllerTest extends TestCase
{
    public function testGetNotFound(): void
    {
        $dir = sys_get_temp_dir() . '/catalog_' . uniqid();
        mkdir($dir);
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE catalogs(id INTEGER PRIMARY KEY AUTOINCREMENT, slug TEXT UNIQUE NOT NULL, uid TEXT UNIQUE NOT NULL, file TEXT NOT NULL, name TEXT NOT NULL, description TEXT, qrcode_url TEXT, raetsel_buchstabe TEXT);');
        $pdo->exec('CREATE TABLE questions(id INTEGER PRIMARY KEY AUTOINCREMENT, catalog_id INTEGER NOT NULL, type TEXT NOT NULL, prompt TEXT NOT NULL, options TEXT, answers TEXT, terms TEXT, items TEXT);');
        $controller = new CatalogController(new CatalogService($pdo));
        $request = $this->createRequest('GET', '/kataloge/missing.json', ['HTTP_ACCEPT' => 'application/json']);
        $response = $controller->get($request, new Response(), ['file' => 'missing.json']);
        $this->assertEquals(404, $response->getStatusCode());
        rmdir($dir);
    }

    public function testPostAndGet(): void
    {
        $dir = sys_get_temp_dir() . '/catalog_' . uniqid();
        mkdir($dir);
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE catalogs(id INTEGER PRIMARY KEY AUTOINCREMENT, slug TEXT UNIQUE NOT NULL, uid TEXT UNIQUE NOT NULL, file TEXT NOT NULL, name TEXT NOT NULL, description TEXT, qrcode_url TEXT, raetsel_buchstabe TEXT);');
        $pdo->exec('CREATE TABLE questions(id INTEGER PRIMARY KEY AUTOINCREMENT, catalog_id INTEGER NOT NULL, type TEXT NOT NULL, prompt TEXT NOT NULL, options TEXT, answers TEXT, terms TEXT, items TEXT);');
        $service = new CatalogService($pdo);
        $controller = new CatalogController($service);

        $request = $this->createRequest('POST', '/kataloge/test.json');
        $request = $request->withParsedBody(['a' => 1]);
        $postResponse = $controller->post($request, new Response(), ['file' => 'test.json']);
        $this->assertEquals(204, $postResponse->getStatusCode());

        $getResponse = $controller->get(
            $this->createRequest('GET', '/kataloge/test.json', ['HTTP_ACCEPT' => 'application/json']),
            new Response(),
            ['file' => 'test.json']
        );
        $this->assertEquals(200, $getResponse->getStatusCode());
        $this->assertJsonStringEqualsJsonString('{"a":1}', (string) $getResponse->getBody());

        unlink($dir . '/test.json');
        rmdir($dir);
    }

    public function testCreateAndDelete(): void
    {
        $dir = sys_get_temp_dir() . '/catalog_' . uniqid();
        mkdir($dir);
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE catalogs(id INTEGER PRIMARY KEY AUTOINCREMENT, slug TEXT UNIQUE NOT NULL, uid TEXT UNIQUE NOT NULL, file TEXT NOT NULL, name TEXT NOT NULL, description TEXT, qrcode_url TEXT, raetsel_buchstabe TEXT);');
        $pdo->exec('CREATE TABLE questions(id INTEGER PRIMARY KEY AUTOINCREMENT, catalog_id INTEGER NOT NULL, type TEXT NOT NULL, prompt TEXT NOT NULL, options TEXT, answers TEXT, terms TEXT, items TEXT);');
        $service = new CatalogService($pdo);
        $controller = new CatalogController($service);

        $createReq = $this->createRequest('PUT', '/kataloge/new.json');
        $createRes = $controller->create($createReq, new Response(), ['file' => 'new.json']);
        $this->assertEquals(204, $createRes->getStatusCode());
        $this->assertFileExists($dir . '/new.json');

        $deleteRes = $controller->delete(
            $this->createRequest('DELETE', '/kataloge/new.json'),
            new Response(),
            ['file' => 'new.json']
        );
        $this->assertEquals(204, $deleteRes->getStatusCode());
        $this->assertFileDoesNotExist($dir . '/new.json');

        rmdir($dir);
    }

    public function testDeleteQuestion(): void
    {
        $dir = sys_get_temp_dir() . '/catalog_' . uniqid();
        mkdir($dir);
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE catalogs(id INTEGER PRIMARY KEY AUTOINCREMENT, slug TEXT UNIQUE NOT NULL, uid TEXT UNIQUE NOT NULL, file TEXT NOT NULL, name TEXT NOT NULL, description TEXT, qrcode_url TEXT, raetsel_buchstabe TEXT);');
        $pdo->exec('CREATE TABLE questions(id INTEGER PRIMARY KEY AUTOINCREMENT, catalog_id INTEGER NOT NULL, type TEXT NOT NULL, prompt TEXT NOT NULL, options TEXT, answers TEXT, terms TEXT, items TEXT);');
        $service = new CatalogService($pdo);
        $controller = new CatalogController($service);

        $service->write('cat.json', [['a' => 1], ['b' => 2]]);

        $req = $this->createRequest('DELETE', '/kataloge/cat.json/0');
        $res = $controller->deleteQuestion($req, new Response(), ['file' => 'cat.json', 'index' => '0']);
        $this->assertEquals(204, $res->getStatusCode());

        $data = json_decode($service->read('cat.json'), true);
        $this->assertCount(1, $data);
        $this->assertSame(['b' => 2], $data[0]);

        unlink($dir . '/cat.json');
        rmdir($dir);
    }

    public function testPostInvalidJson(): void
    {
        $dir = sys_get_temp_dir() . '/catalog_' . uniqid();
        mkdir($dir);
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE catalogs(id INTEGER PRIMARY KEY AUTOINCREMENT, slug TEXT UNIQUE NOT NULL, uid TEXT UNIQUE NOT NULL, file TEXT NOT NULL, name TEXT NOT NULL, description TEXT, qrcode_url TEXT, raetsel_buchstabe TEXT);');
        $pdo->exec('CREATE TABLE questions(id INTEGER PRIMARY KEY AUTOINCREMENT, catalog_id INTEGER NOT NULL, type TEXT NOT NULL, prompt TEXT NOT NULL, options TEXT, answers TEXT, terms TEXT, items TEXT);');
        $controller = new CatalogController(new CatalogService($pdo));

        $request = $this->createRequest('POST', '/kataloge/test.json', ['HTTP_CONTENT_TYPE' => 'application/json']);
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, '{invalid');
        rewind($stream);
        $stream = (new \Slim\Psr7\Factory\StreamFactory())->createStreamFromResource($stream);
        $request = $request->withBody($stream);

        $response = $controller->post($request, new Response(), ['file' => 'test.json']);
        $this->assertEquals(400, $response->getStatusCode());

        rmdir($dir);
    }
}
