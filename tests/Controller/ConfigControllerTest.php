<?php

declare(strict_types=1);

namespace Tests\Controller;

use App\Controller\ConfigController;
use App\Service\ConfigService;
use App\Service\ConfigValidator;
use Tests\TestCase;
use Slim\Psr7\Response;

class ConfigControllerTest extends TestCase
{
    public function testGetNotFound(): void
    {
        $pdo = $this->createDatabase();
        $controller = new ConfigController(new ConfigService($pdo), new ConfigValidator());
        $request = $this->createRequest('GET', '/config.json');
        $response = $controller->get($request, new Response());

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testPostAndGet(): void
    {
        $pdo = $this->createDatabase();
        $service = new ConfigService($pdo);
        $controller = new ConfigController($service, new ConfigValidator());
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'event-manager'];
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'event-manager'];

        $request = $this->createRequest('POST', '/config.json');
        $request = $request->withParsedBody(['pageTitle' => 'Demo']);
        $postResponse = $controller->post($request, new Response());
        $this->assertEquals(204, $postResponse->getStatusCode());

        $getResponse = $controller->get($this->createRequest('GET', '/config.json'), new Response());
        $this->assertEquals(200, $getResponse->getStatusCode());
        $this->assertStringContainsString('Demo', (string) $getResponse->getBody());
        session_destroy();
    }

    public function testPostInvalidJson(): void
    {
        $pdo = $this->createDatabase();
        $service = new ConfigService($pdo);
        $controller = new ConfigController($service, new ConfigValidator());

        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'event-manager'];

        $request = $this->createRequest('POST', '/config.json', ['HTTP_CONTENT_TYPE' => 'application/json']);
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, '{invalid');
        rewind($stream);
        $stream = (new \Slim\Psr7\Factory\StreamFactory())->createStreamFromResource($stream);
        $request = $request->withBody($stream);

        $response = $controller->post($request, new Response());
        $this->assertEquals(400, $response->getStatusCode());
        session_destroy();
    }

    public function testPostInvalidColor(): void
    {
        $pdo = $this->createDatabase();
        $service = new ConfigService($pdo);
        $controller = new ConfigController($service, new ConfigValidator());

        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'event-manager'];

        $request = $this->createRequest('POST', '/config.json');
        $request = $request->withParsedBody(['pageTitle' => 'Demo', 'backgroundColor' => 'blue']);
        $response = $controller->post($request, new Response());
        $this->assertEquals(400, $response->getStatusCode());
        session_destroy();
    }

    public function testPostEventUidOnly(): void
    {
        $pdo = $this->createDatabase();
        $service = new ConfigService($pdo);
        $controller = new ConfigController($service, new ConfigValidator());

        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'event-manager'];

        $request = $this->createRequest('POST', '/config.json');
        $request = $request->withParsedBody(['event_uid' => 'ev1']);
        $response = $controller->post($request, new Response());

        $this->assertEquals(204, $response->getStatusCode());
        $this->assertSame('ev1', $service->getActiveEventUid());
        session_destroy();
    }

    public function testGetByEvent(): void
    {
        $pdo = $this->createDatabase();
        $service = new ConfigService($pdo);
        $service->saveConfig(['event_uid' => 'ev1', 'pageTitle' => 'Demo']);
        $controller = new ConfigController($service, new ConfigValidator());

        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];

        $request = $this->createRequest('GET', '/events/ev1/config.json');
        $response = $controller->getByEvent($request, new Response(), ['uid' => 'ev1']);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Demo', (string) $response->getBody());
        session_destroy();
    }

    public function testPostDeniedForNonAdmin(): void
    {
        $app = $this->getAppInstance();
        session_start();
        $_SESSION['user'] = ['id' => 2, 'role' => 'user'];
        $request = $this->createRequest('POST', '/config.json');
        $request = $request->withParsedBody(['pageTitle' => 'Demo']);
        $response = $app->handle($request);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/login', $response->getHeaderLine('Location'));
        session_destroy();
    }
}
