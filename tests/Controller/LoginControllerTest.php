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

    public function testLoginPersistsSession(): void
    {
        $pdo = $this->getDatabase();
        $userService = new UserService($pdo);
        $userService->create('alice', 'secret', 'alice@example.com', Roles::ADMIN);
        $record = $userService->getByUsername('alice');
        $this->assertIsArray($record);

        $app = $this->getAppInstance();
        $request = $this->createRequest('POST', '/login')
            ->withParsedBody(['username' => 'alice', 'password' => 'secret']);
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
        $request = $this->createRequest('POST', '/login')
            ->withParsedBody(['username' => 'frank', 'password' => 'secret'])
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
        $request = $this->createRequest('POST', '/login')
            ->withParsedBody(['username' => 'bob@example.com', 'password' => 'secret']);
        $response = $app->handle($request);
        $this->assertSame(302, $response->getStatusCode());
    }

    public function testLoginWithJsonCharset(): void
    {
        $pdo = $this->getDatabase();
        $userService = new UserService($pdo);
        $userService->create('dave', 'secret', 'dave@example.com', Roles::ADMIN);

        $app = $this->getAppInstance();
        $body = json_encode(['username' => 'dave', 'password' => 'secret']);
        $stream = (new StreamFactory())->createStream((string) $body);
        $request = $this->createRequest('POST', '/login')
            ->withHeader('Content-Type', 'application/json; charset=UTF-8')
            ->withBody($stream);

        $response = $app->handle($request);
        $this->assertSame(302, $response->getStatusCode());
    }

    public function testUnknownUserShowsMessage(): void
    {
        $app = $this->getAppInstance();
        $request = $this->createRequest('POST', '/login')
            ->withParsedBody(['username' => 'nobody', 'password' => 'secret']);
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
        $request = $this->createRequest('POST', '/login')
            ->withParsedBody(['username' => 'carol', 'password' => 'wrong']);
        $response = $app->handle($request);
        $this->assertSame(401, $response->getStatusCode());
        $this->assertStringContainsString('Passwort falsch', (string) $response->getBody());
    }
}
