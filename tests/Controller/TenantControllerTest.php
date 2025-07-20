<?php

declare(strict_types=1);

namespace Tests\Controller;

use App\Controller\TenantController;
use App\Service\TenantService;
use PDO;
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
        $service = new class extends TenantService {
            public function __construct()
            {
            }

            public function createTenant(string $uid, string $schema): void
            {
            }

            public function deleteTenant(string $uid): void
            {
            }
        };
        $controller = new TenantController($service);
        $request = $this->createRequest('POST', '/tenants', ['HTTP_CONTENT_TYPE' => 'application/json']);
        $stream = (new StreamFactory())->createStream(json_encode(['uid' => 't1', 'schema' => 's1']));
        $request = $request->withBody($stream);
        $response = $controller->create($request, new Response());
        $this->assertEquals(201, $response->getStatusCode());
    }

    public function testDeleteReturns204(): void
    {
        $service = new class extends TenantService {
            public function __construct()
            {
            }

            public function createTenant(string $uid, string $schema): void
            {
            }

            public function deleteTenant(string $uid): void
            {
            }
        };
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

    public function testCreateForbiddenOnTenantDomain(): void
    {
        $old = getenv('MAIN_DOMAIN');
        putenv('MAIN_DOMAIN=main.test');
        $app = $this->getAppInstance();
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];
        $req = $this->createRequest('POST', '/tenants');
        $req = $req->withUri($req->getUri()->withHost('tenant.test'));
        $res = $app->handle($req);
        $this->assertEquals(403, $res->getStatusCode());
        session_destroy();
        if ($old === false) {
            putenv('MAIN_DOMAIN');
        } else {
            putenv('MAIN_DOMAIN=' . $old);
        }
    }

    public function testDeleteForbiddenOnTenantDomain(): void
    {
        $old = getenv('MAIN_DOMAIN');
        putenv('MAIN_DOMAIN=main.test');
        $app = $this->getAppInstance();
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];
        $req = $this->createRequest('DELETE', '/tenants');
        $req = $req->withUri($req->getUri()->withHost('tenant.test'));
        $res = $app->handle($req);
        $this->assertEquals(403, $res->getStatusCode());
        session_destroy();
        if ($old === false) {
            putenv('MAIN_DOMAIN');
        } else {
            putenv('MAIN_DOMAIN=' . $old);
        }
    }
}
