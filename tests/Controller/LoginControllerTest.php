<?php

declare(strict_types=1);

namespace Tests\Controller;

use Tests\TestCase;

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
}
