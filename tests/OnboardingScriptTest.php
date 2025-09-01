<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

class OnboardingScriptTest extends TestCase
{
    public function testOnboardTenantCreatesComposeFile(): void
    {
        $slug = 't' . bin2hex(random_bytes(3));
        $root = dirname(__DIR__);
        $tenantDir = $root . '/tenants';
        if (!is_dir($tenantDir)) {
            mkdir($tenantDir);
        }

        $stubDir = sys_get_temp_dir() . '/docker_stub_' . uniqid();
        mkdir($stubDir);
        file_put_contents($stubDir . '/docker', "#!/bin/sh\nexit 0\n");
        chmod($stubDir . '/docker', 0755);
        file_put_contents($stubDir . '/curl', "#!/bin/sh\nexit 0\n");
        chmod($stubDir . '/curl', 0755);

        $envPath = $stubDir . ':' . getenv('PATH');
        $cmd = sprintf(
            'PATH=%s DOMAIN=example.test APP_IMAGE=image NETWORK=webproxy %s/scripts/onboard_tenant.sh %s 2>&1',
            escapeshellarg($envPath),
            escapeshellarg($root),
            escapeshellarg($slug)
        );
        exec($cmd, $output, $ret);

        $this->assertSame(0, $ret, implode("\n", $output));
        $compose = $root . '/tenants/' . $slug . '/docker-compose.yml';
        $this->assertFileExists($compose);

        $json = json_decode(end($output), true);
        $this->assertIsArray($json);
        $this->assertSame('success', $json['status'] ?? '');
        $this->assertSame($slug, $json['slug'] ?? '');

        unlink($compose);
        rmdir(dirname($compose));
    }

    public function testOnboardTenantUsesEnvFile(): void
    {
        $slug = 't' . bin2hex(random_bytes(3));
        $root = dirname(__DIR__);
        $tenantDir = $root . '/tenants';
        if (!is_dir($tenantDir)) {
            mkdir($tenantDir);
        }

        $stubDir = sys_get_temp_dir() . '/docker_stub_' . uniqid();
        mkdir($stubDir);
        file_put_contents($stubDir . '/docker', "#!/bin/sh\nexit 0\n");
        chmod($stubDir . '/docker', 0755);
        file_put_contents($stubDir . '/curl', "#!/bin/sh\nexit 0\n");
        chmod($stubDir . '/curl', 0755);

        $envPath = $stubDir . ':' . getenv('PATH');
        file_put_contents($root . '/.env', "DOMAIN=example.test\nAPP_IMAGE=image\n");
        $cmd = sprintf(
            'PATH=%s %s/scripts/onboard_tenant.sh %s 2>&1',
            escapeshellarg($envPath),
            escapeshellarg($root),
            escapeshellarg($slug)
        );
        exec($cmd, $output, $ret);
        unlink($root . '/.env');

        $this->assertSame(0, $ret, implode("\n", $output));
        $compose = $root . '/tenants/' . $slug . '/docker-compose.yml';
        $this->assertFileExists($compose);

        $json = json_decode(end($output), true);
        $this->assertIsArray($json);
        $this->assertSame('success', $json['status'] ?? '');
        $this->assertSame($slug, $json['slug'] ?? '');

        unlink($compose);
        rmdir(dirname($compose));
    }
}
