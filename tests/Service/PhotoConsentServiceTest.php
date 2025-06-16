<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\PhotoConsentService;
use Tests\TestCase;

class PhotoConsentServiceTest extends TestCase
{
    public function testAddConsentAppendsEntry(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'consent');
        $svc = new PhotoConsentService($tmp);
        $svc->add('TeamA', 123);
        $svc->add('TeamB', 456);
        $data = json_decode(file_get_contents($tmp), true);
        $this->assertCount(2, $data);
        $this->assertSame('TeamA', $data[0]['team']);
        $this->assertSame(456, $data[1]['time']);
        unlink($tmp);
    }
}
