<?php

declare(strict_types=1);

namespace Tests\Controller;

use App\Controller\TenantController;
use App\Service\TenantService;
use Tests\TestCase;
use Slim\Psr7\Response;
use Slim\Psr7\Factory\StreamFactory;

class TenantControllerTest extends TestCase
{
    private function setupDb(): string
    {
        $db = tempnam(sys_get_temp_dir(), 'db');
        putenv('POSTGRES_DSN=sqlite:' . $db);
        putenv('POSTGRES_USER=');
        putenv('POSTGRES_PASSWORD=');
        $_ENV['POSTGRES_DSN'] = 'sqlite:' . $db;
        $_ENV['POSTGRES_USER'] = '';
        $_ENV['POSTGRES_PASSWORD'] = '';
        return $db;
    }

    public function testCreateReturns201(): void
    {
        $service = new TenantService();
        $controller = new TenantController($service);
        $request = $this->createRequest('POST', '/tenants', ['HTTP_CONTENT_TYPE' => 'application/json']);
        $stream = (new StreamFactory())->createStream(json_encode(['uid' => 't1']));
        $request = $request->withBody($stream);
        $response = $controller->create($request, new Response());
        $this->assertEquals(201, $response->getStatusCode());
    }

    public function testDeleteReturns204(): void
    {
        $service = new TenantService();
        $service->create(['uid' => 't1']);
        $controller = new TenantController($service);
        $request = $this->createRequest('DELETE', '/tenants', ['HTTP_CONTENT_TYPE' => 'application/json']);
        $stream = (new StreamFactory())->createStream(json_encode(['uid' => 't1']));
        $request = $request->withBody($stream);
        $response = $controller->delete($request, new Response());
        $this->assertEquals(204, $response->getStatusCode());
    }

    public function testCreateDeniedForNonAdmin(): void
    {
        $db = $this->setupDb();
        $app = $this->getAppInstance();
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'user'];
        $req = $this->createRequest('POST', '/tenants');
        $res = $app->handle($req);
        $this->assertEquals(302, $res->getStatusCode());
        $this->assertEquals('/login', $res->getHeaderLine('Location'));
        session_destroy();
        unlink($db);
    }

    public function testDeleteDeniedForNonAdmin(): void
    {
        $db = $this->setupDb();
        $app = $this->getAppInstance();
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'user'];
        $req = $this->createRequest('DELETE', '/tenants');
        $res = $app->handle($req);
        $this->assertEquals(302, $res->getStatusCode());
        $this->assertEquals('/login', $res->getHeaderLine('Location'));
        session_destroy();
        unlink($db);
    }
}
