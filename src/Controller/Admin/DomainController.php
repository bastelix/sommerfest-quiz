<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\CertificateZoneRegistry;
use App\Service\DomainService;
use App\Support\AcmeDnsProvider;
use App\Support\DomainNameHelper;
use InvalidArgumentException;
use RuntimeException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Admin API controller for managing domain records.
 */
class DomainController
{
    private DomainService $domainService;
    private CertificateZoneRegistry $certificateZoneRegistry;

    public function __construct(
        DomainService $domainService,
        CertificateZoneRegistry $certificateZoneRegistry
    ) {
        $this->domainService = $domainService;
        $this->certificateZoneRegistry = $certificateZoneRegistry;
    }

    public function index(Request $request, Response $response): Response
    {
        $domains = $this->domainService->listDomains(includeInactive: true);

        $response->getBody()->write(json_encode([
            'domains' => $domains,
        ], JSON_PRETTY_PRINT));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function provisionSsl(Request $request, Response $response, array $args): Response
    {
        $id = isset($args['id']) ? (int) $args['id'] : 0;
        if ($id <= 0) {
            return $response->withStatus(400);
        }

        $domain = $this->domainService->getDomainById($id);
        if ($domain === null) {
            return $response->withStatus(404);
        }

        try {
            $this->certificateZoneRegistry->ensureZone($domain['zone']);
            $this->certificateZoneRegistry->markPending($domain['zone']);
        } catch (RuntimeException $exception) {
            return $this->jsonError($response, 'Failed to queue certificate provisioning.', 500);
        }

        $payload = [
            'status' => 'started',
            'namespace' => $domain['namespace'] ?? null,
            'domain' => $domain['host'],
        ];

        $response->getBody()->write(json_encode($payload));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function create(Request $request, Response $response): Response
    {
        $data = $this->parseRequest($request);
        if ($data === null) {
            return $response->withStatus(400);
        }

        $host = isset($data['host']) ? (string) $data['host'] : (string) ($data['domain'] ?? '');
        $label = array_key_exists('label', $data) ? (string) $data['label'] : null;
        $namespace = array_key_exists('namespace', $data) ? (string) $data['namespace'] : null;
        $isActive = $this->normalizeBool($data['is_active'] ?? true);

        $provider = null;
        if ($isActive) {
            try {
                $provider = AcmeDnsProvider::fromEnv();
            } catch (InvalidArgumentException $exception) {
                return $this->jsonError($response, $exception->getMessage(), 422);
            }
        }

        try {
            $domain = $this->domainService->createDomain($host, $label, $namespace, $isActive);
        } catch (InvalidArgumentException $exception) {
            return $this->jsonError($response, $exception->getMessage(), 422);
        }

        if ($domain['is_active']) {
            $this->queueZone($domain['zone'], $provider);
            $this->dispatchWildcardJobs();
            $this->clearMarketingDomainCache();
        }

        $response->getBody()->write(json_encode([
            'status' => 'ok',
            'domain' => $domain,
        ]));

        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $data = $this->parseRequest($request);
        if ($data === null) {
            return $response->withStatus(400);
        }

        $id = isset($args['id']) ? (int) $args['id'] : 0;
        if ($id <= 0) {
            return $response->withStatus(400);
        }

        $host = isset($data['host']) ? (string) $data['host'] : (string) ($data['domain'] ?? '');
        $label = array_key_exists('label', $data) ? (string) $data['label'] : null;
        $namespace = array_key_exists('namespace', $data) ? (string) $data['namespace'] : null;
        $isActive = $this->normalizeBool($data['is_active'] ?? true);

        $existing = $this->domainService->getDomainById($id);
        if ($existing === null) {
            return $response->withStatus(404);
        }

        $provider = null;
        $shouldActivate = !$existing['is_active'] && $isActive;
        if ($shouldActivate) {
            try {
                $provider = AcmeDnsProvider::fromEnv();
            } catch (InvalidArgumentException $exception) {
                return $this->jsonError($response, $exception->getMessage(), 422);
            }
        }

        try {
            $domain = $this->domainService->updateDomain($id, $host, $label, $namespace, $isActive);
        } catch (InvalidArgumentException $exception) {
            return $this->jsonError($response, $exception->getMessage(), 422);
        }

        if ($domain === null) {
            return $response->withStatus(404);
        }

        if ($shouldActivate && $domain['is_active']) {
            $this->queueZone($domain['zone'], $provider);
            $this->dispatchWildcardJobs();
            $this->clearMarketingDomainCache();
        }

        $response->getBody()->write(json_encode([
            'status' => 'ok',
            'domain' => $domain,
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    private function clearMarketingDomainCache(): void
    {
        $provider = DomainNameHelper::getMarketingDomainProvider();
        if ($provider === null) {
            return;
        }

        $provider->clearCache();
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = isset($args['id']) ? (int) $args['id'] : 0;
        if ($id <= 0) {
            return $response->withStatus(400);
        }

        $this->domainService->deleteDomain($id);

        $response->getBody()->write(json_encode([
            'status' => 'ok',
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function renewSsl(Request $request, Response $response, array $args): Response
    {
        $id = isset($args['id']) ? (int) $args['id'] : 0;
        if ($id <= 0) {
            return $response->withStatus(400);
        }

        $domain = $this->domainService->getDomainById($id);
        if ($domain === null) {
            return $response->withStatus(404);
        }

        try {
            $this->certificateZoneRegistry->markPending($domain['zone']);
        } catch (RuntimeException $exception) {
            return $this->jsonError($response, 'Failed to queue certificate renewal.', 500);
        }

        $response->getBody()->write(json_encode([
            'status' => 'Certificate renewal queued.',
            'domain' => $domain['zone'],
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * @return array<string,mixed>|null
     */
    private function parseRequest(Request $request): ?array
    {
        $parsed = $request->getParsedBody();
        if (is_array($parsed)) {
            return $parsed;
        }

        if (is_object($parsed)) {
            $data = json_decode((string) json_encode($parsed), true);
            if (is_array($data)) {
                return $data;
            }
        }

        $contentType = strtolower(trim($request->getHeaderLine('Content-Type')));
        if ($contentType !== '' && str_starts_with($contentType, 'application/json')) {
            $data = json_decode((string) $request->getBody(), true);
            if (is_array($data)) {
                return $data;
            }
        }

        return null;
    }

    private function jsonError(Response $response, string $message, int $status): Response
    {
        $payload = [
            'error' => $message,
        ];

        $response->getBody()->write(json_encode($payload));

        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }

    private function normalizeBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $value = is_string($value) ? strtolower(trim($value)) : $value;

        return $value === true || $value === 1 || $value === '1' || $value === 'true' || $value === 'on';
    }

    private function queueZone(string $zone, ?string $provider = null): void
    {
        $resolvedProvider = $provider ?? AcmeDnsProvider::fromEnv();

        $this->certificateZoneRegistry->ensureZone($zone, $resolvedProvider, true);
    }

    private function dispatchWildcardJobs(): void
    {
        $autoDispatch = getenv('ENABLE_WILDCARD_AUTOMATION');
        if ($autoDispatch !== false && strtolower((string) $autoDispatch) === '0') {
            return;
        }

        foreach (['ACME_SH_BIN', 'ACME_WILDCARD_PROVIDER', 'NGINX_WILDCARD_CERT_DIR'] as $variable) {
            if (getenv($variable) === false || getenv($variable) === '') {
                return;
            }
        }

        $script = realpath(__DIR__ . '/../../scripts/wildcard_maintenance.sh');
        if ($script === false || !is_file($script)) {
            return;
        }

        $command = 'bash ' . escapeshellarg($script) . ' >/dev/null 2>&1 &';
        $process = @proc_open(
            ['/bin/sh', '-c', $command],
            [
                0 => ['file', '/dev/null', 'r'],
                1 => ['file', '/dev/null', 'w'],
                2 => ['file', '/dev/null', 'w'],
            ],
            $pipes
        );

        if (is_resource($process)) {
            proc_close($process);
        }
    }
}
