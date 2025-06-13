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

    public function testCleanupOnException(): void
    {
        global $dummyImagePath;
        $dummyImagePath = $this->img;

        FPDF::$throwOnOutput = true;

        $service = new PdfExportService();
        $config = [];
        $catalogs = [
            [
                'id' => 1,
                'name' => 'Test',
                'description' => '',
                'qr_image' => 'http://example.com/qr.png',
            ],
        ];

        $before = glob(sys_get_temp_dir() . '/qr_*');

        try {
            $service->build($config, $catalogs);
            $this->fail('No exception thrown');
        } catch (\RuntimeException $e) {
            // expected
        }

        FPDF::$throwOnOutput = false;

        $after = glob(sys_get_temp_dir() . '/qr_*');
        $this->assertSame($before, $after);
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

namespace {
    class FPDF
    {
        public static bool $throwOnOutput = false;
        private string $output = '';

        public function AddPage(): void
        {
        }

        public function SetFont(...$args): void
        {
        }

        public function Cell(
            $w,
            $h = 0,
            $txt = '',
            $border = 0,
            $ln = 0,
            $align = '',
            $fill = false,
            $link = ''
        ): void {
            $this->output .= $txt;
        }

        public function Ln($h = null): void
        {
        }

        public function GetX(): int
        {
            return 0;
        }

        public function GetY(): int
        {
            return 0;
        }

        public function Image($file, $x = 0, $y = 0, $size = 0): void
        {
            $this->output .= (string)file_get_contents($file);
        }

        public function Output($dest = '', $name = '')
        {
            if (self::$throwOnOutput) {
                throw new \RuntimeException('fail');
            }

            return $this->output;
        }
    }
}
