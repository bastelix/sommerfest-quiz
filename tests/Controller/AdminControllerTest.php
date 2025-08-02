<?php

declare(strict_types=1);

namespace Tests\Controller;

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
        $db = $this->setupDb();
        $this->assertFileExists($db);
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/admin/events');
        $response = $app->handle($request);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/admin/events', $response->getHeaderLine('Location'));
        $login = $app->handle($this->createRequest('GET', '/admin/events'));
        $this->assertEquals('/login', $login->getHeaderLine('Location'));
        unlink($db);
        $this->assertFileDoesNotExist($db);
    }

    public function testAdminPageAfterLogin(): void
    {
        $db = $this->setupDb();
        $this->assertFileExists($db);
        $app = $this->getAppInstance();
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];
        $request = $this->createRequest('GET', '/admin/events');
        $response = $app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('export-card', (string) $response->getBody());
        $this->destroySession();
        unlink($db);
        $this->assertFileDoesNotExist($db);
    }

    public function testRedirectForWrongRole(): void
    {
        $db = $this->setupDb();
        $this->assertFileExists($db);
        $app = $this->getAppInstance();
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'user'];
        $request = $this->createRequest('GET', '/admin');
        $response = $app->handle($request);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/login', $response->getHeaderLine('Location'));
        $this->destroySession();
        unlink($db);
        $this->assertFileDoesNotExist($db);
    }
}
