<?php

declare(strict_types=1);

namespace App\Service;

use App\Support\DomainNameHelper;
use InvalidArgumentException;
use RuntimeException;

use function App\runSyncProcess;

/**
 * Updates reverse proxy host lists and triggers reloads for new marketing domains.
 */
final class ReverseProxyHostUpdater
{
    private const MARKETING_ENV_KEY = 'MARKETING_DOMAINS';

    private DomainStartPageService $domainService;

    private NginxService $nginxService;

    private string $envFile;

    private string $nginxContainer;

    public function __construct(
        DomainStartPageService $domainService,
        NginxService $nginxService,
        ?string $envFile = null,
        ?string $nginxContainer = null
    ) {
        $this->domainService = $domainService;
        $this->nginxService = $nginxService;
        $this->envFile = $envFile ?? dirname(__DIR__, 2) . '/.env';
        $this->nginxContainer = $nginxContainer ?? (getenv('NGINX_CONTAINER') ?: 'nginx');
    }

    /**
     * Persist the new marketing domain into the reverse proxy host list and reload nginx.
     */
    public function persistMarketingDomain(string $host): void
    {
        $normalized = DomainNameHelper::normalize($host, stripAdmin: false);
        if ($normalized === '') {
            throw new InvalidArgumentException('Invalid marketing domain supplied.');
        }

        $domains = $this->collectMarketingDomains($normalized);
        $value = implode(',', $domains);

        $this->writeEnvValue($value);
        $this->setRuntimeEnv($value);
        $this->reloadProxy();
    }

    /**
     * @return list<string>
     */
    private function collectMarketingDomains(string $newHost): array
    {
        $domains = [];

        foreach ($this->domainService->listMarketingDomains() as $domain) {
            $host = $domain['host'] !== '' ? $domain['host'] : $domain['normalized_host'];
            $normalized = DomainNameHelper::normalize($host, stripAdmin: false);
            if ($normalized !== '') {
                $domains[] = $normalized;
            }
        }

        $existing = $this->readEnvValue() ?? (getenv(self::MARKETING_ENV_KEY) ?: '');
        foreach (preg_split('/[\s,]+/', $existing) ?: [] as $entry) {
            $normalized = DomainNameHelper::normalize((string) $entry, stripAdmin: false);
            if ($normalized !== '') {
                $domains[] = $normalized;
            }
        }

        $domains[] = $newHost;
        $domains = array_values(array_unique(array_filter($domains, static fn (string $value): bool => $value !== '')));

        return $domains;
    }

    private function readEnvValue(): ?string
    {
        if (!is_file($this->envFile)) {
            return null;
        }

        $lines = file($this->envFile, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            throw new RuntimeException('Unable to read environment file.');
        }

        foreach ($lines as $line) {
            if (!preg_match('/^\s*' . preg_quote(self::MARKETING_ENV_KEY, '/') . '\s*=/', $line)) {
                continue;
            }

            $parts = explode('=', $line, 2);
            if (count($parts) < 2) {
                return '';
            }

            return $this->sanitizeEnvValue($parts[1]);
        }

        return null;
    }

    private function sanitizeEnvValue(string $raw): string
    {
        $value = trim($raw);
        $value = preg_replace('/\s+#.*$/', '', $value) ?? $value;
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $firstChar = $value[0];
        $lastChar = $value[strlen($value) - 1];
        if (($firstChar === '"' && $lastChar === '"') || ($firstChar === "'" && $lastChar === "'")) {
            $value = substr($value, 1, -1);
        }

        return trim($value);
    }

    private function writeEnvValue(string $value): void
    {
        $line = self::MARKETING_ENV_KEY . '=' . $value;

        if (!file_exists($this->envFile)) {
            if (file_put_contents($this->envFile, $line . PHP_EOL) === false) {
                throw new RuntimeException('Unable to create environment file.');
            }

            return;
        }

        if (!is_writable($this->envFile)) {
            throw new RuntimeException('Environment file is not writable.');
        }

        $lines = file($this->envFile, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            throw new RuntimeException('Unable to read environment file.');
        }

        $updated = false;
        foreach ($lines as $index => $existing) {
            if (!preg_match('/^\s*' . preg_quote(self::MARKETING_ENV_KEY, '/') . '\s*=/', $existing)) {
                continue;
            }

            $lines[$index] = $line;
            $updated = true;
        }

        if (!$updated) {
            $lines[] = $line;
        }

        $content = implode(PHP_EOL, $lines) . PHP_EOL;
        if (file_put_contents($this->envFile, $content) === false) {
            throw new RuntimeException('Unable to persist environment file.');
        }
    }

    private function setRuntimeEnv(string $value): void
    {
        putenv(self::MARKETING_ENV_KEY . '=' . $value);
        $_ENV[self::MARKETING_ENV_KEY] = $value;
    }

    private function reloadProxy(): void
    {
        $reloadFlag = getenv('NGINX_RELOAD');
        if ($reloadFlag === '0') {
            return;
        }

        $reloaderUrl = getenv('NGINX_RELOADER_URL') ?: '';
        if ($reloaderUrl !== '') {
            $this->nginxService->reload();
            return;
        }

        $result = runSyncProcess('docker', ['exec', $this->nginxContainer, 'nginx', '-s', 'reload']);
        if (!$result['success']) {
            $message = $result['stderr'] !== '' ? $result['stderr'] : $result['stdout'];
            throw new RuntimeException('Failed to reload nginx: ' . trim($message));
        }
    }
}
