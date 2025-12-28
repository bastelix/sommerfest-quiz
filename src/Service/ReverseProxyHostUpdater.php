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
    private const SLIM_VIRTUAL_HOSTS_ENV_KEY = 'SLIM_VIRTUAL_HOSTS';
    private const SLIM_LETSENCRYPT_HOSTS_ENV_KEY = 'SLIM_LETSENCRYPT_HOSTS';

    private DomainService $domainService;

    private NginxService $nginxService;

    private string $envFile;

    private string $nginxContainer;

    private string $projectDir;

    private string $slimService;

    private string $dockerBinary;

    public function __construct(
        DomainService $domainService,
        NginxService $nginxService,
        ?string $envFile = null,
        ?string $nginxContainer = null,
        ?string $projectDir = null,
        ?string $slimService = null,
        ?string $dockerBinary = null
    ) {
        $this->domainService = $domainService;
        $this->nginxService = $nginxService;
        $this->envFile = $envFile ?? dirname(__DIR__, 2) . '/.env';
        $this->nginxContainer = $nginxContainer ?? (getenv('NGINX_CONTAINER') ?: 'nginx');
        $this->projectDir = $projectDir ?? dirname(__DIR__, 2);
        $this->slimService = $slimService ?? 'slim';
        $this->dockerBinary = $dockerBinary ?? 'docker';
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

        $marketingChanged = $this->writeEnvValue(self::MARKETING_ENV_KEY, $value);
        $this->setRuntimeEnv(self::MARKETING_ENV_KEY, $value);

        $combinedHosts = $this->collectSlimHosts($domains);
        $combinedValue = implode(',', $combinedHosts);

        $virtualChanged = $this->writeEnvValue(self::SLIM_VIRTUAL_HOSTS_ENV_KEY, $combinedValue);
        $letsencryptChanged = $this->writeEnvValue(self::SLIM_LETSENCRYPT_HOSTS_ENV_KEY, $combinedValue);

        $this->setRuntimeEnv(self::SLIM_VIRTUAL_HOSTS_ENV_KEY, $combinedValue);
        $this->setRuntimeEnv(self::SLIM_LETSENCRYPT_HOSTS_ENV_KEY, $combinedValue);

        if ($virtualChanged || $letsencryptChanged) {
            $this->recreateSlimContainer();
        }

        if ($marketingChanged || $virtualChanged || $letsencryptChanged) {
            $this->reloadProxy();
        }
    }

    /**
     * @return list<string>
     */
    private function collectMarketingDomains(string $newHost): array
    {
        $domains = [];

        foreach ($this->domainService->listDomains() as $domain) {
            $host = $domain['host'] !== '' ? $domain['host'] : $domain['normalized_host'];
            $normalized = DomainNameHelper::normalize($host, stripAdmin: false);
            if ($normalized !== '') {
                $domains[] = $normalized;
            }
        }

        $existing = $this->readEnvValue(self::MARKETING_ENV_KEY) ?? (getenv(self::MARKETING_ENV_KEY) ?: '');
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

    /**
     * @return list<string>
     */
    private function collectSlimHosts(array $marketingHosts): array
    {
        $hosts = [];

        $sources = [
            getenv(self::SLIM_VIRTUAL_HOSTS_ENV_KEY) ?: '',
            getenv('SLIM_VIRTUAL_HOST') ?: '',
            getenv(self::SLIM_LETSENCRYPT_HOSTS_ENV_KEY) ?: '',
            getenv('SLIM_LETSENCRYPT_HOST') ?: '',
            getenv('SLIM_VIRTUAL_HOSTS') ?: '',
            getenv('SLIM_LETSENCRYPT_HOSTS') ?: '',
            getenv('MAIN_DOMAIN') ?: '',
            getenv('DOMAIN') ?: '',
        ];

        foreach ($sources as $source) {
            foreach (preg_split('/[\s,]+/', $source) ?: [] as $host) {
                $normalized = DomainNameHelper::normalize($host, stripAdmin: false);
                if ($normalized !== '') {
                    $hosts[] = $normalized;
                }
            }
        }

        foreach ($marketingHosts as $host) {
            $normalized = DomainNameHelper::normalize($host, stripAdmin: false);
            if ($normalized !== '') {
                $hosts[] = $normalized;
            }
        }

        return array_values(array_unique($hosts));
    }

    private function readEnvValue(string $key): ?string
    {
        if (!is_file($this->envFile)) {
            return null;
        }

        $lines = file($this->envFile, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            throw new RuntimeException('Unable to read environment file.');
        }

        foreach ($lines as $line) {
            if (!preg_match('/^\s*' . preg_quote($key, '/') . '\s*=/', $line)) {
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

    private function writeEnvValue(string $key, string $value): bool
    {
        $line = $key . '=' . $value;
        $current = $this->readEnvValue($key);

        if ($current !== null && $current === $value) {
            return false;
        }

        if (!file_exists($this->envFile)) {
            if (file_put_contents($this->envFile, $line . PHP_EOL) === false) {
                throw new RuntimeException('Unable to create environment file.');
            }

            return true;
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
            if (!preg_match('/^\s*' . preg_quote($key, '/') . '\s*=/', $existing)) {
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

        return true;
    }

    private function setRuntimeEnv(string $key, string $value): void
    {
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
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

    private function recreateSlimContainer(): void
    {
        $result = runSyncProcess(
            $this->dockerBinary,
            ['compose', '--project-directory', $this->projectDir, 'up', '-d', '--force-recreate', $this->slimService]
        );

        if (!$result['success']) {
            $message = $result['stderr'] !== '' ? $result['stderr'] : $result['stdout'];
            throw new RuntimeException('Failed to recreate slim container: ' . trim($message));
        }
    }
}
