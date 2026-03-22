<?php

declare(strict_types=1);

namespace App\Service;

use PDO;

/**
 * Generates standalone nginx server block configs for marketing domains
 * and provisions SSL certificates via acme.sh.
 *
 * docker-gen only sees env vars from docker-compose.yml, not entrypoint
 * exports, so marketing domains from the database need their own nginx
 * configs and certificates managed independently.
 */
final class MarketingProxySyncService
{
    private const CONF_DIR = '/etc/nginx/conf.d';
    private const CERT_DIR = '/etc/nginx/certs';
    private const WEBROOT = '/usr/share/nginx/html';
    private const CONF_PREFIX = 'marketing_';
    private const UPSTREAM = 'http://slim-1:8080';

    private PDO $pdo;
    private string $confDir;
    private string $certDir;
    private string $webroot;
    private string $upstream;
    private ?string $nginxReloaderUrl;
    private ?string $nginxReloadToken;
    private string $acmeBin;
    private string $acmeEmail;

    public function __construct(
        PDO $pdo,
        ?string $confDir = null,
        ?string $certDir = null,
        ?string $upstream = null,
        ?string $webroot = null
    ) {
        $this->pdo = $pdo;
        $this->confDir = $confDir ?? self::CONF_DIR;
        $this->certDir = $certDir ?? self::CERT_DIR;
        $this->webroot = $webroot ?? self::WEBROOT;
        $this->upstream = $upstream ?? self::UPSTREAM;
        $this->nginxReloaderUrl = getenv('NGINX_RELOADER_URL') ?: null;
        $this->nginxReloadToken = getenv('NGINX_RELOAD_TOKEN') ?: null;
        $this->acmeBin = getenv('ACME_SH_BIN') ?: 'acme.sh';
        $this->acmeEmail = getenv('LETSENCRYPT_EMAIL') ?: '';
    }

    /**
     * Synchronise nginx server block configs for all active marketing domains.
     *
     * @return array{written: int, removed: int, reloaded: bool, certs_issued: int}
     */
    public function sync(): array
    {
        $domains = $this->loadActiveDomains();
        $mainDomain = $this->getMainDomain();

        // Exclude the main domain and its subdomains — docker-gen handles those.
        $domains = array_values(array_filter(
            $domains,
            static fn (string $h): bool => $mainDomain === ''
                || ($h !== $mainDomain && !str_ends_with($h, '.' . $mainDomain))
        ));

        $written = 0;
        $activeFiles = [];

        foreach ($domains as $host) {
            $safeHost = $this->sanitiseHostForFilename($host);
            if ($safeHost === '') {
                continue;
            }

            $file = $this->confDir . '/' . self::CONF_PREFIX . $safeHost . '.conf';
            $activeFiles[] = $file;
            $config = $this->generateServerBlock($host);

            if (is_file($file) && file_get_contents($file) === $config) {
                continue;
            }

            if (file_put_contents($file, $config) !== false) {
                $written++;
            }
        }

        $removed = $this->removeStaleConfigs($activeFiles);

        $reloaded = false;
        if ($written > 0 || $removed > 0) {
            $reloaded = $this->triggerNginxReload();
        }

        // After nginx is serving HTTP for these domains, attempt to
        // provision SSL certificates for any that don't have one yet.
        $certsIssued = 0;
        if ($reloaded || $written === 0) {
            foreach ($domains as $host) {
                if ($this->provisionCertificate($host)) {
                    $certsIssued++;
                }
            }

            // Re-generate configs with SSL enabled and reload.
            if ($certsIssued > 0) {
                foreach ($domains as $host) {
                    $safeHost = $this->sanitiseHostForFilename($host);
                    if ($safeHost === '') {
                        continue;
                    }
                    $file = $this->confDir . '/' . self::CONF_PREFIX . $safeHost . '.conf';
                    $config = $this->generateServerBlock($host);
                    file_put_contents($file, $config);
                }
                $this->triggerNginxReload();
            }
        }

        return [
            'written' => $written,
            'removed' => $removed,
            'reloaded' => $reloaded,
            'certs_issued' => $certsIssued,
        ];
    }

