<?php

declare(strict_types=1);

namespace Tests\Controller;

use Tests\TestCase;

class AdminControllerTest extends TestCase
{
    public function testRedirectWhenNotLoggedIn(): void
    {
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/admin');
        $response = $app->handle($request);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/login', $response->getHeaderLine('Location'));
    }

    public function testAdminPageAfterLogin(): void
    {
        $app = $this->getAppInstance();
        session_start();
        $_SESSION['admin'] = true;
        $request = $this->createRequest('GET', '/admin');
        $response = $app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('export-card', (string) $response->getBody());
        session_destroy();
    }
}
