<?php

declare(strict_types=1);

namespace Tests\Controller;

use App\Service\TraefikService;
use Tests\TestCase;

class TraefikRefreshRouteTest extends TestCase
{
    public function testRequestInvokesTraefikServiceWhenAuthorized(): void
    {
        $dir = sys_get_temp_dir() . '/traefik' . uniqid();
        mkdir($dir);
        $file = $dir . '/trigger.yml';

        $service = new class ($file) extends TraefikService {
            public bool $notified = false;

            public function notifyConfigChange(): void
            {
                $this->notified = true;
            }
        };

        $old = getenv('NGINX_RELOAD_TOKEN');
        putenv('NGINX_RELOAD_TOKEN=changeme');
        $_ENV['NGINX_RELOAD_TOKEN'] = 'changeme';

        $app = $this->getAppInstance();
        $req = $this->createRequest('POST', '/nginx-reload', ['X-Token' => 'changeme']);
        $req = $req->withAttribute('proxyService', $service);
        $res = $app->handle($req);

        $this->assertSame(200, $res->getStatusCode());
        $this->assertTrue($service->notified);

        if ($old === false) {
            putenv('NGINX_RELOAD_TOKEN');
            unset($_ENV['NGINX_RELOAD_TOKEN']);
        } else {
            putenv('NGINX_RELOAD_TOKEN=' . $old);
            $_ENV['NGINX_RELOAD_TOKEN'] = $old;
        }

        if (file_exists($file)) {
            unlink($file);
        }
        if (is_dir($dir)) {
            rmdir($dir);
        }
    }
}
