<?php

declare(strict_types=1);

namespace Tests\Controller;

use App\Controller\ConfigController;
use App\Service\ConfigService;
use Tests\TestCase;
use Slim\Psr7\Response;

class ConfigControllerTest extends TestCase
{
    public function testGetNotFound(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'config');
        unlink($tmp);
        $controller = new ConfigController(new ConfigService($tmp));
        $request = $this->createRequest('GET', '/config.js');
        $response = $controller->get($request, new Response());

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testPostAndGet(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'config');
        $service = new ConfigService($tmp);
        $controller = new ConfigController($service);

        $request = $this->createRequest('POST', '/config.js');
        $request = $request->withParsedBody(['foo' => 'bar']);
        $postResponse = $controller->post($request, new Response());
        $this->assertEquals(204, $postResponse->getStatusCode());

        $getResponse = $controller->get($this->createRequest('GET', '/config.js'), new Response());
        $this->assertEquals(200, $getResponse->getStatusCode());
        $this->assertStringContainsString('foo', (string) $getResponse->getBody());

        unlink($tmp);
    }
}
