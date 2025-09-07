<?php

declare(strict_types=1);

namespace Tests\Controller;

use Tests\TestCase;
use App\Service\UserService;
use App\Domain\Roles;
use Slim\Psr7\Factory\StreamFactory;

class LoginControllerTest extends TestCase
{
    public function testLoginPageShowsVersion(): void
    {
        putenv('APP_VERSION=1.2.3');
        $_ENV['APP_VERSION'] = '1.2.3';

        $app = $this->getAppInstance();
        $response = $app->handle($this->createRequest('GET', '/login'));
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Version 1.2.3', (string) $response->getBody());

        putenv('APP_VERSION');
        unset($_ENV['APP_VERSION']);
    }

    public function testLoginPageContainsCsrfToken(): void
    {
        $old = getenv('MAIN_DOMAIN');
        putenv('MAIN_DOMAIN=example.com');
        $_ENV['MAIN_DOMAIN'] = 'example.com';

        $app = $this->getAppInstance();
        session_start();
        $request = $this->createRequest('GET', '/login', ['HTTP_HOST' => 'example.com']);
        $request = $request->withUri($request->getUri()->withHost('example.com'));
        $response = $app->handle($request);
        $this->assertSame(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $this->assertStringContainsString('csrf_token', $body);

        putenv('MAIN_DOMAIN');
        if ($old !== false) {
            putenv('MAIN_DOMAIN=' . $old);
        }
        unset($_ENV['MAIN_DOMAIN']);
    }

    public function testLoginPersistsSession(): void
    {
        $pdo = $this->getDatabase();
        $userService = new UserService($pdo);
        $userService->create('alice', 'secret', 'alice@example.com', Roles::ADMIN);
        $record = $userService->getByUsername('alice');
        $this->assertIsArray($record);

        $app = $this->getAppInstance();
        session_start();
        $_SESSION['csrf_token'] = 'tok';
        $request = $this->createRequest('POST', '/login')
            ->withParsedBody([
                'username' => 'alice',
                'password' => 'secret',
                'csrf_token' => 'tok',
            ]);
        $response = $app->handle($request);
        $this->assertSame(302, $response->getStatusCode());

        $sid = session_id();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM user_sessions WHERE user_id = ? AND session_id = ?');
        $stmt->execute([(int) $record['id'], $sid]);
        $this->assertSame(1, (int) $stmt->fetchColumn());
    }

    public function testLoginPersistsSessionOnLocalhost(): void
    {
        $pdo = $this->getDatabase();
        $userService = new UserService($pdo);
        $userService->create('frank', 'secret', 'frank@example.com', Roles::ADMIN);
        $record = $userService->getByUsername('frank');
        $this->assertIsArray($record);

        $app = $this->getAppInstance();
        session_start();
        $_SESSION['csrf_token'] = 'tok';
        $request = $this->createRequest('POST', '/login')
            ->withParsedBody([
                'username' => 'frank',
                'password' => 'secret',
                'csrf_token' => 'tok',
            ])
            ->withHeader('Host', 'localhost');
        $response = $app->handle($request);
        $this->assertSame(302, $response->getStatusCode());

        $sid = session_id();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM user_sessions WHERE session_id = ?');
        $stmt->execute([$sid]);
        $this->assertSame(1, (int) $stmt->fetchColumn());
    }

    public function testLoginByEmail(): void
    {
        $pdo = $this->getDatabase();
        $userService = new UserService($pdo);
        $userService->create('bob', 'secret', 'bob@example.com', Roles::ADMIN);

        $app = $this->getAppInstance();
        session_start();
        $_SESSION['csrf_token'] = 'tok';
        $request = $this->createRequest('POST', '/login')
            ->withParsedBody([
                'username' => 'bob@example.com',
                'password' => 'secret',
                'csrf_token' => 'tok',
            ]);
        $response = $app->handle($request);
        $this->assertSame(302, $response->getStatusCode());
    }

    public function testLoginWithJsonCharset(): void
    {
        $pdo = $this->getDatabase();
        $userService = new UserService($pdo);
        $userService->create('dave', 'secret', 'dave@example.com', Roles::ADMIN);

        $app = $this->getAppInstance();
        session_start();
        $_SESSION['csrf_token'] = 'tok';
        $body = json_encode(['username' => 'dave', 'password' => 'secret']);
        $stream = (new StreamFactory())->createStream((string) $body);
        $request = $this->createRequest('POST', '/login')
            ->withHeader('Content-Type', 'application/json; charset=UTF-8')
            ->withHeader('X-CSRF-Token', 'tok')
            ->withBody($stream);

        $response = $app->handle($request);
        $this->assertSame(302, $response->getStatusCode());
    }

    public function testLoginRedirectRespectsBasePath(): void
    {
        $pdo = $this->getDatabase();
        $userService = new UserService($pdo);
        $userService->create('erin', 'secret', 'erin@example.com', Roles::ADMIN);

        putenv('BASE_PATH=/base');
        $_ENV['BASE_PATH'] = '/base';

        $app = $this->getAppInstance();
        session_start();
        $_SESSION['csrf_token'] = 'tok';
        $request = $this->createRequest('POST', '/base/login')
            ->withParsedBody([
                'username' => 'erin',
                'password' => 'secret',
                'csrf_token' => 'tok',
            ]);
        $response = $app->handle($request);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/base/admin', $response->getHeaderLine('Location'));

        putenv('BASE_PATH');
        unset($_ENV['BASE_PATH']);
    }

    public function testLoginRedirectsToMainDomainOnWrongHost(): void
    {
        $pdo = $this->getDatabase();
        $userService = new UserService($pdo);
        $userService->create('grace', 'secret', 'grace@example.com', Roles::ADMIN);

        putenv('MAIN_DOMAIN=main.test');
        $_ENV['MAIN_DOMAIN'] = 'main.test';

        $app = $this->getAppInstance();
        session_start();
        $_SESSION['csrf_token'] = 'tok';
        $_SERVER['HTTP_HOST'] = 'tenant.main.test';
        $request = $this->createRequest('POST', '/login', ['HTTP_HOST' => 'tenant.main.test'])
            ->withParsedBody([
                'username' => 'grace',
                'password' => 'secret',
                'csrf_token' => 'tok',
            ]);
        $request = $request->withUri($request->getUri()->withHost('tenant.main.test')->withScheme('https'));
        $response = $app->handle($request);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('https://main.test/admin', $response->getHeaderLine('Location'));

        putenv('MAIN_DOMAIN');
        unset($_ENV['MAIN_DOMAIN'], $_SERVER['HTTP_HOST']);
    }

    public function testUnknownUserShowsMessage(): void
    {
        $app = $this->getAppInstance();
        session_start();
        $_SESSION['csrf_token'] = 'tok';
        $request = $this->createRequest('POST', '/login')
            ->withParsedBody([
                'username' => 'nobody',
                'password' => 'secret',
                'csrf_token' => 'tok',
            ]);
        $response = $app->handle($request);
        $this->assertSame(401, $response->getStatusCode());
        $this->assertStringContainsString('Benutzer nicht gefunden', (string) $response->getBody());
    }

    public function testWrongPasswordShowsMessage(): void
    {
        $pdo = $this->getDatabase();
        $userService = new UserService($pdo);
        $userService->create('carol', 'secret', 'carol@example.com', Roles::ADMIN);

        $app = $this->getAppInstance();
        session_start();
        $_SESSION['csrf_token'] = 'tok';
        $request = $this->createRequest('POST', '/login')
            ->withParsedBody([
                'username' => 'carol',
                'password' => 'wrong',
                'csrf_token' => 'tok',
            ]);
        $response = $app->handle($request);
        $this->assertSame(401, $response->getStatusCode());
        $this->assertStringContainsString('Passwort falsch', (string) $response->getBody());
    }
}
