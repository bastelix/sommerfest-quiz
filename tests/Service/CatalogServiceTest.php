<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\CatalogService;
use Tests\TestCase;

class CatalogServiceTest extends TestCase
{
    public function testReadWrite(): void
    {
        $dir = sys_get_temp_dir() . '/catalog_' . uniqid();
        mkdir($dir);
        $service = new CatalogService($dir);
        $file = 'test.json';
        $data = ['a' => 1];

        $service->write($file, $data);
        $this->assertJsonStringEqualsJsonString(json_encode($data, JSON_PRETTY_PRINT), $service->read($file));

        unlink($dir . '/' . $file);
        rmdir($dir);
    }

    public function testReadReturnsNullIfMissing(): void
    {
        $dir = sys_get_temp_dir() . '/catalog_' . uniqid();
        mkdir($dir);
        $service = new CatalogService($dir);

        $this->assertNull($service->read('missing.json'));

        rmdir($dir);
    }
}
