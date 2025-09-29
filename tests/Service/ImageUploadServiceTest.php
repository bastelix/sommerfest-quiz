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
}
