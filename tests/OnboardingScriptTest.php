<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class OnboardingScriptTest extends TestCase
{
    public function testCreateTenantCleansUpAfterFailure(): void {
        $slug = 'c' . bin2hex(random_bytes(3));
        $root = dirname(__DIR__);
        $envFile = $root . '/.env';

        file_put_contents(
            $envFile,
            "SERVICE_USER=svc\n" .
            "SERVICE_PASS=pass\n" .
            "DOMAIN=example.test\n" .
            "MAIN_DOMAIN=example.test\n" .
            "NGINX_RELOAD=0\n"
        );

        $certDir = $root . '/certs';
        $certDirExisted = is_dir($certDir);
        if (!$certDirExisted) {
            mkdir($certDir, 0775, true);
        }
        file_put_contents($certDir . '/example.test.crt', 'dummy-cert');
        file_put_contents($certDir . '/example.test.key', 'dummy-key');

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
            clearstatcache(true, $vhostFile);
            $this->assertFileDoesNotExist($vhostFile);
        } finally {
            if (file_exists($envFile)) {
                unlink($envFile);
            }
            @unlink($root . '/vhost.d/' . strtolower($slug) . '.example.test');
            @unlink($certDir . '/example.test.crt');
            @unlink($certDir . '/example.test.key');
            if (!$certDirExisted && is_dir($certDir)) {
                $entries = array_diff(scandir($certDir), ['.', '..']);
                if (count($entries) === 0) {
                    rmdir($certDir);
                }
            }
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
