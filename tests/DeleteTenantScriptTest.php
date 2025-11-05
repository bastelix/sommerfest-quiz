<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

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

        $process = new Process(
            [
                $root . '/scripts/delete_tenant.sh',
                $slug,
                '--subdomain',
            ],
            $root,
            [
                'PATH' => $envPath,
            ]
        );

        try {
            $process->mustRun();
        } catch (ProcessFailedException $exception) {
            $this->fail(
                sprintf(
                    'Process failed with exit code %d.%s%s',
                    $process->getExitCode(),
                    PHP_EOL . $process->getOutput(),
                    $process->getErrorOutput() !== '' ? PHP_EOL . $process->getErrorOutput() : ''
                )
            );
        }

        $this->assertSame(
            0,
            $process->getExitCode(),
            sprintf(
                "Process failed with output:%s%s",
                PHP_EOL . $process->getOutput(),
                $process->getErrorOutput() !== '' ? PHP_EOL . $process->getErrorOutput() : ''
            )
        );
        $this->assertFileDoesNotExist($vhost);
        $this->assertFileDoesNotExist($certCrt);
        $this->assertFileDoesNotExist($certKey);
        $this->assertDirectoryDoesNotExist($acmeDir);
        $this->assertDirectoryDoesNotExist($acmeDirEcc);

        if (file_exists($envFile)) {
            unlink($envFile);
        }
        if (file_exists($stubDir . '/curl')) {
            unlink($stubDir . '/curl');
        }
        if (is_dir($stubDir)) {
            @rmdir($stubDir);
        }
    }
}
