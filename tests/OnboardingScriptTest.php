<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class OnboardingScriptTest extends TestCase
{
    public function testOnboardTenantCreatesComposeFile(): void {
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
        $process = new Process(
            [
                $root . '/scripts/onboard_tenant.sh',
                $slug,
            ],
            $root,
            [
                'PATH' => $envPath,
                'DOMAIN' => 'example.test',
                'APP_IMAGE' => 'image',
                'NETWORK' => 'webproxy',
            ]
        );

        $compose = $root . '/tenants/' . $slug . '/docker-compose.yml';

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

        $this->assertFileExists($compose);

        $outputLines = preg_split('/\r?\n/', trim($process->getOutput()));
        $outputLines = array_values(array_filter($outputLines, static fn ($line) => $line !== ''));
        $lastLine = $outputLines !== [] ? end($outputLines) : '';
        $json = json_decode($lastLine !== '' ? $lastLine : $process->getErrorOutput(), true);
        $this->assertIsArray($json);
        $this->assertSame('success', $json['status'] ?? '');
        $this->assertSame($slug, $json['slug'] ?? '');

        if (file_exists($compose)) {
            unlink($compose);
        }
        if (is_dir(dirname($compose))) {
            @rmdir(dirname($compose));
        }
    }

    public function testOnboardTenantUsesEnvFile(): void {
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
        $envFile = $root . '/.env';
        file_put_contents($envFile, "DOMAIN=example.test\nAPP_IMAGE=image\n");
        $process = new Process(
            [
                $root . '/scripts/onboard_tenant.sh',
                $slug,
            ],
            $root,
            [
                'PATH' => $envPath,
            ]
        );

        $compose = $root . '/tenants/' . $slug . '/docker-compose.yml';

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

        $this->assertFileExists($compose);

        $outputLines = preg_split('/\r?\n/', trim($process->getOutput()));
        $outputLines = array_values(array_filter($outputLines, static fn ($line) => $line !== ''));
        $lastLine = $outputLines !== [] ? end($outputLines) : '';
        $json = json_decode($lastLine !== '' ? $lastLine : $process->getErrorOutput(), true);
        $this->assertIsArray($json);
        $this->assertSame('success', $json['status'] ?? '');
        $this->assertSame($slug, $json['slug'] ?? '');

        if (file_exists($envFile)) {
            unlink($envFile);
        }
        if (file_exists($compose)) {
            unlink($compose);
        }
        if (is_dir(dirname($compose))) {
            @rmdir(dirname($compose));
        }
    }
}
