<?php

declare(strict_types=1);

namespace Tests;


class UpgradeTenantRouteTest extends TestCase
{
    private string $oldPath = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->oldPath = getenv('PATH') ?: '';
        $path = __DIR__ . '/fixtures/bin:' . $this->oldPath;
        putenv('PATH=' . $path);
        $_ENV['PATH'] = $path;
        $_SERVER['PATH'] = $path;
    }

    protected function tearDown(): void
    {
        putenv('PATH=' . $this->oldPath);
        $_ENV['PATH'] = $this->oldPath;
        $_SERVER['PATH'] = $this->oldPath;
        putenv('APP_IMAGE');
        unset($_ENV['APP_IMAGE']);
        parent::tearDown();
    }

    public function testUpgradeWithoutTag(): void
    {
        $app = $this->getAppInstance();
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];
        $request = $this->createRequest('POST', '/api/tenants/main/upgrade')
            ->withAttribute('domainType', 'main');
        $response = $app->handle($request);
        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertSame('success', $data['status']);
        $this->assertSame('sommerfest-quiz:latest', $data['image']);
        session_destroy();
    }

    public function testUpgradeWithCustomTag(): void
    {
        $app = $this->getAppInstance();
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];
        $request = $this->createRequest('POST', '/api/tenants/main/upgrade')
            ->withAttribute('domainType', 'main')
            ->withParsedBody(['image' => 'custom:1']);
        $response = $app->handle($request);
        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertSame('success', $data['status']);
        $this->assertSame('custom:1', $data['image']);
        session_destroy();
    }
}

