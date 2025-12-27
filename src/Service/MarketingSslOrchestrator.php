<?php

declare(strict_types=1);

namespace App\Service;

use RuntimeException;
use Symfony\Component\Process\Process;

class MarketingSslOrchestrator
{
    private const DEFAULT_SCRIPT = '/usr/local/bin/marketing_ssl_orchestrator.sh';
    private const DEFAULT_USER = 'www-data';

    public function __construct(
        private readonly string $scriptPath = self::DEFAULT_SCRIPT,
        private readonly string $runAsUser = self::DEFAULT_USER
    ) {
    }

    public function trigger(?string $namespace = null, bool $dryRun = false, ?string $host = null): void
    {
        $namespace = $namespace !== null ? trim($namespace) : '';
        $host = $host !== null ? trim($host) : '';

        if ($namespace === '' && $host === '') {
            throw new RuntimeException('Namespace or host is required.');
        }

        $command = [
            'sudo',
            '-u',
            $this->runAsUser,
            $this->scriptPath,
        ];

        if ($host !== '') {
            $command[] = '--host';
            $command[] = $host;
        } else {
            $command[] = '--namespace';
            $command[] = $namespace;
        }

        if ($dryRun) {
            $command[] = '--dry-run';
        }

        $process = new Process($command);
        $process->setTimeout(null);
        $process->setIdleTimeout(null);
        $process->run();

        $stdout = $process->getOutput();
        $stderr = $process->getErrorOutput();

        if ($stdout !== '') {
            error_log('[marketing-ssl] stdout: ' . $stdout);
        }

        if ($stderr !== '') {
            error_log('[marketing-ssl] stderr: ' . $stderr);
        }

        if (!$process->isSuccessful()) {
            throw new RuntimeException('SSL provisioning failed.');
        }
    }
}
