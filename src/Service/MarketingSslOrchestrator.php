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
        $apiToken = getenv('MARKETING_SSL_API_TOKEN') ?: '';

        if ($namespace === '' && $host === '') {
            throw new RuntimeException('Namespace or host is required.');
        }

        if ($apiToken === '') {
            throw new RuntimeException('MARKETING_SSL_API_TOKEN is required.');
        }

        $command = [
            'sudo',
            '--preserve-env=MARKETING_SSL_API_TOKEN,MARKETING_SSL_API_URL,MARKETING_SSL_CONTACT_EMAIL',
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

        $processEnvironment = ['MARKETING_SSL_API_TOKEN' => $apiToken];

        $apiUrl = getenv('MARKETING_SSL_API_URL');
        if ($apiUrl !== false) {
            $processEnvironment['MARKETING_SSL_API_URL'] = $apiUrl;
        }

        $contactEmail = getenv('MARKETING_SSL_CONTACT_EMAIL');
        if ($contactEmail !== false) {
            $processEnvironment['MARKETING_SSL_CONTACT_EMAIL'] = $contactEmail;
        }

        $process = new Process($command, null, $processEnvironment);
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
