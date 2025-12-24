<?php
declare(strict_types=1);

namespace Tests\Controller;

use App\Application\Handlers\HttpErrorHandler;
use App\Application\Middleware\RoleAuthMiddleware;
use App\Controller\EventController;
use App\Domain\Roles;
use App\Infrastructure\Database;
use App\Service\ConfigService;
use App\Service\EventService;
use App\Service\TenantService;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Uri;
use Tests\TestCase;

class EventsRouteTest extends TestCase
{
    public function testEventsListAccessibleForCatalogEditor(): void {
        $app = $this->getAppInstance();
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION['user'] = ['role' => Roles::CATALOG_EDITOR];

        $request = $this->createRequest('GET', '/events.json', ['HTTP_ACCEPT' => 'application/json']);
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        @session_destroy();
    }

    public function testStarterPlanLimitExceeded(): void {
        $pdo = $this->createDatabase();
        $pdo->exec("INSERT INTO tenants(uid, subdomain, plan) VALUES('t1','foo','starter')");

        $config = new ConfigService($pdo);
        $tenantSvc = new TenantService($pdo);
        $eventSvc = new EventService($pdo, $config, $tenantSvc, 'foo');
        $controller = new EventController($eventSvc);

        $app = AppFactory::create();
        $errorMiddleware = $app->addErrorMiddleware(false, false, false);
        $handler = new HttpErrorHandler(
            $app->getCallableResolver(),
            $app->getResponseFactory()
        );
        $errorMiddleware->setDefaultErrorHandler($handler);

        $app->post('/events.json', [$controller, 'post'])
            ->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::EVENT_MANAGER));

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION['user'] = ['id' => 1, 'role' => Roles::ADMIN];

        $request = $this->createRequest('POST', '/events.json', [
            'HTTP_CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $stream = (new StreamFactory())->createStream(json_encode([
            ['name' => 'One'],
            ['name' => 'Two'],
        ]));
        $request = $request->withBody($stream);

        $response = $app->handle($request);
        $this->assertSame(402, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertSame('max-events-exceeded', $data['error']['description'] ?? null);

        @session_destroy();
    }

    public function testStarterPlanLimitExceededOnMainDomain(): void {
        $pdo = $this->createDatabase();
        $pdo->exec("INSERT INTO tenants(uid, subdomain, plan) VALUES('t1','main','starter')");
        putenv('MAIN_DOMAIN=example.com');
        $_ENV['MAIN_DOMAIN'] = 'example.com';

        $app = $this->getAppInstance();

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION['user'] = ['id' => 1, 'role' => Roles::ADMIN];

        $request = $this->createRequest('POST', '/events.json', [
            'HTTP_CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ])->withUri(new Uri('http', 'example.com', 80, '/events.json'));
        $stream = (new StreamFactory())->createStream(json_encode([
            ['name' => 'One'],
            ['name' => 'Two'],
        ]));
        $request = $request->withBody($stream);

        $response = $app->handle($request);
        $this->assertSame(402, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertSame('max-events-exceeded', $data['error']['description'] ?? null);

        @session_destroy();
        putenv('MAIN_DOMAIN');
        unset($_ENV['MAIN_DOMAIN']);
    }

    public function testEventsListFallsBackToJsonWhenDatabaseFails(): void
    {
        $pdo = $this->createDatabase();
        $this->setDatabase($pdo);

        $dsn = getenv('POSTGRES_DSN') ?: '';
        $user = getenv('POSTGRES_USER') ?: '';
        $password = getenv('POSTGRES_PASSWORD') ?: '';
        $options = [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION];

        Database::setFactory(function () use ($dsn, $user, $password, $options) {
            return new class ($dsn, $user, $password, $options) extends \PDO {
                public function __construct(string $dsn, string $user, string $password, array $options)
                {
                    parent::__construct($dsn, $user, $password, $options);
                }

                public function query(
                    string $query,
                    ?int $fetchMode = null,
                    mixed ...$fetchModeArgs
                ): \PDOStatement|false {
                    if (trim($query) === 'SELECT uid,name,start_date,end_date,description,published,sort_order FROM events ORDER BY sort_order') {
                        throw new \PDOException('Simulated database failure');
                    }

                    if ($fetchMode === null) {
                        return parent::query($query);
                    }

                    return parent::query($query, $fetchMode, ...$fetchModeArgs);
                }
            };
        });

        try {
            $app = $this->getAppInstance();
            $request = $this->createRequest('GET', '/events.json', ['HTTP_ACCEPT' => 'application/json']);
            $response = $app->handle($request);

            $this->assertSame(200, $response->getStatusCode());

            $payload = json_decode((string) $response->getBody(), true);
            $this->assertIsArray($payload);

            $path = dirname(__DIR__, 2) . '/data/events.json';
            $expected = json_decode((string) file_get_contents($path), true);
            $this->assertIsArray($expected);

            $expected = array_map(static function (array $event): array {
                $event['published'] = (bool)($event['published'] ?? false);
                return $event;
            }, $expected);

            $this->assertSame($expected, $payload);
        } finally {
            Database::setFactory(null);
        }
    }
}