    /**
     * Synchronise a single domain (used after domain create/update).
     */
    public function syncDomain(string $host): bool
    {
        $safeHost = $this->sanitiseHostForFilename($host);
        if ($safeHost === '') {
            return false;
        }

        $file = $this->confDir . '/' . self::CONF_PREFIX . $safeHost . '.conf';
        $config = $this->generateServerBlock($host);
        $current = is_file($file) ? file_get_contents($file) : null;

        if ($current !== $config) {
            if (file_put_contents($file, $config) === false) {
                return false;
            }
            $this->triggerNginxReload();
        }

        // Attempt cert provisioning (non-blocking; may fail if DNS not ready).
        if ($this->provisionCertificate($host)) {
            $config = $this->generateServerBlock($host);
            file_put_contents($file, $config);
            $this->triggerNginxReload();
        }

        return true;
    }

    /**
     * Remove the nginx config for a domain (used after domain delete/deactivation).
     */
    public function removeDomain(string $host): bool
    {
        $safeHost = $this->sanitiseHostForFilename($host);
        if ($safeHost === '') {
            return false;
        }

        $file = $this->confDir . '/' . self::CONF_PREFIX . $safeHost . '.conf';
        if (!is_file($file)) {
            return true;
        }

        @unlink($file);

        return $this->triggerNginxReload();
    }

    /**
     * Provision an SSL certificate via acme.sh using HTTP-01 webroot validation.
     *
     * Returns true if a NEW certificate was issued (not if one already exists).
     */
    private function provisionCertificate(string $host): bool
    {
        $certFile = $this->certDir . '/' . $host . '.crt';
        $keyFile = $this->certDir . '/' . $host . '.key';

        // Skip if a valid certificate already exists.
        if (is_file($certFile) && is_file($keyFile)) {
            if (!$this->isCertExpiringSoon($certFile)) {
                return false;
            }
        }

        if (!is_executable($this->acmeBin) && !$this->commandExists($this->acmeBin)) {
            error_log('MarketingProxySyncService: acme.sh not found at ' . $this->acmeBin);
            return false;
        }

        $issueCommand = [
            $this->acmeBin,
            '--issue',
            '-d', $host,
            '--webroot', $this->webroot,
            '--server', 'letsencrypt',
        ];

        if ($this->acmeEmail !== '') {
            $issueCommand[] = '--accountemail';
            $issueCommand[] = $this->acmeEmail;
        }

        error_log('MarketingProxySyncService: issuing cert for ' . $host);
        if (!$this->runCommand($issueCommand)) {
            error_log('MarketingProxySyncService: cert issue failed for ' . $host);
            return false;
        }

        $installCommand = [
            $this->acmeBin,
            '--install-cert',
            '-d', $host,
            '--fullchain-file', $certFile,
            '--key-file', $keyFile,
        ];

        if (!$this->runCommand($installCommand)) {
            error_log('MarketingProxySyncService: cert install failed for ' . $host);
            return false;
        }

        error_log('MarketingProxySyncService: cert provisioned for ' . $host);
        return true;
    }

    private function isCertExpiringSoon(string $certFile): bool
    {
        $content = @file_get_contents($certFile);
        if ($content === false) {
            return true;
        }

        $cert = @openssl_x509_parse($content);
        if (!is_array($cert) || !isset($cert['validTo_time_t'])) {
            return true;
        }

        // Renew 30 days before expiry.
        return time() > ($cert['validTo_time_t'] - 30 * 86400);
    }

    /**
     * Determine the main domain that docker-gen already handles.
     */
    private function getMainDomain(): string
    {
        $domain = getenv('MAIN_DOMAIN');
        if ($domain !== false && $domain !== '') {
            return strtolower(trim($domain));
        }

        $domain = getenv('DOMAIN');
        if ($domain !== false && $domain !== '') {
            return strtolower(trim($domain));
        }

        return '';
    }

