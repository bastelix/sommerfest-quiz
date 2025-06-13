<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\PdfExportService;
use PHPUnit\Framework\TestCase;

class PdfExportServiceTest extends TestCase
{
    private string $img;

    protected function setUp(): void
    {
        $this->img = sys_get_temp_dir() . '/qr_' . uniqid() . '.png';
        $data = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQI12NgYGBgAAAABAABJzQnKgAAAABJRU5ErkJggg==');
        file_put_contents($this->img, $data);

        stream_wrapper_unregister('http');
        stream_wrapper_register('http', DummyHttpStream::class);
    }

    protected function tearDown(): void
    {
        @unlink($this->img);
        stream_wrapper_restore('http');
    }

    public function testRemoteQrImageIsEmbedded(): void
    {
        global $dummyImagePath;
        $dummyImagePath = $this->img;

        $service = new PdfExportService();
        $config = ['header' => 'h'];
        $catalogs = [
            [
                'id' => 1,
                'name' => 'Test',
                'description' => '',
                'qr_image' => 'http://example.com/qr.png',
            ],
        ];

        $pdf = $service->build($config, $catalogs);
        $this->assertNotEmpty($pdf);
        $this->assertStringContainsString('PNG', $pdf);
    }
}

class DummyHttpStream
{
    public $context;
    private $handle;

    public function stream_open($path, $mode, $options, &$opened_path): bool
    {
        global $dummyImagePath;
        $this->handle = fopen($dummyImagePath, 'rb');
        return $this->handle !== false;
    }

    public function stream_read($count)
    {
        return fread($this->handle, $count);
    }

    public function stream_eof(): bool
    {
        return feof($this->handle);
    }

    public function stream_stat()
    {
        return fstat($this->handle);
    }

    public function stream_close(): void
    {
        fclose($this->handle);
    }
}
