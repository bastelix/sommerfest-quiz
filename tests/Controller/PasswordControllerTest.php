<?php

declare(strict_types=1);

namespace Tests\Controller;

use App\Domain\Roles;
use App\Infrastructure\Database;
use App\Infrastructure\Migrations\Migrator;
use App\Service\UserService;
use Tests\TestCase;

class PasswordControllerTest extends TestCase
{
    private function createUser(): UserService {
        $pdo = Database::connectFromEnv();
        Migrator::migrate($pdo, __DIR__ . '/../../migrations');
        $service = new UserService($pdo);
        $service->create('alice', 'OldPass1', null, Roles::ADMIN);
        return $service;
    }

    public function testRejectWeakPassword(): void {
        putenv('POSTGRES_DSN=');
        putenv('POSTGRES_USER=');
        putenv('POSTGRES_PASSWORD=');
        unset($_ENV['POSTGRES_DSN'], $_ENV['POSTGRES_USER'], $_ENV['POSTGRES_PASSWORD']);
        $app = $this->getAppInstance();
        $service = $this->createUser();

        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => Roles::ADMIN];

        $request = $this->createRequest('POST', '/password')
            ->withParsedBody(['password' => 'weak']);
        $response = $app->handle($request);
        $this->assertSame(400, $response->getStatusCode());

        $user = $service->getByUsername('alice');
        $this->assertIsArray($user);
        $this->assertTrue(password_verify('OldPass1', (string) $user['password']));
        session_destroy();
    }

    public function testAcceptStrongPassword(): void {
        putenv('POSTGRES_DSN=');
        putenv('POSTGRES_USER=');
        putenv('POSTGRES_PASSWORD=');
        unset($_ENV['POSTGRES_DSN'], $_ENV['POSTGRES_USER'], $_ENV['POSTGRES_PASSWORD']);
        $app = $this->getAppInstance();
        $service = $this->createUser();

        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => Roles::ADMIN];

        $request = $this->createRequest('POST', '/password')
            ->withParsedBody(['password' => 'Str0ngPass1']);
        $response = $app->handle($request);
        $this->assertSame(204, $response->getStatusCode());

        $user = $service->getByUsername('alice');
        $this->assertIsArray($user);
        $this->assertTrue(password_verify('Str0ngPass1', (string) $user['password']));
        session_destroy();
    }
}
