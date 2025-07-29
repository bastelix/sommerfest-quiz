<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\NginxService;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class NginxServiceTest extends TestCase
{
    public function testReloadViaWebhook(): void
    {
        $dir = sys_get_temp_dir() . '/vhost' . uniqid();
        mkdir($dir);
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('request')
            ->with('POST', 'http://webhook', ['headers' => ['X-Token' => 'tok']])
            ->willReturn(new Response(200));
        $svc = new class($dir, 'example.com', '1m', true, 'http://webhook', 'tok', $client) extends NginxService {
            public bool $docker = false;
            protected function reloadDocker(): void
            {
                $this->docker = true;
            }
        };
        $svc->createVhost('test');
        $this->assertFalse($svc->docker);
        $this->assertFileExists("$dir/test.example.com");
    }

    public function testReloadViaDockerWhenNoWebhook(): void
    {
        $dir = sys_get_temp_dir() . '/vhost' . uniqid();
        mkdir($dir);
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->never())->method('request');
        $svc = new class($dir, 'example.com', '1m', true, '', 'tok', $client) extends NginxService {
            public bool $docker = false;
            protected function reloadDocker(): void
            {
                $this->docker = true;
            }
        };
        $svc->createVhost('test');
        $this->assertTrue($svc->docker);
        $this->assertFileExists("$dir/test.example.com");
    }
}
