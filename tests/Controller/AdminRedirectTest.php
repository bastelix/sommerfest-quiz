<?php

declare(strict_types=1);

namespace Tests\Controller;

use Tests\TestCase;

class AdminRedirectTest extends TestCase
{
    public function testUnknownAdminPathRedirectsToAdmin(): void {
        $app = $this->getAppInstance();
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];
        $response = $app->handle($this->createRequest('GET', '/admin/unknown'));
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/admin/dashboard', $response->getHeaderLine('Location'));
        session_destroy();
    }
}
