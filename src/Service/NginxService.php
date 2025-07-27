<?php

declare(strict_types=1);

namespace App\Service;

class NginxService
{
    private string $vhostDir;
    private string $domain;
    private string $clientMaxBodySize;
    private bool $reload;

    public function __construct(
        ?string $vhostDir = null,
        ?string $domain = null,
        ?string $clientMaxBodySize = null,
        ?bool $reload = null
    ) {
        $this->vhostDir = $vhostDir ?? dirname(__DIR__, 2) . '/vhost.d';
        $this->domain = $domain ?? (getenv('DOMAIN') ?: '');
        $this->clientMaxBodySize = $clientMaxBodySize ?? (getenv('CLIENT_MAX_BODY_SIZE') ?: '50m');
        $this->reload = $reload ?? (getenv('NGINX_RELOAD') !== '0');
    }

    public function createVhost(string $sub): void
    {
        if ($this->domain === '') {
            throw new \RuntimeException('DOMAIN not configured');
        }
        if (!is_dir($this->vhostDir) && !mkdir($this->vhostDir, 0777, true) && !is_dir($this->vhostDir)) {
            throw new \RuntimeException('Unable to create vhost directory');
        }
        $file = $this->vhostDir . '/' . $sub . '.' . $this->domain;
        if (file_put_contents($file, 'client_max_body_size ' . $this->clientMaxBodySize . ';') === false) {
            throw new \RuntimeException('Unable to write vhost file');
        }
        if ($this->reload) {
            $output = [];
            $status = 0;
            exec('docker compose exec nginx nginx -s reload 2>&1', $output, $status);
            if ($status !== 0) {
                throw new \RuntimeException('nginx reload failed: ' . implode("\n", $output));
            }
        }
    }
}
