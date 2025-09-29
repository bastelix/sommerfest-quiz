<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

class DeleteTenantScriptTest extends TestCase
{
    public function testDeleteTenantRemovesSslArtifacts(): void {
        $slug = 't' . bin2hex(random_bytes(3));
        $root = dirname(__DIR__);
        $domain = 'example.test';

        $envFile = $root . '/.env';
        file_put_contents($envFile, "DOMAIN=$domain\nNGINX_RELOAD=0\n");

        $vhost = "$root/vhost.d/$slug.$domain";
        file_put_contents($vhost, 'dummy');

        $certCrt = "$root/certs/$slug.$domain.crt";
        $certKey = "$root/certs/$slug.$domain.key";
        touch($certCrt);
        touch($certKey);

        $acmeDir = "$root/acme/$slug.$domain";
        $acmeDirEcc = $acmeDir . '_ecc';
        mkdir($acmeDir);
        mkdir($acmeDirEcc);

        $stubDir = sys_get_temp_dir() . '/curl_stub_' . uniqid();
        mkdir($stubDir);
        file_put_contents($stubDir . '/curl', "#!/bin/sh\nexit 0\n");
        chmod($stubDir . '/curl', 0755);
        $envPath = $stubDir . ':' . getenv('PATH');

        $cmd = sprintf(
            'PATH=%s %s/scripts/delete_tenant.sh %s --subdomain 2>&1',
            escapeshellarg($envPath),
            escapeshellarg($root),
            escapeshellarg($slug)
        );
        exec($cmd, $output, $ret);

        $this->assertSame(0, $ret, implode("\n", $output));
        $this->assertFileDoesNotExist($vhost);
        $this->assertFileDoesNotExist($certCrt);
        $this->assertFileDoesNotExist($certKey);
        $this->assertDirectoryDoesNotExist($acmeDir);
        $this->assertDirectoryDoesNotExist($acmeDirEcc);

        unlink($envFile);
        @rmdir($stubDir);
    }
}
