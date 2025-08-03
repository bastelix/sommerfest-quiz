<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\TenantService;
use Tests\TestCase;
use PDO;

class TenantServiceTest extends TestCase
{
    private function createService(string $dir, PDO &$pdo, ?\App\Service\NginxService $nginx = null): TenantService
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE tenants(' .
            'uid TEXT PRIMARY KEY, subdomain TEXT, plan TEXT, billing_info TEXT, ' .
            'imprint_name TEXT, imprint_street TEXT, imprint_zip TEXT, imprint_city TEXT, imprint_email TEXT, created_at TEXT)'
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
        $service->createTenant('u1', 's1');
        $count = (int) $pdo->query('SELECT COUNT(*) FROM tenants')->fetchColumn();
        $this->assertSame(1, $count);
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

    public function testGetBySubdomainReturnsTenant(): void
    {
        $dir = sys_get_temp_dir() . '/mig' . uniqid();
        $pdo = new PDO('sqlite::memory:');
        $service = $this->createService($dir, $pdo);
        $pdo->exec(
            "INSERT INTO tenants(uid, subdomain, plan, billing_info, imprint_name, imprint_street, imprint_zip, imprint_city, imprint_email, created_at) " .
            "VALUES('u6', 'sub', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2024-01-01')"
        );
        $row = $service->getBySubdomain('sub');
        $this->assertIsArray($row);
        $this->assertSame('sub', $row['subdomain']);
    }
}
