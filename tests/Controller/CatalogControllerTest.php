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
        $controller = new CatalogController(new CatalogService($dir));
        $request = $this->createRequest('GET', '/kataloge/missing.json', ['HTTP_ACCEPT' => 'application/json']);
        $response = $controller->get($request, new Response(), ['file' => 'missing.json']);
        $this->assertEquals(404, $response->getStatusCode());
        rmdir($dir);
    }

    public function testPostAndGet(): void
    {
        $dir = sys_get_temp_dir() . '/catalog_' . uniqid();
        mkdir($dir);
        $service = new CatalogService($dir);
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
        $service = new CatalogService($dir);
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
        $service = new CatalogService($dir);
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
}
