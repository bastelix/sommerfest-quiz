<?php

declare(strict_types=1);

namespace Tests\Controller;

use App\Service\ConfigService;
use App\Service\EventService;
use Tests\TestCase;

class EventPreviewControllerTest extends TestCase
{
    private function prepareDatabase(): string
    {
        $db = $this->createTemporaryDatabase();
        $pdo = \App\Infrastructure\Database::connectFromEnv();
        \App\Infrastructure\Migrations\Migrator::migrate($pdo, dirname(__DIR__, 2) . '/migrations');
        $eventService = new EventService($pdo);
        $eventService->saveAll([
            [
                'uid' => 'preview',
                'slug' => 'preview',
                'name' => 'Preview Event',
                'start_date' => '2099-01-01T12:00',
                'end_date' => '2099-01-01T14:00',
            ],
        ]);
        return $db;
    }

    private function createTemporaryDatabase(): string
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

    public function testUnlockWithValidPasswordSetsSession(): void
    {
        $db = $this->prepareDatabase();
        $app = $this->getAppInstance();
        $pdo = \App\Infrastructure\Database::connectFromEnv();
        $configService = new ConfigService($pdo);
        $configService->saveConfig([
            'event_uid' => 'preview',
            'previewPassword' => 'Secret123'
        ]);

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION['csrf_token'] = 'token';

        try {
            $request = $this->createRequest('POST', '/events/preview/preview-unlock')
                ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
                ->withParsedBody([
                    'preview_password' => 'Secret123',
                    'csrf_token' => 'token',
                ]);

            $response = $app->handle($request);

            $this->assertEquals(302, $response->getStatusCode());
            $this->assertEquals('/?event=preview', $response->getHeaderLine('Location'));
            $this->assertTrue($_SESSION['event_preview']['preview'] ?? false);
            $this->assertArrayNotHasKey('event_preview_error', $_SESSION);
        } finally {
            unlink($db);
        }
    }

    public function testUnlockWithInvalidPasswordSetsError(): void
    {
        $db = $this->prepareDatabase();
        $app = $this->getAppInstance();
        $pdo = \App\Infrastructure\Database::connectFromEnv();
        $configService = new ConfigService($pdo);
        $configService->saveConfig([
            'event_uid' => 'preview',
            'previewPassword' => 'Secret123'
        ]);

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION['csrf_token'] = 'token';

        try {
            $request = $this->createRequest('POST', '/events/preview/preview-unlock')
                ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
                ->withParsedBody([
                    'preview_password' => 'wrong',
                    'csrf_token' => 'token',
                ]);

            $response = $app->handle($request);

            $this->assertEquals(302, $response->getStatusCode());
            $this->assertEquals('/?event=preview', $response->getHeaderLine('Location'));
            $this->assertFalse($_SESSION['event_preview']['preview'] ?? false);
            $this->assertSame('invalid', $_SESSION['event_preview_error'] ?? null);
        } finally {
            unlink($db);
        }
    }

    public function testUnlockWithoutConfiguredPasswordSignalsMissing(): void
    {
        $db = $this->prepareDatabase();
        $app = $this->getAppInstance();

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION['csrf_token'] = 'token';

        try {
            $request = $this->createRequest('POST', '/events/preview/preview-unlock')
                ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
                ->withParsedBody([
                    'preview_password' => 'anything',
                    'csrf_token' => 'token',
                ]);

            $response = $app->handle($request);

            $this->assertEquals(302, $response->getStatusCode());
            $this->assertEquals('/?event=preview', $response->getHeaderLine('Location'));
            $this->assertSame('missing', $_SESSION['event_preview_error'] ?? null);
        } finally {
            unlink($db);
        }
    }
}
