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

            public function createTenant(string $uid, string $schema, ?string $plan = null, ?string $billing = null): void
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

            public function createTenant(string $uid, string $schema, ?string $plan = null, ?string $billing = null): void
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
        $this->assertFileExists($db);
        $app = $this->getAppInstance();
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'user'];
        $req = $this->createRequest('POST', '/tenants');
        $res = $app->handle($req);
        $this->assertEquals(302, $res->getStatusCode());
        $this->assertEquals('/login', $res->getHeaderLine('Location'));
        $this->destroySession();
        unlink($db);
        $this->assertFileDoesNotExist($db);
    }

    public function testDeleteDeniedForNonAdmin(): void
    {
        $db = $this->setupDb();
        $this->assertFileExists($db);
        $app = $this->getAppInstance();
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'user'];
        $req = $this->createRequest('DELETE', '/tenants');
        $res = $app->handle($req);
        $this->assertEquals(302, $res->getStatusCode());
        $this->assertEquals('/login', $res->getHeaderLine('Location'));
        $this->destroySession();
        unlink($db);
        $this->assertFileDoesNotExist($db);
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
        $this->destroySession();
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
        $this->destroySession();
        if ($old === false) {
            putenv('MAIN_DOMAIN');
        } else {
            putenv('MAIN_DOMAIN=' . $old);
        }
    }

    public function testExistsReturns404ForUnknown(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE tenants(uid TEXT PRIMARY KEY, subdomain TEXT, plan TEXT, billing_info TEXT);');
        $controller = new TenantController(new TenantService($pdo));
        $req = $this->createRequest('GET', '/tenants/foo');
        $res = $controller->exists($req, new Response(), ['subdomain' => 'foo']);
        $this->assertEquals(404, $res->getStatusCode());
    }

    public function testExistsReturns200ForExisting(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE tenants(uid TEXT PRIMARY KEY, subdomain TEXT, plan TEXT, billing_info TEXT);');
        $pdo->exec("INSERT INTO tenants(uid, subdomain) VALUES('u1', 'bar')");
        $controller = new TenantController(new TenantService($pdo));
        $req = $this->createRequest('GET', '/tenants/bar');
        $res = $controller->exists($req, new Response(), ['subdomain' => 'bar']);
        $this->assertEquals(200, $res->getStatusCode());
    }

    public function testCreateHandlesPdoException(): void
    {
        $service = new class extends TenantService {
            public function __construct()
            {
            }

            public function createTenant(string $uid, string $schema, ?string $plan = null, ?string $billing = null): void
            {
                throw new \PDOException('fail');
            }

            public function deleteTenant(string $uid): void
            {
            }
        };
        $controller = new TenantController($service);
        $req = $this->createRequest('POST', '/tenants', ['HTTP_CONTENT_TYPE' => 'application/json']);
        $stream = (new StreamFactory())->createStream(json_encode(['uid' => 't', 'schema' => 's']));
        $req = $req->withBody($stream);
        $res = $controller->create($req, new Response());

        $this->assertEquals(500, $res->getStatusCode());
        $this->assertStringContainsString('fail', (string) $res->getBody());
    }

    public function testCreateHandlesThrowable(): void
    {
        $service = new class extends TenantService {
            public function __construct()
            {
            }

            public function createTenant(string $uid, string $schema, ?string $plan = null, ?string $billing = null): void
            {
                throw new \Exception('boom');
            }

            public function deleteTenant(string $uid): void
            {
            }
        };
        $controller = new TenantController($service);
        $req = $this->createRequest('POST', '/tenants', ['HTTP_CONTENT_TYPE' => 'application/json']);
        $stream = (new StreamFactory())->createStream(json_encode(['uid' => 't', 'schema' => 's']));
        $req = $req->withBody($stream);
        $res = $controller->create($req, new Response());

        $this->assertEquals(500, $res->getStatusCode());
        $this->assertStringContainsString('boom', (string) $res->getBody());
    }
}
