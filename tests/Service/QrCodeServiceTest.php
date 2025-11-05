<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\QrCodeService;
use Tests\TestCase;

class QrCodeServiceTest extends TestCase
{
    public function testPunchOutCanBeToggled(): void {
        $svc = new QrCodeService();

        // create transparent logo for first call
        $dir = dirname(__DIR__, 2) . '/data';
        $logo1 = imagecreatetruecolor(40, 40);
        imagesavealpha($logo1, true);
        $trans = imagecolorallocatealpha($logo1, 0, 0, 0, 127);
        imagefill($logo1, 0, 0, $trans);
        imagepng($logo1, $dir . '/logo1.png');
        imagedestroy($logo1);

        $with = $svc->generateCatalog([
            't' => 'https://example.com',
            'format' => 'png',
            'logo_path' => 'logo1.png',
            'logo_punchout' => '1',
        ]);

        // create transparent logo for second call
        $logo2 = imagecreatetruecolor(40, 40);
        imagesavealpha($logo2, true);
        $trans2 = imagecolorallocatealpha($logo2, 0, 0, 0, 127);
        imagefill($logo2, 0, 0, $trans2);
        imagepng($logo2, $dir . '/logo2.png');
        imagedestroy($logo2);

        $without = $svc->generateCatalog([
            't' => 'https://example.com',
            'format' => 'png',
            'logo_path' => 'logo2.png',
            'logo_punchout' => '0',
        ]);

        $this->assertNotSame($with['body'], $without['body']);
    }

    public function testSvgPunchOutAddsRectBehindLogo(): void {
        $svc = new QrCodeService();

        $dir = dirname(__DIR__, 2) . '/data';
        $logo = imagecreatetruecolor(40, 40);
        imagesavealpha($logo, true);
        $trans = imagecolorallocatealpha($logo, 0, 0, 0, 127);
        imagefill($logo, 0, 0, $trans);
        imagepng($logo, $dir . '/logo-svg.png');
        imagedestroy($logo);

        $result = $svc->generateCatalog([
            't' => 'https://example.com',
            'format' => 'svg',
            'logo_path' => 'logo-svg.png',
            'logo_punchout' => '1',
        ]);

        $svg = $result['body'];
        $rectPos = strpos($svg, '<rect');
        $imagePos = strpos($svg, '<image');

        $this->assertIsInt($rectPos);
        $this->assertIsInt($imagePos);
        $this->assertLessThan($imagePos, $rectPos);
        $this->assertMatchesRegularExpression('/<rect[^>]*fill="#ffffff"/i', $svg);
    }

    public function testSvgLogoKeepsAspectRatio(): void {
        $svc = new QrCodeService();

        $dir = dirname(__DIR__, 2) . '/data';
        $logo = imagecreatetruecolor(80, 40); // rectangular logo
        imagesavealpha($logo, true);
        $trans = imagecolorallocatealpha($logo, 0, 0, 0, 127);
        imagefill($logo, 0, 0, $trans);
        imagepng($logo, $dir . '/logo-rect.png');
        imagedestroy($logo);

        $result = $svc->generateCatalog([
            't' => 'https://example.com',
            'format' => 'svg',
            'logo_path' => 'logo-rect.png',
            'logo_punchout' => '1',
            'logo_width' => '100',
        ]);

        $svg = $result['body'];
        $this->assertMatchesRegularExpression('/<image[^>]*width="100"[^>]*height="50"/i', $svg);
        $this->assertMatchesRegularExpression('/<rect[^>]*width="100"[^>]*height="50"/i', $svg);
    }

    public function testLogoFileRemainsAvailableAfterMultipleGenerations(): void {
        $svc = new QrCodeService();

        $dir = dirname(__DIR__, 2) . '/data';
        $logoPath = $dir . '/logo-persist.png';

        $logo = imagecreatetruecolor(32, 32);
        imagepng($logo, $logoPath);
        imagedestroy($logo);

        $this->assertFileExists($logoPath);

        for ($i = 0; $i < 3; $i++) {
            $svc->generateCatalog([
                't' => 'https://example.com',
                'format' => 'png',
                'logo_path' => 'logo-persist.png',
            ]);
        }

        clearstatcache(true, $logoPath);
        $this->assertFileExists($logoPath);
    }
}
