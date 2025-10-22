<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\ImageUploadService;
use Slim\Psr7\Stream;
use Slim\Psr7\UploadedFile;
use Tests\TestCase;

class ImageUploadServiceTest extends TestCase
{
    public function testReadImageScalesDownLargeImages(): void {
        $service = new ImageUploadService();
        $file = tempnam(sys_get_temp_dir(), 'img');
        $image = imagecreatetruecolor(6000, 4000);
        imagepng($image, $file);
        imagedestroy($image);
        $stream = fopen($file, 'rb');
        $uploaded = new UploadedFile(new Stream($stream), 'large.png', 'image/png', filesize($file), UPLOAD_ERR_OK);

        $result = $service->readImage($uploaded);

        $this->assertTrue($result->width() < 6000);
        $this->assertLessThanOrEqual(ImageUploadService::MAX_PIXELS, $result->width() * $result->height());
        unlink($file);
    }

    public function testSaveUploadedFileStoresSvg(): void {
        $baseDir = sys_get_temp_dir() . '/image-upload-' . uniqid('', true);
        $this->assertTrue(mkdir($baseDir));
        $service = new ImageUploadService($baseDir);
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><rect width="10" height="10" /></svg>';

        $resource = fopen('php://temp', 'rb+');
        fwrite($resource, $svg);
        rewind($resource);

        $uploaded = new UploadedFile(
            new Stream($resource),
            'logo.svg',
            'image/svg+xml',
            strlen($svg),
            UPLOAD_ERR_OK
        );

        $relative = $service->saveUploadedFile($uploaded, 'events/test', 'logo');
        fclose($resource);

        $this->assertSame('/events/test/logo.svg', $relative);
        $target = $baseDir . '/events/test/logo.svg';
        $this->assertFileExists($target);
        $this->assertSame($svg, file_get_contents($target));

        if (is_file($target)) {
            @unlink($target);
        }
        @rmdir(dirname($target));
        @rmdir(dirname(dirname($target)));
        @rmdir($baseDir);
    }
}
