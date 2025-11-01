<?php

declare(strict_types=1);

namespace Tests\Application;

use App\Domain\Roles;
use App\Infrastructure\Database;
use App\Infrastructure\Migrations\Migrator;
use PDO;
use Tests\TestCase;

class OnboardTenantEndpointTest extends TestCase
{
    public function testSingleContainerSkipsDockerProvisioning(): void
    {
        putenv('TENANT_SINGLE_CONTAINER=1');
        $_ENV['TENANT_SINGLE_CONTAINER'] = '1';

        $logPath = dirname(__DIR__, 2) . '/logs/onboarding.log';
        $originalLog = is_file($logPath) ? file_get_contents($logPath) : null;

        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE tenants ('
            . 'uid TEXT PRIMARY KEY,'
            . 'subdomain TEXT UNIQUE NOT NULL,'
            . 'plan TEXT,'
            . 'billing_info TEXT,'
            . 'stripe_customer_id TEXT,'
            . 'imprint_name TEXT,'
            . 'imprint_street TEXT,'
            . 'imprint_zip TEXT,'
            . 'imprint_city TEXT,'
            . 'imprint_email TEXT,'
            . 'custom_limits TEXT,'
            . 'plan_started_at TEXT,'
            . 'plan_expires_at TEXT,'
            . 'onboarding_state TEXT DEFAULT "pending",'
            . 'created_at TEXT DEFAULT CURRENT_TIMESTAMP'
            . ')'
        );
        $pdo->exec('CREATE TABLE migrations(version TEXT PRIMARY KEY)');

        Migrator::setHook(static function (): bool {
            return false;
        });

        Database::setFactory(static function () use ($pdo): PDO {
            return $pdo;
        });
        $this->setDatabase($pdo);

        putenv('DISPLAY_ERROR_DETAILS=1');
        $_ENV['DISPLAY_ERROR_DETAILS'] = '1';
        putenv('DASHBOARD_TOKEN_SECRET=test-secret');
        $_ENV['DASHBOARD_TOKEN_SECRET'] = 'test-secret';

        try {
            $pdo->exec("INSERT INTO tenants(uid, subdomain) VALUES('t-single', 'singleslug')");

            $app = $this->getAppInstance();

            putenv('RUN_MIGRATIONS_ON_REQUEST=1');
            $_ENV['RUN_MIGRATIONS_ON_REQUEST'] = '1';

            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }

            $_SESSION['user'] = ['id' => 1, 'role' => Roles::SERVICE_ACCOUNT];
            $_SESSION['csrf_token'] = 'csrf-token';

            $request = $this->createRequest('POST', '/api/tenants/singleslug/onboard', [
                'HTTP_ACCEPT' => 'application/json',
                'X-Requested-With' => 'fetch',
                'X-CSRF-Token' => 'csrf-token',
            ]);

            $response = $app->handle($request);

            $this->assertSame(200, $response->getStatusCode());

            $payload = json_decode((string) $response->getBody(), true);
            $this->assertIsArray($payload);
            $this->assertSame('completed', $payload['status'] ?? null);
            $this->assertSame('singleslug', $payload['tenant'] ?? null);
            $this->assertSame('single-container', $payload['mode'] ?? null);
            $state = $pdo->query("SELECT onboarding_state FROM tenants WHERE subdomain='singleslug'")
                ->fetchColumn();
            $this->assertSame('provisioned', $state);
        } finally {
            if ($originalLog === null) {
                if (is_file($logPath)) {
                    unlink($logPath);
                }
            } else {
                file_put_contents($logPath, $originalLog);
            }

            putenv('TENANT_SINGLE_CONTAINER');
            unset($_ENV['TENANT_SINGLE_CONTAINER']);
            putenv('RUN_MIGRATIONS_ON_REQUEST');
            unset($_ENV['RUN_MIGRATIONS_ON_REQUEST']);
            putenv('DISPLAY_ERROR_DETAILS');
            unset($_ENV['DISPLAY_ERROR_DETAILS']);
            putenv('DASHBOARD_TOKEN_SECRET');
            unset($_ENV['DASHBOARD_TOKEN_SECRET']);
            Database::setFactory(null);
            Migrator::setHook(null);
        }
    }
}
