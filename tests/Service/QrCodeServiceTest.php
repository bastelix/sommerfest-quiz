<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\QrCodeService;
use chillerlan\QRCode\Common\EccLevel;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use function imagecolorat;
use function imagecolorsforindex;
use function imagecreatetruecolor;
use function imagefill;
use function imagepng;
use function imagesavealpha;
use function unlink;
use function imagecolorallocatealpha;
use function imagesx;
use function imagesy;
use function imagecreatefromstring;
use function imagedestroy;

class QrCodeServiceTest extends TestCase
{
    private function createTransparentLogo(): string
    {
        $im = imagecreatetruecolor(20, 20);
        imagesavealpha($im, true);
        $transparent = imagecolorallocatealpha($im, 0, 0, 0, 127);
        imagefill($im, 0, 0, $transparent);
        $tmp = tempnam(sys_get_temp_dir(), 'logo_') . '.png';
        imagepng($im, $tmp);
        imagedestroy($im);
        return $tmp;
    }

    public function testTransparentLogoPngPunchout(): void
    {
        $service = new QrCodeService();
        $logo = $this->createTransparentLogo();
        $ref = new ReflectionClass(QrCodeService::class);
        $method = $ref->getMethod('renderQr');
        $method->setAccessible(true);
        $params = [
            'format' => 'png',
            'size' => 200,
            'margin' => 10,
            'ecc' => EccLevel::M,
            'fg' => [0, 0, 0],
            'bg' => [255, 255, 255],
            'logoPath' => $logo,
            'logoWidth' => 40,
            'logoPunchout' => true,
        ];
        $res = $method->invoke($service, 'test', $params);
        $im = imagecreatefromstring($res['body']);
        $color = imagecolorat($im, (int)(imagesx($im) / 2), (int)(imagesy($im) / 2));
        $rgb = imagecolorsforindex($im, $color);
        imagedestroy($im);
        unlink($logo);
        $this->assertSame(255, $rgb['red']);
        $this->assertSame(255, $rgb['green']);
        $this->assertSame(255, $rgb['blue']);
    }

    public function testTransparentLogoSvgPunchout(): void
    {
        $service = new QrCodeService();
        $logo = $this->createTransparentLogo();
        $ref = new ReflectionClass(QrCodeService::class);
        $method = $ref->getMethod('renderQr');
        $method->setAccessible(true);
        $params = [
            'format' => 'svg',
            'size' => 200,
            'margin' => 10,
            'ecc' => EccLevel::M,
            'fg' => [0, 0, 0],
            'bg' => [255, 255, 255],
            'logoPath' => $logo,
            'logoWidth' => 40,
            'logoPunchout' => true,
        ];
        $res = $method->invoke($service, 'test', $params);
        unlink($logo);
        $this->assertStringContainsString('<rect', $res['body']);
    }
}
