<?php

declare(strict_types=1);

namespace Tests\Controller;

use Slim\Psr7\Uri;
use Tests\TestCase;

class AdminControllerTest extends TestCase
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
    public function testRedirectWhenNotLoggedIn(): void
    {
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

    public function testAdminPageAfterLogin(): void
    {
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

    public function testDashboardShowsGreeting(): void
    {
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

    public function testRedirectForWrongRole(): void
    {
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

    public function testProfileShowsMainDomainData(): void
    {
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

    public function testPagesAndProfileDataLoadedOnEvents(): void
    {
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

    /**
     * Ensure stripe customer id is stored when loading subscription page on main domain.
     *
     * @runInSeparateProcess
     */
    public function testStripeCustomerIdStoredOnMainDomain(): void
    {
        require __DIR__ . '/../Service/StripeServiceStub.php';
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
    public function testSubscriptionPageShowsEmailInputWhenTenantMissing(): void
    {
        require __DIR__ . '/../Service/StripeServiceStub.php';
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

    public function testSetSubscriptionPlanAllowsDowngrade(): void
    {
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
}
