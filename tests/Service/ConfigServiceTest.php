<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\ConfigService;
use Tests\TestCase;

class ConfigServiceTest extends TestCase
{
    public function testReadWriteConfig(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'config');
        $service = new ConfigService();
        $data = ['foo' => 'bar'];

        $service->saveConfig($data);
        $this->assertFileExists($tmp);
        $expected = json_encode($data, JSON_PRETTY_PRINT) . "\n";
        $this->assertSame($expected, $service->getJson());
        $this->assertEquals($data, $service->getConfig());

        unlink($tmp);
    }

    public function testGetJsonReturnsNullIfFileMissing(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'config');
        unlink($tmp);
        $service = new ConfigService();

        $this->assertNull($service->getJson());
        $this->assertEquals([], $service->getConfig());
    }
}
