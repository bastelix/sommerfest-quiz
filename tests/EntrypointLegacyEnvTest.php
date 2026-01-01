<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

class EntrypointLegacyEnvTest extends TestCase
{
    public function testEntrypointMigratesLegacyVariables(): void
    {
        $projectRoot = dirname(__DIR__);
        $entrypoint = $projectRoot . '/docker-entrypoint.sh';

        if (!is_dir('/usr/local/etc/php/conf.d')) {
            mkdir('/usr/local/etc/php/conf.d', 0o755, true);
        }

        $env = [
            'PATH' => getenv('PATH') ?: '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
            'CONTAINER_METRICS_LOGGING' => '0',
            'REQUEST_SSL_ON_STARTUP' => '0',
            'SLIM_VIRTUAL_HOSTS' => 'legacy.example.com',
            'SLIM_LETSENCRYPT_HOSTS' => 'legacy-https.example.com',
            'POSTGRES_PASS' => 'legacy-pass',
            'VIRTUAL_HOST' => '',
            'LETSENCRYPT_HOST' => '',
            'SLIM_VIRTUAL_HOST' => '',
            'SLIM_LETSENCRYPT_HOST' => '',
            'POSTGRES_PASSWORD' => '',
            'POSTGRES_DSN' => '',
        ];

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open("cd {$projectRoot} && {$entrypoint} env", $descriptorSpec, $pipes, $projectRoot, $env);

        $this->assertIsResource($process, 'Failed to start entrypoint process');

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        $this->assertSame(0, $exitCode, $stderr);

        $exportedEnv = [];
        foreach (preg_split('/\r?\n/', trim((string) $stdout)) as $line) {
            if ($line === '' || strpos($line, '=') === false) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);
            $exportedEnv[$name] = $value;
        }

        $this->assertSame('legacy.example.com', $exportedEnv['VIRTUAL_HOST'] ?? null);
        $this->assertSame('legacy-https.example.com', $exportedEnv['LETSENCRYPT_HOST'] ?? null);
        $this->assertSame('legacy-pass', $exportedEnv['POSTGRES_PASSWORD'] ?? null);
    }
}
