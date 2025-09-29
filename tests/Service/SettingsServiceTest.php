<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\SettingsService;
use Tests\TestCase;

class SettingsServiceTest extends TestCase
{
    public function testReadWriteSettings(): void {
        $pdo = $this->createDatabase();
        $svc = new SettingsService($pdo);
        $svc->save(['home_page' => 'events']);
        $this->assertSame('events', $svc->get('home_page'));
        $svc->save(['home_page' => 'landing']);
        $this->assertSame('landing', $svc->get('home_page'));
        $all = $svc->getAll();
        $this->assertArrayHasKey('home_page', $all);
        $this->assertEquals('landing', $all['home_page']);
    }
}
