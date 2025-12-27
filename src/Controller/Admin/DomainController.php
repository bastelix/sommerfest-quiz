<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\CertificateProvisionerInterface;
use App\Service\DomainService;
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
    private ?CertificateProvisionerInterface $certificateProvisioningService;

    public function __construct(
        DomainService $domainService,
        ?CertificateProvisionerInterface $certificateProvisioningService = null
    ) {
        $this->domainService = $domainService;
        $this->certificateProvisioningService = $certificateProvisioningService;
    }

    public function index(Request $request, Response $response): Response
    {
        $domains = $this->domainService->listDomains(includeInactive: true);

        $response->getBody()->write(json_encode([
            'domains' => $domains,
        ], JSON_PRETTY_PRINT));

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

        try {
            $domain = $this->domainService->createDomain($host, $label, $namespace, $isActive);
        } catch (InvalidArgumentException $exception) {
            return $this->jsonError($response, $exception->getMessage(), 422);
        }

        if (
            $this->certificateProvisioningService !== null
            && $domain['is_active']
        ) {
            $this->certificateProvisioningService->provisionAllDomains();
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

        try {
            $domain = $this->domainService->updateDomain($id, $host, $label, $namespace, $isActive);
        } catch (InvalidArgumentException $exception) {
            return $this->jsonError($response, $exception->getMessage(), 422);
        }

        if ($domain === null) {
            return $response->withStatus(404);
        }

        if (
            $this->certificateProvisioningService !== null
            && !$existing['is_active']
            && $domain['is_active']
        ) {
            $this->certificateProvisioningService->provisionAllDomains();
        }

        $response->getBody()->write(json_encode([
            'status' => 'ok',
            'domain' => $domain,
        ]));

        return $response->withHeader('Content-Type', 'application/json');
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

        if ($this->certificateProvisioningService === null) {
            return $this->jsonError($response, 'Certificate provisioning unavailable.', 503);
        }

        $host = $this->domainService->normalizeDomain($domain['host'], stripAdmin: false);
        if ($host === '' && isset($domain['normalized_host'])) {
            $host = (string) $domain['normalized_host'];
        }

        if ($host === '') {
            return $this->jsonError($response, 'Invalid domain supplied.', 422);
        }

        try {
            $this->certificateProvisioningService->provisionMarketingDomain($host);
        } catch (InvalidArgumentException | RuntimeException $exception) {
            return $this->jsonError($response, $exception->getMessage(), 422);
        }

        $payload = [
            'status' => 'Certificate renewal queued.',
            'domain' => $host,
        ];

        $response->getBody()->write(json_encode($payload));

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
}
