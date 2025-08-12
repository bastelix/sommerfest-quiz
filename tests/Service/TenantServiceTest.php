<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\TenantService;
use Tests\TestCase;
use PDO;
use App\Domain\Plan;

class TenantServiceTest extends TestCase
{
    private function createService(string $dir, PDO &$pdo, ?\App\Service\NginxService $nginx = null): TenantService
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE tenants(' .
            'uid TEXT PRIMARY KEY, subdomain TEXT, plan TEXT, billing_info TEXT, stripe_customer_id TEXT, ' .
            'imprint_name TEXT, imprint_street TEXT, imprint_zip TEXT, imprint_city TEXT, ' .
            'imprint_email TEXT, custom_limits TEXT, plan_started_at TEXT, plan_expires_at TEXT, created_at TEXT)'
        );
        if (!is_dir($dir)) {
            mkdir($dir);
        }
        $sql = <<<'SQL'
CREATE TABLE events(uid TEXT PRIMARY KEY);
CREATE TABLE catalogs(
    uid TEXT PRIMARY KEY,
    sort_order INTEGER,
    slug TEXT,
    file TEXT,
    name TEXT,
    event_uid TEXT
);
CREATE TABLE question_results(
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT,
    catalog TEXT,
    question_id INTEGER,
    attempt INTEGER,
    correct INTEGER,
    answer_text TEXT,
    photo TEXT,
    consent INTEGER,
    event_uid TEXT
);
SQL;
        file_put_contents($dir . '/20240910_base_schema.sql', $sql);
        if ($nginx === null) {
            $nginx = new class extends \App\Service\NginxService {
                public function __construct()
                {
                }

                public function createVhost(string $sub): void
                {
                }
            };
        }
        return new TenantService($pdo, $dir, $nginx);
    }

    public function testCreateTenantInsertsRow(): void
    {
        $dir = sys_get_temp_dir() . '/mig' . uniqid();
        $pdo = new PDO('sqlite::memory:');
        $service = $this->createService($dir, $pdo);
        $service->createTenant('u1', 's1', null, null, 'u1@example.com');
        $count = (int) $pdo->query('SELECT COUNT(*) FROM tenants')->fetchColumn();
        $this->assertSame(1, $count);
        $email = $pdo->query("SELECT imprint_email FROM tenants WHERE uid='u1'")->fetchColumn();
        $this->assertSame('u1@example.com', $email);
    }

    public function testDeleteTenantRemovesRow(): void
    {
        $dir = sys_get_temp_dir() . '/mig' . uniqid();
        $pdo = new PDO('sqlite::memory:');
        $service = $this->createService($dir, $pdo);
        $service->createTenant('u2', 's2');
        $service->deleteTenant('u2');
        $count = (int) $pdo->query('SELECT COUNT(*) FROM tenants')->fetchColumn();
        $this->assertSame(0, $count);
    }

    public function testCreateAndDeleteSequence(): void
    {
        $dir = sys_get_temp_dir() . '/mig' . uniqid();
        $pdo = new PDO('sqlite::memory:');
        $service = $this->createService($dir, $pdo);
        $service->createTenant('u3', 's3');
        $this->assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM tenants')->fetchColumn());
        $service->deleteTenant('u3');
        $this->assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM tenants')->fetchColumn());
    }

    public function testCreateTenantThrowsOnNginxFailure(): void
    {
        $dir = sys_get_temp_dir() . '/mig' . uniqid();
        $pdo = new PDO('sqlite::memory:');
        $nginx = new class extends \App\Service\NginxService {
            public function __construct()
            {
            }

            public function createVhost(string $sub): void
            {
                throw new \RuntimeException('reload failed');
            }
        };

        $service = $this->createService($dir, $pdo, $nginx);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Nginx reload failed');

        $service->createTenant('u4', 's4');
    }

    public function testCreateTenantFailsOnDuplicate(): void
    {
        $dir = sys_get_temp_dir() . '/mig' . uniqid();
        $pdo = new PDO('sqlite::memory:');
        $service = $this->createService($dir, $pdo);
        $service->createTenant('u5', 'dup');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('tenant-exists');

        $service->createTenant('u5b', 'dup');
    }

    public function testExistsReturnsTrueForReserved(): void
    {
        $dir = sys_get_temp_dir() . '/mig' . uniqid();
        $pdo = new PDO('sqlite::memory:');
        $service = $this->createService($dir, $pdo);
        $this->assertTrue($service->exists('www'));
    }

    public function testCreateTenantFailsOnReserved(): void
    {
        $dir = sys_get_temp_dir() . '/mig' . uniqid();
        $pdo = new PDO('sqlite::memory:');
        $service = $this->createService($dir, $pdo);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('tenant-exists');

        $service->createTenant('uid', 'www');
    }

    public function testGetBySubdomainReturnsTenant(): void
    {
        $dir = sys_get_temp_dir() . '/mig' . uniqid();
        $pdo = new PDO('sqlite::memory:');
        $service = $this->createService($dir, $pdo);
        $pdo->exec(
            "INSERT INTO tenants(uid, subdomain, plan, billing_info, imprint_name, " .
            "imprint_street, imprint_zip, imprint_city, imprint_email, custom_limits, created_at) " .
            "VALUES('u6', 'sub', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2024-01-01')"
        );
        $row = $service->getBySubdomain('sub');
        $this->assertIsArray($row);
        $this->assertSame('sub', $row['subdomain']);
    }

    public function testCreateTenantRejectsInvalidPlan(): void
    {
        $dir = sys_get_temp_dir() . '/mig' . uniqid();
        $pdo = new PDO('sqlite::memory:');
        $service = $this->createService($dir, $pdo);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('invalid-plan');

        $service->createTenant('u7', 'sub7', 'unknown');
    }

    public function testUpdateProfileRejectsInvalidPlan(): void
    {
        $dir = sys_get_temp_dir() . '/mig' . uniqid();
        $pdo = new PDO('sqlite::memory:');
        $service = $this->createService($dir, $pdo);
        $service->createTenant('u8', 'sub8', 'starter');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('invalid-plan');

        $service->updateProfile('sub8', ['plan' => 'foo']);
    }

    public function testGetPlanBySubdomainReturnsPlan(): void
    {
        $dir = sys_get_temp_dir() . '/mig' . uniqid();
        $pdo = new PDO('sqlite::memory:');
        $service = $this->createService($dir, $pdo);
        $service->createTenant('u9', 'sub9', 'starter');

        $plan = $service->getPlanBySubdomain('sub9');
        $this->assertSame('starter', $plan);
    }

    public function testCustomLimitsReadWrite(): void
    {
        $dir = sys_get_temp_dir() . '/mig' . uniqid();
        $pdo = new PDO('sqlite::memory:');
        $service = $this->createService($dir, $pdo);
        $service->createTenant('u10', 'sub10', 'starter', null, null, ['maxEvents' => 2]);
        $limits = $service->getCustomLimitsBySubdomain('sub10');
        $this->assertSame(['maxEvents' => 2], $limits);
        $service->setCustomLimits('sub10', ['maxEvents' => 5]);
        $limits2 = $service->getCustomLimitsBySubdomain('sub10');
        $this->assertSame(['maxEvents' => 5], $limits2);
    }

    public function testPlanAndLimitsReflectExternalUpdates(): void
    {
        $dir = sys_get_temp_dir() . '/mig' . uniqid();
        $pdo = new PDO('sqlite::memory:');
        $service = $this->createService($dir, $pdo);
        $service->createTenant('u12', 'sub12', Plan::STARTER->value);
        $this->assertSame(Plan::STARTER->value, $service->getPlanBySubdomain('sub12'));

        $webhook = new TenantService($pdo, $dir, new class extends \App\Service\NginxService {
            public function __construct()
            {
            }

            public function createVhost(string $sub): void
            {
            }
        });
        $webhook->updateProfile('sub12', ['plan' => Plan::STANDARD->value]);
        $this->assertSame(Plan::STANDARD->value, $service->getPlanBySubdomain('sub12'));
        $this->assertSame(Plan::STANDARD->limits(), $service->getLimitsBySubdomain('sub12'));

        $webhook->setCustomLimits('sub12', ['maxEvents' => 4]);
        $this->assertSame(['maxEvents' => 4], $service->getLimitsBySubdomain('sub12'));
    }

    public function testUpdateProfileRecalculatesExpiry(): void
    {
        $dir = sys_get_temp_dir() . '/mig' . uniqid();
        $pdo = new PDO('sqlite::memory:');
        $service = $this->createService($dir, $pdo);
        $service->createTenant('u11', 'sub11', 'starter');
        $service->updateProfile('sub11', ['plan_started_at' => '2000-01-01 00:00:00+00:00', 'plan' => 'starter']);
        $row = $pdo
            ->query("SELECT plan_started_at, plan_expires_at FROM tenants WHERE subdomain='sub11'")
            ->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('2000-01-01 00:00:00+00:00', $row['plan_started_at']);
        $this->assertSame('2000-01-31 00:00:00+00:00', $row['plan_expires_at']);
    }
}
