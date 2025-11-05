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
                'MAIN_DOMAIN' => 'example.test',
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
        file_put_contents($envFile, "DOMAIN=example.test\nMAIN_DOMAIN=example.test\nAPP_IMAGE=image\n");
        $process = new Process(
            [
                $root . '/scripts/onboard_tenant.sh',
                $slug,
            ],
            $root,
            [
                'PATH' => $envPath,
                'MAIN_DOMAIN' => 'example.test',
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

    public function testOnboardTenantCleansUpAfterFailure(): void {
        $slug = 't' . bin2hex(random_bytes(3));
        $root = dirname(__DIR__);
        $tenantRoot = $root . '/tenants';
        if (!is_dir($tenantRoot)) {
            mkdir($tenantRoot);
        }

        $vhostDir = $root . '/vhost.d';
        if (!is_dir($vhostDir)) {
            mkdir($vhostDir);
        }

        $vhostFile = $vhostDir . '/' . $slug . '.example.test';
        file_put_contents($vhostFile, 'client_max_body_size 50m;');

        $stubDir = sys_get_temp_dir() . '/onboard_stub_' . uniqid('', true);
        mkdir($stubDir);
        file_put_contents(
            $stubDir . '/docker',
            <<<'SH'
#!/bin/sh
case "$1" in
  compose|network|image)
    exit 0
    ;;
esac
exit 0
SH
        );
        chmod($stubDir . '/docker', 0755);

        file_put_contents(
            $stubDir . '/curl',
            <<<'SH'
#!/bin/sh
exit 1
SH
        );
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
                'MAIN_DOMAIN' => 'example.test',
                'APP_IMAGE' => 'image',
                'NETWORK' => 'webproxy',
            ]
        );

        $process->run();

        $this->assertSame(1, $process->getExitCode());
        $tenantDir = $root . '/tenants/' . $slug;
        clearstatcache(true, $tenantDir);
        clearstatcache(true, $vhostFile);
        $this->assertDirectoryDoesNotExist($tenantDir);
        $this->assertFileDoesNotExist($vhostFile);

        @unlink($vhostFile);
        $this->removeDirectory($stubDir);
    }

    public function testCreateTenantCleansUpAfterFailure(): void {
        $slug = 'c' . bin2hex(random_bytes(3));
        $root = dirname(__DIR__);
        $envFile = $root . '/.env';
        $tenantsDir = $root . '/tenants';
        if (!is_dir($tenantsDir)) {
            mkdir($tenantsDir);
        }

        file_put_contents(
            $envFile,
            "SERVICE_USER=svc\n" .
            "SERVICE_PASS=pass\n" .
            "DOMAIN=example.test\n" .
            "MAIN_DOMAIN=example.test\n" .
            "NGINX_RELOAD=0\n"
        );

        $stubDir = sys_get_temp_dir() . '/create_stub_' . uniqid('', true);
        mkdir($stubDir);

        file_put_contents(
            $stubDir . '/curl',
            <<<'SH'
#!/bin/sh
output=""
cookie=""
url=""
while [ "$#" -gt 0 ]; do
  case "$1" in
    -o)
      shift
      if [ "$#" -gt 0 ]; then
        output="$1"
        shift
      fi
      continue
      ;;
    -c)
      shift
      if [ "$#" -gt 0 ]; then
        cookie="$1"
        : > "$cookie"
        shift
      fi
      continue
      ;;
    -b|-H|-d|-X|-w)
      shift
      [ "$#" -gt 0 ] && shift
      continue
      ;;
    -s|-f)
      shift
      continue
      ;;
    *)
      url="$1"
      shift
      continue
      ;;
  esac
done

if [ -n "$output" ]; then
  : > "$output"
fi

case "$url" in
  */login)
    exit 0
    ;;
  */tenants)
    printf '201'
    exit 0
    ;;
  */onboard)
    printf '500'
    exit 1
    ;;
  *)
    exit 0
    ;;
esac
SH
        );
        chmod($stubDir . '/curl', 0755);

        file_put_contents(
            $stubDir . '/docker',
            "#!/bin/sh\nexit 0\n"
        );
        chmod($stubDir . '/docker', 0755);

        $envPath = $stubDir . ':' . getenv('PATH');
        $tenantSlug = strtolower(preg_replace('/[^a-z0-9-]/', '-', $slug));
        $tenantDir = $tenantsDir . '/' . $tenantSlug;
        mkdir($tenantDir);
        file_put_contents($tenantDir . '/placeholder', 'keep');

        $process = new Process(
            [
                $root . '/scripts/create_tenant.sh',
                $slug,
            ],
            $root,
            [
                'PATH' => $envPath,
            ]
        );

        try {
            $process->run();

            $this->assertSame(1, $process->getExitCode());
            $vhostFile = $root . '/vhost.d/' . strtolower($slug) . '.example.test';
            clearstatcache(true, $tenantDir);
            clearstatcache(true, $vhostFile);
            $this->assertFileDoesNotExist($vhostFile);
            $this->assertDirectoryDoesNotExist($tenantDir);
        } finally {
            if (file_exists($envFile)) {
                unlink($envFile);
            }
            @unlink($root . '/vhost.d/' . strtolower($slug) . '.example.test');
            $this->removeDirectory($stubDir);
        }
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($directory);
    }
}
