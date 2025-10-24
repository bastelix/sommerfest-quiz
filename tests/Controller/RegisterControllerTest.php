<?php

declare(strict_types=1);

namespace Tests\Controller;

use App\Service\SettingsService;
use Tests\TestCase;

class RegisterControllerTest extends TestCase
{
    public function testRegistrationSucceedsWithAllowedUsername(): void
    {
        $pdo = $this->getDatabase();
        $settings = new SettingsService($pdo);
        $settings->save(['registration_enabled' => '1']);

        $app = $this->getAppInstance();
        $request = $this->createRequest('POST', '/register')
            ->withParsedBody([
                'username' => 'FriendlyUser',
                'email' => 'friendly@example.com',
                'password' => 'secret123',
                'password_repeat' => 'secret123',
            ]);

        $response = $app->handle($request);
        $body = (string) $response->getBody();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Registrierung gespeichert', $body);
        $this->assertStringNotContainsString('not allowed', $body);
    }

    public function testRegistrationRejectsBlockedUsername(): void
    {
        $pdo = $this->getDatabase();
        $settings = new SettingsService($pdo);
        $settings->save(['registration_enabled' => '1']);

        $app = $this->getAppInstance();
        $request = $this->createRequest('POST', '/register')
            ->withParsedBody([
                'username' => 'Admin',
                'email' => 'admin@example.com',
                'password' => 'secret123',
                'password_repeat' => 'secret123',
            ]);

        $response = $app->handle($request);
        $body = (string) $response->getBody();

        $this->assertSame(400, $response->getStatusCode());
        $this->assertStringContainsString('The username "Admin" is not allowed.', $body);
    }
}
