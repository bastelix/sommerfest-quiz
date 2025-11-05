<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\NginxService;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class NginxServiceTest extends TestCase
{
    public function testReloadViaWebhook(): void {
        $dir = sys_get_temp_dir() . '/vhost' . uniqid();
        mkdir($dir);
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('request')
            ->with('POST', 'http://webhook', ['headers' => ['X-Token' => 'tok']])
            ->willReturn(new Response(200));
        $svc = new class ($dir, 'example.com', '1m', true, 'http://webhook', 'tok', $client) extends NginxService {
            public bool $called = false;
            public function reload(): void {
                $this->called = true;
                parent::reload();
            }
        };
        $svc->createVhost('test');
        $this->assertTrue($svc->called);
        $this->assertFileExists("$dir/test.example.com");
    }

    public function testCreateVhostFailsOnUnwritableDir(): void {
        $svc = new NginxService('/proc/sys', 'example.com', '1m', false);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Vhost directory not writable');
        $svc->createVhost('test');
    }

    public function testWebhookOverridesDisabledReloadEnv(): void {
        $dir = sys_get_temp_dir() . '/vhost' . uniqid();
        mkdir($dir);
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('request')
            ->with('POST', 'http://dummy-webhook', ['headers' => ['X-Token' => 'tok']])
            ->willReturn(new Response(200));

        $originalReload = getenv('NGINX_RELOAD');
        $originalWebhook = getenv('NGINX_RELOADER_URL');

        putenv('NGINX_RELOAD=0');
        putenv('NGINX_RELOADER_URL=http://dummy-webhook');

        try {
            $svc = new class ($dir, 'example.com', '1m', null, null, 'tok', $client) extends NginxService {
                public bool $called = false;
                public function reload(): void {
                    $this->called = true;
                    parent::reload();
                }
            };

            $svc->createVhost('test');

            $this->assertTrue($svc->called);
        } finally {
            if ($originalReload === false) {
                putenv('NGINX_RELOAD');
            } else {
                putenv('NGINX_RELOAD=' . $originalReload);
            }

            if ($originalWebhook === false) {
                putenv('NGINX_RELOADER_URL');
            } else {
                putenv('NGINX_RELOADER_URL=' . $originalWebhook);
            }
        }

        $this->assertFileExists("$dir/test.example.com");
    }
}
