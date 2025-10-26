<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\Roles;
use App\Infrastructure\Migrations\Migrator;
use App\Service\EventService;
use Psr\Http\Message\ResponseInterface;
use Tests\TestCase;
use PDO;

final class AdminEventConfigRouteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        putenv('DASHBOARD_TOKEN_SECRET=test-secret');
        $_ENV['DASHBOARD_TOKEN_SECRET'] = 'test-secret';
        putenv('DISPLAY_ERROR_DETAILS=1');
        $_ENV['DISPLAY_ERROR_DETAILS'] = '1';

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

    public function testPostRequestUpdatesEventConfiguration(): void
    {
        $pdo = $this->createDatabase();
        $this->setDatabase($pdo);

        $eventService = new EventService($pdo);
        $eventService->saveAll([[
            'uid' => 'ev1',
            'name' => 'Test Event',
            'slug' => 'test-event',
        ]]);

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION['user'] = ['role' => Roles::ADMIN];

        $app = $this->getAppInstance();

        $request = $this->createRequest('POST', '/admin/event/ev1', [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_CONTENT_TYPE' => 'application/x-www-form-urlencoded',
        ]);
        $request->getBody()->write('pageTitle=Demo+Event');
        $request->getBody()->rewind();

        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        $payload = $this->decodeJson($response);
        $this->assertSame('Demo Event', $payload['config']['pageTitle'] ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(ResponseInterface $response): array
    {
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertIsArray($data, 'Response did not contain valid JSON: ' . $body);

        return $data;
    }
}
