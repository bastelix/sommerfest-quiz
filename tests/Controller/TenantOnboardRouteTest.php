<?php

declare(strict_types=1);

namespace Tests\Controller;

use App\Domain\Roles;
use Tests\TestCase;

class TenantOnboardRouteTest extends TestCase
{
    public function testSingleContainerOnboardReturnsSuccess(): void
    {
        putenv('TENANT_SINGLE_CONTAINER=1');
        $_ENV['TENANT_SINGLE_CONTAINER'] = '1';

        $pdo = $this->getDatabase();
        $slug = 'tenant' . bin2hex(random_bytes(2));
        $stmt = $pdo->prepare('INSERT INTO tenants(uid, subdomain) VALUES(?, ?)');
        $stmt->execute([$slug . '-uid', $slug]);

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION['user'] = ['role' => Roles::ADMIN];
        $_SESSION['csrf_token'] = 'token';

        $app = $this->getAppInstance();

        $request = $this->createRequest('POST', '/api/tenants/' . $slug . '/onboard', [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_CSRF_TOKEN' => 'token',
        ]);

        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertIsArray($data);
        $this->assertSame('completed', $data['status'] ?? null);
        $this->assertSame($slug, $data['tenant'] ?? null);
        $this->assertSame('single-container', $data['mode'] ?? null);

        $compose = dirname(__DIR__, 2) . '/tenants/' . $slug . '/docker-compose.yml';
        $this->assertFileDoesNotExist($compose);

        putenv('TENANT_SINGLE_CONTAINER');
        unset($_ENV['TENANT_SINGLE_CONTAINER']);
    }
}

