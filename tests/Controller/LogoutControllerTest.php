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
        $_SESSION['admin'] = true;
        $request = $this->createRequest('GET', '/logout');
        $response = $app->handle($request);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/login', $response->getHeaderLine('Location'));
        session_destroy();
    }
}
