<?php

declare(strict_types=1);

namespace Tests\Controller;

use Slim\Psr7\Uri;
use Slim\Psr7\Response;
use Slim\Views\Twig;
use App\Controller\AdminController;
use App\Infrastructure\Migrations\Migrator;
use PDO;
use Tests\TestCase;

class AdminControllerTest extends TestCase
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
    public function testRedirectWhenNotLoggedIn(): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
        }
        $_SESSION = [];
        $_COOKIE = [];

        $db = $this->setupDb();
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/admin/dashboard');
        $response = $app->handle($request);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/admin/dashboard', $response->getHeaderLine('Location'));
        $login = $app->handle($this->createRequest('GET', '/admin/dashboard'));
        $this->assertEquals('/login', $login->getHeaderLine('Location'));
        unlink($db);
    }

    private function applySqliteSchema(PDO $pdo): void {
        $schemaPath = __DIR__ . '/../../src/Infrastructure/Migrations/sqlite-schema.sql';
        $schema = file_get_contents($schemaPath);
        if ($schema === false) {
            throw new \RuntimeException('Unable to load SQLite schema.');
        }

        preg_match_all('/(CREATE TABLE[\s\S]*?\);)/', $schema, $tableMatches);
        foreach ($tableMatches[1] as $statement) {
            $pdo->exec($statement);
        }

        preg_match_all('/(CREATE (?:UNIQUE )?INDEX[\s\S]*?;)/', $schema, $indexMatches);
        foreach ($indexMatches[1] as $statement) {
            $pdo->exec($statement);
        }

        $pdo->exec("INSERT OR IGNORE INTO settings(key, value) VALUES('home_page', 'help')");
        $pdo->exec("INSERT OR IGNORE INTO settings(key, value) VALUES('registration_enabled', '0')");
    }

    public function testAdminPageAfterLogin(): void {
        $db = $this->setupDb();
        $app = $this->getAppInstance();
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];
        $request = $this->createRequest('GET', '/admin/events');
        $response = $app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('export-card', (string) $response->getBody());
        $this->assertStringNotContainsString('id="langSelect"', (string) $response->getBody());
        session_destroy();
        unlink($db);
    }

    public function testDashboardShowsGreeting(): void {
        $db = $this->setupDb();
        $app = $this->getAppInstance();
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'admin', 'username' => 'alice'];
        $request = $this->createRequest('GET', '/admin/dashboard');
        $response = $app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Willkommen alice', (string) $response->getBody());
        session_destroy();
        unlink($db);
    }

    public function testRedirectForWrongRole(): void {
        $db = $this->setupDb();
        $app = $this->getAppInstance();
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'user'];
        $request = $this->createRequest('GET', '/admin');
        $response = $app->handle($request);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/login', $response->getHeaderLine('Location'));
        session_destroy();
        unlink($db);
    }

    public function testProfileShowsMainDomainData(): void {
        $db = $this->setupDb();
        putenv('MAIN_DOMAIN=example.com');
        $_ENV['MAIN_DOMAIN'] = 'example.com';
        $app = $this->getAppInstance();
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];
        $request = $this->createRequest('GET', '/admin/profile')
            ->withUri(new Uri('http', 'example.com', 80, '/admin/profile'));
        $response = $app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $this->assertStringContainsString('Example Org', $body);
        $this->assertStringContainsString('id="langSelect"', $body);
        session_destroy();
        unlink($db);
        putenv('MAIN_DOMAIN');
        unset($_ENV['MAIN_DOMAIN']);
    }

    public function testPagesAndProfileDataLoadedOnEvents(): void {
        $db = $this->setupDb();
        $app = $this->getAppInstance();
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];
        $request = $this->createRequest('GET', '/admin/events');
        $response = $app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $html = (string) $response->getBody();
        $this->assertStringContainsString('Example Org', $html);
        $this->assertStringContainsString('Professionelles Quiz-Hosting', $html);
        session_destroy();
        unlink($db);
    }

    public function testEventsEmbeddedOnPage(): void {
        $db = $this->setupDb();
        $pdo = new PDO('sqlite:' . $db);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->applySqliteSchema($pdo);
        $pdo->exec(
            "INSERT INTO events(uid, slug, name, start_date, end_date, description, published, sort_order) " .
            "VALUES('e1','test','Test Event','2025-01-01 00:00:00','2025-01-01 01:00:00','',1,0)"
        );
        putenv('DASHBOARD_TOKEN_SECRET=test-secret');
        $_ENV['DASHBOARD_TOKEN_SECRET'] = 'test-secret';
        Migrator::setHook(static fn (PDO $pdoConnection, string $dir) => false);

        $app = $this->getAppInstance();
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];
        $request = $this->createRequest('GET', '/admin/events');
        $response = $app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $this->assertStringContainsString('Test Event', $body);
        session_destroy();
        unlink($db);
        Migrator::setHook(null);
        putenv('DASHBOARD_TOKEN_SECRET');
        unset($_ENV['DASHBOARD_TOKEN_SECRET']);
    }

    public function testEventsEmbeddedOnPageWithLegacySchema(): void {
        $db = $this->setupDb();
        $pdo = new PDO('sqlite:' . $db);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->applySqliteSchema($pdo);
        $pdo->exec('ALTER TABLE events DROP COLUMN sort_order');
        $pdo->exec(
            "INSERT INTO events(uid, slug, name, start_date, end_date, description, published) " .
            "VALUES('legacy1','legacy-event','Legacy Event','2025-02-01 00:00:00','2025-02-01 01:00:00','',1)"
        );

        putenv('DASHBOARD_TOKEN_SECRET=test-secret');
        $_ENV['DASHBOARD_TOKEN_SECRET'] = 'test-secret';

        Migrator::setHook(static fn (PDO $pdoConnection, string $dir) => false);

        $app = $this->getAppInstance();
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];
        $request = $this->createRequest('GET', '/admin/events');
        $response = $app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $this->assertStringContainsString('Legacy Event', $body);

        session_destroy();
        unlink($db);
        Migrator::setHook(null);
        putenv('DASHBOARD_TOKEN_SECRET');
        unset($_ENV['DASHBOARD_TOKEN_SECRET']);
    }

    public function testActiveEventRetainedWithoutQueryParam(): void {
        $db = $this->setupDb();
        $pdo = new PDO('sqlite:' . $db);
        Migrator::migrate($pdo, __DIR__ . '/../../migrations');
        $pdo->exec(
            "INSERT INTO events(uid, slug, name, start_date, end_date, description, published, sort_order) " .
            "VALUES('ev1','ev-1','Event 1','2025-01-01 00:00:00','2025-01-01 01:00:00','',1,0)"
        );
        $pdo->exec("INSERT INTO config(event_uid) VALUES('ev1')");
        $pdo->exec("INSERT INTO active_event(event_uid) VALUES('ev1')");
        $app = $this->getAppInstance();
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];
        $request = $this->createRequest('GET', '/admin/event/settings');
        $response = $app->handle($request);
        $this->assertSame(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $this->assertStringContainsString('data-current-event-uid="ev1"', $body);
        $this->assertStringContainsString('Event 1', $body);
        session_destroy();
        unlink($db);
    }

    public function testEventDashboardRouteHighlightsDashboardPanel(): void {
        $db = $this->setupDb();
        $pdo = new PDO('sqlite:' . $db);
        Migrator::migrate($pdo, __DIR__ . '/../../migrations');
        $pdo->exec(
            "INSERT INTO events(uid, slug, name, start_date, end_date, description, published, sort_order) " .
            "VALUES('ev1','ev-1','Event 1','2025-01-01 00:00:00','2025-01-01 01:00:00','',1,0)"
        );
        $pdo->exec("INSERT INTO config(event_uid) VALUES('ev1')");
        $pdo->exec("INSERT INTO active_event(event_uid) VALUES('ev1')");
        $app = $this->getAppInstance();
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];
        $request = $this->createRequest('GET', '/admin/event/dashboard');
        $response = $app->handle($request);
        $this->assertSame(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $this->assertStringContainsString('data-focus="dashboard"', $body);
        $this->assertStringContainsString('id="dashboardConfigHeading"', $body);
        session_destroy();
        unlink($db);
    }

    public function testRagChatSettingsRenderedInAdmin(): void {
        $db = $this->setupDb();
        $pdo = new PDO('sqlite:' . $db);
        Migrator::migrate($pdo, __DIR__ . '/../../migrations');
        $stmt = $pdo->prepare('INSERT INTO settings(key, value) VALUES(?, ?)');
        $stmt->execute(['rag_chat_service_url', 'https://chat.example.com/v1/chat']);
        $stmt->execute(['rag_chat_service_driver', 'openai']);
        $stmt->execute(['rag_chat_service_model', 'gpt-4o-mini']);
        $stmt->execute(['rag_chat_service_temperature', '0.2']);
        $stmt->execute(['rag_chat_service_force_openai', '1']);
        $stmt->execute(['rag_chat_service_token', 'secret-token']);
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/admin/dashboard');
        $response = $app->handle($request);
        $this->assertSame(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $this->assertStringContainsString('"rag_chat_service_url":"https:\/\/chat.example.com\/v1\/chat"', $body);
        $this->assertStringContainsString('"rag_chat_service_driver":"openai"', $body);
        $this->assertStringContainsString('"rag_chat_service_model":"gpt-4o-mini"', $body);
        $this->assertStringContainsString('"rag_chat_service_temperature":"0.2"', $body);
        $this->assertMatchesRegularExpression('~id="ragChatUrl"[\s\S]*value="https://chat\\.example\\.com/v1/chat"~', $body);
        $this->assertMatchesRegularExpression('~<option\s+value="openai"[\s\S]*selected~', $body);
        $this->assertMatchesRegularExpression('~id="ragChatForceOpenAi"[\s\S]*checked~', $body);
        $this->assertMatchesRegularExpression('~id="ragChatModel"[\s\S]*value="gpt-4o-mini"~', $body);
        $this->assertMatchesRegularExpression('~id="ragChatTemperature"[\s\S]*value="0\.2"~', $body);
        $this->assertMatchesRegularExpression('~id="ragChatToken"[\s\S]*placeholder="\*{8}"~', $body);
        session_destroy();
        unlink($db);
    }

    public function testInvalidEventQueryReturns404(): void {
        $db = $this->setupDb();
        $pdo = new PDO('sqlite:' . $db);
        Migrator::migrate($pdo, __DIR__ . '/../../migrations');
        $controller = new AdminController();
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];
        $twig = Twig::create(__DIR__ . '/../../templates', ['cache' => false]);
        $request = $this->createRequest('GET', '/admin/dashboard?event=missing')
            ->withAttribute('view', $twig)
            ->withAttribute('pdo', $pdo);
        $response = $controller($request, new Response());
        $this->assertEquals(404, $response->getStatusCode());
        session_destroy();
        unlink($db);
    }

    /**
     * Ensure stripe customer id is stored when loading subscription page on main domain.
     *
     * @runInSeparateProcess
     */
    public function testStripeCustomerIdStoredOnMainDomain(): void {
        require_once __DIR__ . '/../Service/StripeServiceStub.php';
        $db = $this->setupDb();
        putenv('MAIN_DOMAIN=example.com');
        $_ENV['MAIN_DOMAIN'] = 'example.com';
        $app = $this->getAppInstance();
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];
        $request = $this->createRequest('GET', '/admin/subscription')
            ->withUri(new Uri('http', 'example.com', 80, '/admin/subscription'));
        $response = $app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $pdo = new \PDO($_ENV['POSTGRES_DSN']);
        $stmt = $pdo->query("SELECT stripe_customer_id FROM tenants WHERE subdomain = 'main'");
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->assertEquals('cus_test', $row['stripe_customer_id']);
        session_destroy();
        unlink($db);
        putenv('MAIN_DOMAIN');
        unset($_ENV['MAIN_DOMAIN']);
    }

    /**
     * Render subscription page without existing tenant to ensure email input is shown.
     *
     * @runInSeparateProcess
     */
    public function testSubscriptionPageShowsEmailInputWhenTenantMissing(): void {
        require_once __DIR__ . '/../Service/StripeServiceStub.php';
        $db = $this->setupDb();
        putenv('MAIN_DOMAIN=example.com');
        $_ENV['MAIN_DOMAIN'] = 'example.com';
        $app = $this->getAppInstance();
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];
        $request = $this->createRequest('GET', '/admin/subscription')
            ->withUri(new Uri('http', 'foo.example.com', 80, '/admin/subscription'));
        $response = $app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $this->assertStringContainsString('id="subscription-email"', $body);
        session_destroy();
        unlink($db);
        putenv('MAIN_DOMAIN');
        unset($_ENV['MAIN_DOMAIN']);
    }

    public function testSetSubscriptionPlanAllowsDowngrade(): void {
        $db = $this->setupDb();
        putenv('MAIN_DOMAIN=example.com');
        $_ENV['MAIN_DOMAIN'] = 'example.com';
        $app = $this->getAppInstance();
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];
        $_SESSION['csrf_token'] = 'tok';

        $request = $this->createRequest('POST', '/admin/subscription/toggle', [
            'X-CSRF-Token' => 'tok',
            'HTTP_CONTENT_TYPE' => 'application/json',
        ])->withUri(new Uri('http', 'example.com', 80, '/admin/subscription/toggle'));
        $stream1 = fopen('php://temp', 'r+');
        fwrite($stream1, json_encode(['plan' => 'professional']));
        rewind($stream1);
        $request = $request->withBody((new \Slim\Psr7\Factory\StreamFactory())->createStreamFromResource($stream1));
        $response = $app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertSame('professional', $data['plan']);

        $request2 = $this->createRequest('POST', '/admin/subscription/toggle', [
            'X-CSRF-Token' => 'tok',
            'HTTP_CONTENT_TYPE' => 'application/json',
        ])->withUri(new Uri('http', 'example.com', 80, '/admin/subscription/toggle'));
        $stream2 = fopen('php://temp', 'r+');
        fwrite($stream2, json_encode(['plan' => 'starter']));
        rewind($stream2);
        $request2 = $request2->withBody((new \Slim\Psr7\Factory\StreamFactory())->createStreamFromResource($stream2));
        $response2 = $app->handle($request2);
        $data2 = json_decode((string) $response2->getBody(), true);
        $this->assertSame('starter', $data2['plan']);

        session_destroy();
        unlink($db);
        putenv('MAIN_DOMAIN');
        unset($_ENV['MAIN_DOMAIN']);
    }

    public function testSubscriptionToggleAllowedForDemo(): void {
        $db = $this->setupDb();
        $pdo = new PDO('sqlite:' . $db);
        Migrator::migrate($pdo, __DIR__ . '/../../migrations');
        $pdo->exec("INSERT INTO tenants(uid, subdomain) VALUES('t1','demo')");
        putenv('MAIN_DOMAIN=example.com');
        $_ENV['MAIN_DOMAIN'] = 'example.com';
        $app = $this->getAppInstance();
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];
        $_SESSION['csrf_token'] = 'tok';

        $request = $this->createRequest('POST', '/admin/subscription/toggle', [
            'X-CSRF-Token' => 'tok',
            'HTTP_CONTENT_TYPE' => 'application/json',
        ])->withUri(new Uri('http', 'demo.example.com', 80, '/admin/subscription/toggle'));
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, json_encode(['plan' => 'standard']));
        rewind($stream);
        $request = $request->withBody((new \Slim\Psr7\Factory\StreamFactory())->createStreamFromResource($stream));
        $response = $app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertSame('standard', $data['plan']);
        $plan = $pdo->query("SELECT plan FROM tenants WHERE subdomain='demo'")->fetchColumn();
        $this->assertSame('standard', $plan);

        session_destroy();
        unlink($db);
        putenv('MAIN_DOMAIN');
        unset($_ENV['MAIN_DOMAIN']);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testStripeApiCalledOnPlanChange(): void {
        require_once __DIR__ . '/../Service/StripeServiceStub.php';
        \App\Service\StripeService::$calls = [];

        $db = $this->setupDb();
        putenv('MAIN_DOMAIN=example.com');
        $_ENV['MAIN_DOMAIN'] = 'example.com';
        putenv('STRIPE_PRICE_STARTER=price_start');
        putenv('STRIPE_PRICE_STANDARD=price_standard');
        putenv('STRIPE_PRICE_PROFESSIONAL=price_pro');
        $app = $this->getAppInstance();
        $pdo = new PDO($_ENV['POSTGRES_DSN']);
        $pdo->exec("INSERT INTO tenants(uid, subdomain, stripe_customer_id) VALUES('t1','main','cus_123')");

        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];
        $_SESSION['csrf_token'] = 'tok';

        $request = $this->createRequest('POST', '/admin/subscription/toggle', [
            'X-CSRF-Token' => 'tok',
            'HTTP_CONTENT_TYPE' => 'application/json',
        ])->withUri(new Uri('http', 'example.com', 80, '/admin/subscription/toggle'));
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, json_encode(['plan' => 'standard']));
        rewind($stream);
        $request = $request->withBody((new \Slim\Psr7\Factory\StreamFactory())->createStreamFromResource($stream));
        $response = $app->handle($request);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([
            ['update', 'cus_123', 'price_standard'],
        ], \App\Service\StripeService::$calls);

        session_destroy();
        unlink($db);
        putenv('MAIN_DOMAIN');
        unset($_ENV['MAIN_DOMAIN']);
        putenv('STRIPE_PRICE_STARTER');
        unset($_ENV['STRIPE_PRICE_STARTER']);
        putenv('STRIPE_PRICE_STANDARD');
        unset($_ENV['STRIPE_PRICE_STANDARD']);
        putenv('STRIPE_PRICE_PROFESSIONAL');
        unset($_ENV['STRIPE_PRICE_PROFESSIONAL']);
    }
}