    /**
     * @return list<string>
     */
    private function loadActiveDomains(): array
    {
        $stmt = $this->pdo->query(
            'SELECT host FROM domains WHERE is_active = TRUE AND namespace IS NOT NULL ORDER BY host ASC'
        );

        if ($stmt === false) {
            return [];
        }

        $hosts = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $host) {
            $normalized = strtolower(trim((string) $host));
            if ($normalized !== '') {
                $hosts[] = $normalized;
            }
        }

        return $hosts;
    }

    private function generateServerBlock(string $host): string
    {
        $escapedHost = $this->escapeNginxValue($host);
        $certFile = $this->certDir . '/' . $host . '.crt';
        $keyFile = $this->certDir . '/' . $host . '.key';
        $hasCert = is_file($certFile) && is_file($keyFile);

        $proxyBlock = <<<'NGINX'
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_http_version 1.1;
        proxy_set_header Connection "";
NGINX;

        $config = "# Auto-generated by MarketingProxySyncService – do not edit manually.\n";

        if ($hasCert) {
            $config .= <<<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name {$escapedHost};

    location /.well-known/acme-challenge/ {
        root {$this->webroot};
    }

    location / {
        return 301 https://\$host\$request_uri;
    }
}

server {
    listen 443 ssl;
    listen [::]:443 ssl;
    server_name {$escapedHost};

    ssl_certificate {$certFile};
    ssl_certificate_key {$keyFile};

    location / {
{$proxyBlock}
        proxy_pass {$this->upstream};
    }
}

NGINX;
        } else {
            $config .= <<<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name {$escapedHost};

    location /.well-known/acme-challenge/ {
        root {$this->webroot};
    }

    location / {
{$proxyBlock}
        proxy_pass {$this->upstream};
    }
}

NGINX;
        }

        return $config;
    }

    private function sanitiseHostForFilename(string $host): string
    {
        $host = strtolower(trim($host));

        return preg_replace('/[^a-z0-9._-]/', '_', $host) ?? '';
    }

    private function escapeNginxValue(string $value): string
    {
        return preg_replace('/[^a-z0-9.*_-]/i', '', $value) ?? '';
    }

    /**
     * Remove marketing config files that are no longer active.
     *
     * @param list<string> $activeFiles
     */
    private function removeStaleConfigs(array $activeFiles): int
    {
        $pattern = $this->confDir . '/' . self::CONF_PREFIX . '*.conf';
        $existing = glob($pattern) ?: [];
        $removed = 0;

        foreach ($existing as $file) {
            if (!in_array($file, $activeFiles, true)) {
                @unlink($file);
                $removed++;
            }
        }

        return $removed;
    }

    private function triggerNginxReload(): bool
    {
        if ($this->nginxReloaderUrl === null || $this->nginxReloaderUrl === '') {
            return false;
        }

        $ch = curl_init($this->nginxReloaderUrl);
        if ($ch === false) {
            return false;
        }

        $headers = ['Content-Type: application/json'];
        if ($this->nginxReloadToken !== null && $this->nginxReloadToken !== '') {
            $headers[] = 'X-Token: ' . $this->nginxReloadToken;
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
        ]);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $result !== false && $httpCode >= 200 && $httpCode < 300;
    }

    /**
     * @param list<string> $command
     */
    private function runCommand(array $command): bool
    {
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            return false;
        }

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        $exitCode = proc_close($process);
        if ($stdout !== '' && $stdout !== false) {
            error_log($stdout);
        }
        if ($stderr !== '' && $stderr !== false) {
            error_log($stderr);
        }

        return $exitCode === 0;
    }

    private function commandExists(string $command): bool
    {
        $which = trim((string) shell_exec('which ' . escapeshellarg($command) . ' 2>/dev/null'));

        return $which !== '';
    }
}
