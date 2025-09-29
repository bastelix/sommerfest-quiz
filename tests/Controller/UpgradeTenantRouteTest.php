<?php

declare(strict_types=1);

namespace Tests\Controller;

use App\Domain\Roles;
use Tests\TestCase;

class UpgradeTenantRouteTest extends TestCase
{
    public function testUpgradeWorksWithoutTag(): void {
        $script = __DIR__ . '/../../scripts/upgrade_tenant.sh';
        $backup = $script . '.bak';
        rename($script, $backup);
        file_put_contents($script, "#!/bin/sh\nexit 0\n");
        chmod($script, 0755);

        $dir = sys_get_temp_dir() . '/docker-' . uniqid();
        mkdir($dir);
        $docker = $dir . '/docker';
        file_put_contents(
            $docker,
            "#!/bin/sh\n" .
            "if [ \"$1\" = \"compose\" ] && [ \"$2\" = \"version\" ]; then\n" .
            "  exit 0\n" .
            "elif [ \"$1\" = \"compose\" ]; then\n" .
            "  echo abc123\n" .
            "  exit 0\n" .
            "elif [ \"$1\" = \"inspect\" ]; then\n" .
            "  echo test-image:latest\n" .
            "  exit 0\n" .
            "fi\n" .
            "exit 0\n"
        );
        chmod($docker, 0755);
        $oldPath = getenv('PATH');
        putenv('PATH=' . $dir . ':' . $oldPath);

        putenv('MAIN_DOMAIN=example.com');
        $_ENV['MAIN_DOMAIN'] = 'example.com';

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION['user'] = ['role' => Roles::ADMIN];

        $app = $this->getAppInstance();
        $req = $this->createRequest('POST', '/api/tenants/foo/upgrade', [
            'HTTP_HOST'   => 'example.com',
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $req = $req->withUri($req->getUri()->withHost('example.com'));
        $res = $app->handle($req);

        $this->assertSame(200, $res->getStatusCode());
        $data = json_decode((string) $res->getBody(), true);
        $this->assertSame('success', $data['status'] ?? null);
        $this->assertSame('foo', $data['slug'] ?? null);

        @session_destroy();
        putenv('MAIN_DOMAIN');
        unset($_ENV['MAIN_DOMAIN']);
        putenv('PATH=' . $oldPath);
        unlink($docker);
        rmdir($dir);
        unlink($script);
        rename($backup, $script);
    }
}
