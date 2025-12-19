<?php

declare(strict_types=1);

namespace Tests\Controller;

use App\Domain\Roles;
use App\Infrastructure\Database;
use App\Infrastructure\Migrations\Migrator;
use PDO;
use Tests\TestCase;

class TenantOnboardRouteTest extends TestCase
{
    public function testSingleContainerOnboardReturnsSuccess(): void
    {
        $previousSingleContainer = getenv('TENANT_SINGLE_CONTAINER');
        $previousMainDomain = getenv('MAIN_DOMAIN');
        $previousDisplayErrorDetails = getenv('DISPLAY_ERROR_DETAILS');
        $previousDashboardSecret = getenv('DASHBOARD_TOKEN_SECRET');

        putenv('TENANT_SINGLE_CONTAINER=1');
        $_ENV['TENANT_SINGLE_CONTAINER'] = '1';
        putenv('MAIN_DOMAIN=quiz.example.test');
        $_ENV['MAIN_DOMAIN'] = 'quiz.example.test';

        $projectRoot = dirname(__DIR__, 2);
        $logPath = $projectRoot . '/logs/onboarding.log';
        $originalLog = is_file($logPath) ? file_get_contents($logPath) : null;

        $certDir = $projectRoot . '/certs';
        $certPath = $certDir . '/quiz.example.test.crt';
        $keyPath = $certDir . '/quiz.example.test.key';

        $certDirExisted = is_dir($certDir);
        if (!$certDirExisted) {
            mkdir($certDir, 0775, true);
        }

        $originalCert = is_file($certPath) ? file_get_contents($certPath) : null;
        $originalKey = is_file($keyPath) ? file_get_contents($keyPath) : null;

        file_put_contents($certPath, 'dummy-cert');
        file_put_contents($keyPath, 'dummy-key');

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

        Database::setFactory(static function () use ($pdo): PDO {
            return $pdo;
        });
        $this->setDatabase($pdo);

        Migrator::setHook(static function (): bool {
            return false;
        });

        try {
            $slug = 'tenant' . bin2hex(random_bytes(2));
            $stmt = $pdo->prepare('INSERT INTO tenants(uid, subdomain) VALUES(?, ?)');
            $stmt->execute([$slug . '-uid', $slug]);

            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }

            $_SESSION['user'] = ['role' => Roles::ADMIN];
            $_SESSION['csrf_token'] = 'token';

            putenv('DISPLAY_ERROR_DETAILS=1');
            $_ENV['DISPLAY_ERROR_DETAILS'] = '1';
            putenv('DASHBOARD_TOKEN_SECRET=test-secret');
            $_ENV['DASHBOARD_TOKEN_SECRET'] = 'test-secret';

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

            $compose = $projectRoot . '/tenants/' . $slug . '/docker-compose.yml';
            $this->assertFileDoesNotExist($compose);
        } finally {
            if ($originalLog === null) {
                if (is_file($logPath)) {
                    unlink($logPath);
                }
            } else {
                file_put_contents($logPath, $originalLog);
            }

            if ($originalCert === null) {
                if (is_file($certPath)) {
                    unlink($certPath);
                }
            } else {
                file_put_contents($certPath, $originalCert);
            }

            if ($originalKey === null) {
                if (is_file($keyPath)) {
                    unlink($keyPath);
                }
            } else {
                file_put_contents($keyPath, $originalKey);
            }

            if (!$certDirExisted && is_dir($certDir)) {
                $entries = array_diff(scandir($certDir), ['.', '..']);
                if (count($entries) === 0) {
                    rmdir($certDir);
                }
            }

            if ($previousSingleContainer === false) {
                putenv('TENANT_SINGLE_CONTAINER');
                unset($_ENV['TENANT_SINGLE_CONTAINER']);
            } else {
                putenv('TENANT_SINGLE_CONTAINER=' . $previousSingleContainer);
                $_ENV['TENANT_SINGLE_CONTAINER'] = $previousSingleContainer;
            }

            if ($previousMainDomain === false) {
                putenv('MAIN_DOMAIN');
                unset($_ENV['MAIN_DOMAIN']);
            } else {
                putenv('MAIN_DOMAIN=' . $previousMainDomain);
                $_ENV['MAIN_DOMAIN'] = $previousMainDomain;
            }

            if ($previousDisplayErrorDetails === false) {
                putenv('DISPLAY_ERROR_DETAILS');
                unset($_ENV['DISPLAY_ERROR_DETAILS']);
            } else {
                putenv('DISPLAY_ERROR_DETAILS=' . $previousDisplayErrorDetails);
                $_ENV['DISPLAY_ERROR_DETAILS'] = $previousDisplayErrorDetails;
            }

            if ($previousDashboardSecret === false) {
                putenv('DASHBOARD_TOKEN_SECRET');
                unset($_ENV['DASHBOARD_TOKEN_SECRET']);
            } else {
                putenv('DASHBOARD_TOKEN_SECRET=' . $previousDashboardSecret);
                $_ENV['DASHBOARD_TOKEN_SECRET'] = $previousDashboardSecret;
            }

            Database::setFactory(null);
            Migrator::setHook(null);
        }
    }
}
