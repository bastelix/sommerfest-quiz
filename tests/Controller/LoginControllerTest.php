<?php

declare(strict_types=1);

namespace Tests\Controller;

use Tests\TestCase;
use App\Service\UserService;
use App\Domain\Roles;

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
}
