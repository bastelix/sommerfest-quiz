<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\QrCodeService;
use Tests\TestCase;

class QrCodeServiceTest extends TestCase
{
    public function testPunchOutCanBeToggled(): void
    {
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
}
