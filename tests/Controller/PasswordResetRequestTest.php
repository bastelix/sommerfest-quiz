<?php

declare(strict_types=1);

namespace Tests\Controller;

use Tests\TestCase;

class PasswordResetRequestTest extends TestCase
{
    public function testRenderResetRequestForm(): void {
        $app = $this->getAppInstance();
        session_start();
        $response = $app->handle($this->createRequest('GET', '/password/reset/request'));
        $this->assertSame(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $this->assertStringContainsString('<form', $body);
        $this->assertStringContainsString('csrf_token', $body);
    }
}
