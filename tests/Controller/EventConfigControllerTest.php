<?php

declare(strict_types=1);

namespace Tests\Controller;

use App\Controller\EventConfigController;
use App\Service\ConfigService;
use App\Service\EventService;
use App\Service\ImageUploadService;
use App\Infrastructure\Migrations\Migrator;
use Slim\Psr7\Response;
use Tests\TestCase;
use PDO;
use PHPUnit\Framework\Attributes\Group;

#[Group('integration')]
class EventConfigControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        putenv('DASHBOARD_TOKEN_SECRET=test-secret');
        $_ENV['DASHBOARD_TOKEN_SECRET'] = 'test-secret';

        Migrator::setHook(static function (PDO $pdo): bool {
            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
                return true;
            }

            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS events (' .
                'uid TEXT PRIMARY KEY,' .
                'slug TEXT,' .
                'name TEXT NOT NULL,' .
                'start_date TEXT,' .
                'end_date TEXT,' .
                'description TEXT,' .
                'published INTEGER DEFAULT 0,' .
                'sort_order INTEGER DEFAULT 0' .
                ')'
            );

            $pdo->exec('CREATE TABLE IF NOT EXISTS active_event (event_uid TEXT PRIMARY KEY)');

            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS tenants (' .
                'uid TEXT PRIMARY KEY,' .
                'subdomain TEXT UNIQUE,' .
                'plan TEXT,' .
                'billing_info TEXT,' .
                'stripe_customer_id TEXT,' .
                'imprint_name TEXT,' .
                'imprint_street TEXT,' .
                'imprint_zip TEXT,' .
                'imprint_city TEXT,' .
                'imprint_email TEXT,' .
                'custom_limits TEXT,' .
                'plan_started_at TEXT,' .
                'plan_expires_at TEXT,' .
                'created_at TEXT' .
                ')'
            );

            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS config (' .
                'event_uid TEXT PRIMARY KEY,' .
                'title TEXT,' .
                'dashboard_modules TEXT,' .
                'dashboard_sponsor_modules TEXT' .
                ')'
            );

            return false;
        });
    }

    protected function tearDown(): void
    {
        Migrator::setHook(null);
        parent::tearDown();
    }

    public function testUpdateInvalidData(): void {
        $pdo = $this->createDatabase();
        $eventService = new EventService($pdo);
        $eventService->saveAll([['uid' => 'ev1', 'name' => 'Test']]);
        $imageService = new ImageUploadService(sys_get_temp_dir());
        $controller = new EventConfigController($eventService, new ConfigService($pdo), $imageService);

        $request = $this->createRequest('PUT', '/events/ev1/config.json');
        $request = $request->withParsedBody(['backgroundColor' => 'blue']);

        $response = $controller->update($request, new Response(), ['id' => 'ev1']);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertStringContainsString('errors', (string) $response->getBody());
    }

    public function testUpdateValidData(): void {
        $pdo = $this->createDatabase();
        $eventService = new EventService($pdo);
        $eventService->saveAll([['uid' => 'ev1', 'name' => 'Test']]);
        $configService = new ConfigService($pdo);
        $imageService = new ImageUploadService(sys_get_temp_dir());
        $controller = new EventConfigController($eventService, $configService, $imageService);

        $request = $this->createRequest('PUT', '/events/ev1/config.json');
        $request = $request->withParsedBody(['pageTitle' => 'Demo']);

        $response = $controller->update($request, new Response(), ['id' => 'ev1']);

        $this->assertEquals(200, $response->getStatusCode());
        $payload = json_decode((string) $response->getBody(), true);
        $this->assertSame('ev1', $payload['event']['uid'] ?? null);
    }
}
