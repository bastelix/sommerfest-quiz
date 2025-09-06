<?php

declare(strict_types=1);

namespace Tests\Controller;

use Tests\TestCase;

class LogoutControllerTest extends TestCase
{
    public function testLogoutRedirectsToLogin(): void
    {
        $app = $this->getAppInstance();
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];
        $request = $this->createRequest('GET', '/logout');
        $response = $app->handle($request);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/login', $response->getHeaderLine('Location'));
        session_destroy();
    }

    public function testLogoutRedirectRespectsBasePath(): void
    {
        putenv('BASE_PATH=/base');
        $_ENV['BASE_PATH'] = '/base';

        $app = $this->getAppInstance();
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];
        $request = $this->createRequest('GET', '/base/logout');
        $response = $app->handle($request);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/base/login', $response->getHeaderLine('Location'));
        session_destroy();

        putenv('BASE_PATH');
        unset($_ENV['BASE_PATH']);
    }
}
