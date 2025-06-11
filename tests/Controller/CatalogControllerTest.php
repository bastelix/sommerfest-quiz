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
        $request = $this->createRequest('GET', '/kataloge/missing.json');
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

        $getResponse = $controller->get($this->createRequest('GET', '/kataloge/test.json'), new Response(), ['file' => 'test.json']);
        $this->assertEquals(200, $getResponse->getStatusCode());
        $this->assertJsonStringEqualsJsonString('{"a":1}', (string) $getResponse->getBody());

        unlink($dir . '/test.json');
        rmdir($dir);
    }
}
