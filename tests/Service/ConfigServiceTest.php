<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\ConfigService;
use PDO;
use Tests\TestCase;

class ConfigServiceTest extends TestCase
{
    public function testReadWriteConfig(): void
    {
        $pdo = $this->createMigratedPdo();
        $service = new ConfigService($pdo);
        $data = ['pageTitle' => 'Demo'];

        $service->saveConfig($data);
        $expected = json_encode(['pageTitle' => 'Demo'], JSON_PRETTY_PRINT);
        $this->assertSame($expected, $service->getJson());
        $this->assertEquals($data, $service->getConfig());
    }

    public function testGetJsonReturnsNullIfFileMissing(): void
    {
        $pdo = $this->createMigratedPdo();
        $service = new ConfigService($pdo);

        $this->assertNull($service->getJson());
        $this->assertEquals([], $service->getConfig());
    }
}
