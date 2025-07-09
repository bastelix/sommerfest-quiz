<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\TenantService;
use Tests\TestCase;
use PDO;

class TenantServiceTest extends TestCase
{
    private function createService(string $dir, PDO &$pdo): TenantService
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE tenants(uid TEXT PRIMARY KEY, subdomain TEXT);');
        if (!is_dir($dir)) {
            mkdir($dir);
        }
        file_put_contents($dir . '/001.sql', 'CREATE TABLE sample(id INTEGER);');
        return new TenantService($pdo, $dir);
    }

    public function testCreateTenantInsertsRow(): void
    {
        $dir = sys_get_temp_dir() . '/mig' . uniqid();
        $service = $this->createService($dir, $pdo);
        $service->createTenant('u1', 's1');
        $count = (int) $pdo->query('SELECT COUNT(*) FROM tenants')->fetchColumn();
        $this->assertSame(1, $count);
    }

    public function testDeleteTenantRemovesRow(): void
    {
        $dir = sys_get_temp_dir() . '/mig' . uniqid();
        $service = $this->createService($dir, $pdo);
        $service->createTenant('u2', 's2');
        $service->deleteTenant('u2');
        $count = (int) $pdo->query('SELECT COUNT(*) FROM tenants')->fetchColumn();
        $this->assertSame(0, $count);
    }
}
