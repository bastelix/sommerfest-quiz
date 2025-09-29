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
    private function setupDb(): string {
        $db = tempnam(sys_get_temp_dir(), 'db');
        putenv('POSTGRES_DSN=sqlite:' . $db);
        putenv('POSTGRES_USER=');
        putenv('POSTGRES_PASSWORD=');
        $_ENV['POSTGRES_DSN'] = 'sqlite:' . $db;
        $_ENV['POSTGRES_USER'] = '';
        $_ENV['POSTGRES_PASSWORD'] = '';
        return $db;
    }

    public function testCreateReturns201(): void {
        $service = new class extends TenantService {
            public function __construct() {
            }

            public function createTenant(
                string $uid,
                string $schema,
                ?string $plan = null,
                ?string $billing = null,
                ?string $email = null,
                ?string $imprintName = null,
                ?string $imprintStreet = null,
                ?string $imprintZip = null,
                ?string $imprintCity = null,
                ?array $customLimits = null
            ): void {
            }

            public function deleteTenant(string $uid): void {
            }
        };
        $controller = new TenantController($service);
        $request = $this->createRequest('POST', '/tenants', ['HTTP_CONTENT_TYPE' => 'application/json']);
        $stream = (new StreamFactory())->createStream(json_encode([
            'uid' => 't1',
            'schema' => 's1',
            'email' => 'test@example.com'
        ]));
        $request = $request->withBody($stream);
        $response = $controller->create($request, new Response());
        $this->assertEquals(201, $response->getStatusCode());
    }

    public function testCreateStoresEmail(): void {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE tenants(uid TEXT, subdomain TEXT, imprint_email TEXT)');
        $service = new class ($pdo) extends TenantService {
            private PDO $pdo;
            public function __construct(PDO $pdo) {
                $this->pdo = $pdo;
            }

            public function createTenant(
                string $uid,
                string $schema,
                ?string $plan = null,
                ?string $billing = null,
                ?string $email = null,
                ?string $imprintName = null,
                ?string $imprintStreet = null,
                ?string $imprintZip = null,
                ?string $imprintCity = null,
                ?array $customLimits = null
            ): void {
                $stmt = $this->pdo->prepare('INSERT INTO tenants(uid, subdomain, imprint_email) VALUES(?, ?, ?)');
                $stmt->execute([$uid, $schema, $email]);
            }

            public function deleteTenant(string $uid): void {
            }
        };
        $controller = new TenantController($service);
        $req = $this->createRequest('POST', '/tenants', ['HTTP_CONTENT_TYPE' => 'application/json']);
        $stream = (new StreamFactory())->createStream(json_encode([
            'uid' => 't2',
            'schema' => 's2',
            'email' => 'stored@example.com'
        ]));
        $req = $req->withBody($stream);
        $res = $controller->create($req, new Response());
        $this->assertEquals(201, $res->getStatusCode());
        $email = $pdo->query("SELECT imprint_email FROM tenants WHERE uid='t2'")->fetchColumn();
        $this->assertSame('stored@example.com', $email);
    }

    public function testDeleteReturns204(): void {
        $service = new class extends TenantService {
            public function __construct() {
            }

            public function createTenant(
                string $uid,
                string $schema,
                ?string $plan = null,
                ?string $billing = null,
                ?string $email = null,
                ?string $imprintName = null,
                ?string $imprintStreet = null,
                ?string $imprintZip = null,
                ?string $imprintCity = null,
                ?array $customLimits = null
            ): void {
            }

            public function deleteTenant(string $uid): void {
            }
        };
        $controller = new TenantController($service);
        $request = $this->createRequest('DELETE', '/tenants', ['HTTP_CONTENT_TYPE' => 'application/json']);
        $stream = (new StreamFactory())->createStream(json_encode(['uid' => 't1']));
        $request = $request->withBody($stream);
        $response = $controller->delete($request, new Response());
        $this->assertEquals(204, $response->getStatusCode());
    }

    public function testCreateDeniedForNonAdmin(): void {
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

    public function testDeleteDeniedForNonAdmin(): void {
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

    public function testCreateForbiddenOnTenantDomain(): void {
        $old = getenv('MAIN_DOMAIN');
        putenv('MAIN_DOMAIN=main.test');
        $app = $this->getAppInstance();
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];
        $req = $this->createRequest('POST', '/tenants');
        $req = $req->withUri($req->getUri()->withHost('tenant.main.test'));
        $res = $app->handle($req);
        $this->assertEquals(403, $res->getStatusCode());
        session_destroy();
        if ($old === false) {
            putenv('MAIN_DOMAIN');
        } else {
            putenv('MAIN_DOMAIN=' . $old);
        }
    }

    public function testDeleteForbiddenOnTenantDomain(): void {
        $old = getenv('MAIN_DOMAIN');
        putenv('MAIN_DOMAIN=main.test');
        $app = $this->getAppInstance();
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];
        $req = $this->createRequest('DELETE', '/tenants');
        $req = $req->withUri($req->getUri()->withHost('tenant.main.test'));
        $res = $app->handle($req);
        $this->assertEquals(403, $res->getStatusCode());
        session_destroy();
        if ($old === false) {
            putenv('MAIN_DOMAIN');
        } else {
            putenv('MAIN_DOMAIN=' . $old);
        }
    }

    public function testExistsReturns404ForUnknown(): void {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec(
            'CREATE TABLE tenants('
            . 'uid TEXT PRIMARY KEY, '
            . 'subdomain TEXT, '
            . 'plan TEXT, '
            . 'billing_info TEXT, '
            . 'imprint_name TEXT, '
            . 'imprint_street TEXT, '
            . 'imprint_zip TEXT, '
            . 'imprint_city TEXT, '
            . 'imprint_email TEXT, '
            . 'custom_limits TEXT, '
            . 'plan_started_at TEXT, '
            . 'plan_expires_at TEXT, '
            . 'created_at TEXT, '
            . 'stripe_customer_id TEXT, '
            . 'stripe_subscription_id TEXT, '
            . 'stripe_price_id TEXT, '
            . 'stripe_status TEXT, '
            . 'stripe_current_period_end TEXT, '
            . 'stripe_cancel_at_period_end INTEGER'
            . ');'
        );
        $controller = new TenantController(new TenantService($pdo));
        $req = $this->createRequest('GET', '/tenants/foo');
        $res = $controller->exists($req, new Response(), ['subdomain' => 'foo']);
        $this->assertEquals(404, $res->getStatusCode());
    }

    public function testExistsReturns200ForExisting(): void {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec(
            'CREATE TABLE tenants('
            . 'uid TEXT PRIMARY KEY, '
            . 'subdomain TEXT, '
            . 'plan TEXT, '
            . 'billing_info TEXT, '
            . 'imprint_name TEXT, '
            . 'imprint_street TEXT, '
            . 'imprint_zip TEXT, '
            . 'imprint_city TEXT, '
            . 'imprint_email TEXT, '
            . 'custom_limits TEXT, '
            . 'plan_started_at TEXT, '
            . 'plan_expires_at TEXT, '
            . 'created_at TEXT, '
            . 'stripe_customer_id TEXT, '
            . 'stripe_subscription_id TEXT, '
            . 'stripe_price_id TEXT, '
            . 'stripe_status TEXT, '
            . 'stripe_current_period_end TEXT, '
            . 'stripe_cancel_at_period_end INTEGER'
            . ');'
        );
        $pdo->exec(
            "INSERT INTO tenants("
            . "uid, subdomain, plan, billing_info, imprint_name, imprint_street, "
            . "imprint_zip, imprint_city, imprint_email, created_at"
            . ") "
            . "VALUES('u1', 'bar', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '')"
        );
        $controller = new TenantController(new TenantService($pdo));
        $req = $this->createRequest('GET', '/tenants/bar');
        $res = $controller->exists($req, new Response(), ['subdomain' => 'bar']);
        $this->assertEquals(200, $res->getStatusCode());
    }

    public function testExistsReturns200ForReserved(): void {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec(
            'CREATE TABLE tenants('
            . 'uid TEXT PRIMARY KEY, '
            . 'subdomain TEXT, '
            . 'plan TEXT, '
            . 'billing_info TEXT, '
            . 'imprint_name TEXT, '
            . 'imprint_street TEXT, '
            . 'imprint_zip TEXT, '
            . 'imprint_city TEXT, '
            . 'imprint_email TEXT, '
            . 'custom_limits TEXT, '
            . 'plan_started_at TEXT, '
            . 'plan_expires_at TEXT, '
            . 'created_at TEXT, '
            . 'stripe_customer_id TEXT, '
            . 'stripe_subscription_id TEXT, '
            . 'stripe_price_id TEXT, '
            . 'stripe_status TEXT, '
            . 'stripe_current_period_end TEXT, '
            . 'stripe_cancel_at_period_end INTEGER'
            . ');'
        );
        $controller = new TenantController(new TenantService($pdo));
        $req = $this->createRequest('GET', '/tenants/www');
        $res = $controller->exists($req, new Response(), ['subdomain' => 'www']);
        $this->assertEquals(200, $res->getStatusCode());
    }

    public function testExistsReturns200IfTablesExist(): void {
        $pdo = new class ('sqlite::memory:') extends PDO
        {
            public function __construct($dsn) {
                parent::__construct($dsn);
            }

            public function getAttribute($attr): mixed {
                if ($attr === PDO::ATTR_DRIVER_NAME) {
                    return 'pgsql';
                }
                return parent::getAttribute($attr);
            }
        };
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("ATTACH DATABASE ':memory:' AS information_schema");
        $pdo->exec(
            'CREATE TABLE tenants(' .
            'uid TEXT PRIMARY KEY, subdomain TEXT, plan TEXT, billing_info TEXT, imprint_name TEXT, ' .
            'imprint_street TEXT, imprint_zip TEXT, imprint_city TEXT, imprint_email TEXT, custom_limits TEXT, ' .
            'plan_started_at TEXT, plan_expires_at TEXT, created_at TEXT, stripe_customer_id TEXT, ' .
            'stripe_subscription_id TEXT, stripe_price_id TEXT, stripe_status TEXT, stripe_current_period_end TEXT, ' .
            'stripe_cancel_at_period_end INTEGER' .
            ')'
        );
        $pdo->exec('CREATE TABLE information_schema.tables(table_schema TEXT)');
        $pdo->exec("INSERT INTO information_schema.tables(table_schema) VALUES('busy')");
        $controller = new TenantController(new TenantService($pdo));
        $req = $this->createRequest('GET', '/tenants/busy');
        $res = $controller->exists($req, new Response(), ['subdomain' => 'busy']);
        $this->assertEquals(200, $res->getStatusCode());
    }

    public function testCreateHandlesPdoException(): void {
        $service = new class extends TenantService {
            public function __construct() {
            }

            public function createTenant(
                string $uid,
                string $schema,
                ?string $plan = null,
                ?string $billing = null,
                ?string $email = null,
                ?string $imprintName = null,
                ?string $imprintStreet = null,
                ?string $imprintZip = null,
                ?string $imprintCity = null,
                ?array $customLimits = null
            ): void {
                throw new \PDOException('fail');
            }

            public function deleteTenant(string $uid): void {
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

    public function testCreateHandlesThrowable(): void {
        $service = new class extends TenantService {
            public function __construct() {
            }

            public function createTenant(
                string $uid,
                string $schema,
                ?string $plan = null,
                ?string $billing = null,
                ?string $email = null,
                ?string $imprintName = null,
                ?string $imprintStreet = null,
                ?string $imprintZip = null,
                ?string $imprintCity = null,
                ?array $customLimits = null
            ): void {
                throw new \Exception('boom');
            }

            public function deleteTenant(string $uid): void {
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

    public function testListPassesQueryToService(): void {
        $service = new class extends TenantService {
            public string $received = '';
            public function __construct() {
            }

            public function getAll(string $query = ''): array {
                $this->received = $query;
                return [];
            }
        };
        $controller = new TenantController($service);
        $req = $this->createRequest('GET', '/tenants.json?query=foo');
        $res = $controller->list($req, new Response());
        $this->assertSame('foo', $service->received);
        $this->assertSame('application/json', $res->getHeaderLine('Content-Type'));
        $this->assertSame('[]', (string) $res->getBody());
    }
}
