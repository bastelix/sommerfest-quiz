<?php

declare(strict_types=1);

namespace App\Service;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;

class NginxService
{
    private string $vhostDir;
    private string $domain;
    private string $clientMaxBodySize;
    private bool $reload;
    private string $reloaderUrl;
    private string $reloadToken;
    private \GuzzleHttp\ClientInterface $httpClient;

    public function __construct(
        ?string $vhostDir = null,
        ?string $domain = null,
        ?string $clientMaxBodySize = null,
        ?bool $reload = null,
        ?string $reloaderUrl = null,
        ?string $reloadToken = null,
        ?\GuzzleHttp\ClientInterface $httpClient = null
    ) {
        $this->vhostDir = $vhostDir ?? dirname(__DIR__, 2) . '/vhost.d';
        $this->domain = $this->resolveDomain($domain);
        $this->clientMaxBodySize = $clientMaxBodySize ?? (getenv('CLIENT_MAX_BODY_SIZE') ?: '50m');
        $this->reloaderUrl = $reloaderUrl ?? (getenv('NGINX_RELOADER_URL') ?: '');
        $this->reload = $this->resolveReloadFlag($reload);
        $this->reloadToken = $reloadToken ?? (getenv('NGINX_RELOAD_TOKEN') ?: '');
        $this->httpClient = $httpClient ?? new \GuzzleHttp\Client();
    }

    public function createVhost(string $sub): void {
        if ($this->domain === '') {
            throw new \RuntimeException('Tenant domain not configured (set MAIN_DOMAIN or DOMAIN)');
        }
        if (!is_dir($this->vhostDir) && !mkdir($this->vhostDir, 0777, true) && !is_dir($this->vhostDir)) {
            throw new \RuntimeException('Unable to create vhost directory');
        }
        if (!is_writable($this->vhostDir)) {
            throw new \RuntimeException('Vhost directory not writable');
        }
        $file = $this->vhostDir . '/' . $sub . '.' . $this->domain;
        if (file_exists($file) && !is_writable($file)) {
            throw new \RuntimeException('Vhost file not writable');
        }
        if (file_put_contents($file, 'client_max_body_size ' . $this->clientMaxBodySize . ';') === false) {
            throw new \RuntimeException('Unable to write vhost file');
        }
        if ($this->reload) {
            $this->reload();
        }
    }

    private function resolveDomain(?string $domain): string
    {
        if ($domain !== null && $domain !== '') {
            return $domain;
        }

        $main = getenv('MAIN_DOMAIN') ?: '';
        if ($main !== '') {
            return $main;
        }

        $fallback = getenv('DOMAIN') ?: '';

        return $fallback;
    }

    private function resolveReloadFlag(?bool $reload): bool
    {
        if ($reload !== null) {
            return $reload;
        }

        if ($this->reloaderUrl !== '') {
            return true;
        }

        return getenv('NGINX_RELOAD') !== '0';
    }

    /**
     * Trigger nginx reload via the reloader service.
     */
    public function reload(): void {
        try {
            $res = $this->httpClient->request(
                'POST',
                $this->reloaderUrl,
                ['headers' => ['X-Token' => $this->reloadToken]]
            );
            if ($res->getStatusCode() >= 300) {
                throw new \RuntimeException('HTTP ' . $res->getStatusCode());
            }
        } catch (\Throwable $e) {
            throw new \RuntimeException('nginx reload failed: ' . $e->getMessage(), 0, $e);
        }
    }
}
