<?php

declare(strict_types=1);

namespace App\Service;

use App\Support\DomainNameHelper;
use Psr\Http\Message\ServerRequestInterface as Request;

class LegalPageResolver
{
    private const NAMESPACE_PATTERN = '/^[a-z0-9][a-z0-9\-]{0,99}$/';

    private PageService $pages;

    public function __construct(?PageService $pages = null)
    {
        $this->pages = $pages ?? new PageService();
    }

    public function resolve(Request $request, string $slug): ?string
    {
        $normalizedSlug = trim($slug);
        if ($normalizedSlug === '') {
            return null;
        }

        $candidates = $this->collectNamespaces($request);
        foreach ($candidates as $namespace) {
            $content = $this->pages->getByKey($namespace, $normalizedSlug);
            if ($content !== null) {
                return $content;
            }
        }

        error_log(sprintf(
            'Legal page not found for slug "%s" (namespaces: %s)',
            $normalizedSlug,
            implode(', ', $candidates)
        ));

        return null;
    }

    /**
     * @return string[]
     */
    private function collectNamespaces(Request $request): array
    {
        $candidates = [];

        $explicit = $this->normalizeNamespace(
            $request->getAttribute('legalPageNamespace')
                ?? $request->getAttribute('pageNamespace')
                ?? $request->getAttribute('namespace')
        );
        if ($explicit !== null) {
            $candidates[] = $explicit;
        }

        $eventNamespace = $this->resolveEventNamespace($request);
        if ($eventNamespace !== null) {
            $candidates[] = $eventNamespace;
        }

        $tenantNamespace = $this->resolveTenantNamespace($request);
        if ($tenantNamespace !== null) {
            $candidates[] = $tenantNamespace;
        }

        $candidates[] = PageService::DEFAULT_NAMESPACE;

        $unique = [];
        $result = [];
        foreach ($candidates as $candidate) {
            if (isset($unique[$candidate])) {
                continue;
            }
            $unique[$candidate] = true;
            $result[] = $candidate;
        }

        return $result;
    }

    private function resolveEventNamespace(Request $request): ?string
    {
        $candidate = $request->getAttribute('event_uid')
            ?? $request->getAttribute('event')
            ?? ($request->getQueryParams()['event_uid'] ?? null)
            ?? ($request->getQueryParams()['event'] ?? null);

        return $this->normalizeNamespace($candidate);
    }

    private function resolveTenantNamespace(Request $request): ?string
    {
        $candidate = $request->getAttribute('tenant')
            ?? $request->getAttribute('tenantNamespace');
        $normalized = $this->normalizeNamespace($candidate);
        if ($normalized !== null) {
            return $normalized;
        }

        $domainType = (string) $request->getAttribute('domainType');
        if ($domainType !== 'tenant') {
            return null;
        }

        $host = DomainNameHelper::normalize($request->getUri()->getHost());
        if ($host === '') {
            return null;
        }

        $parts = explode('.', $host);
        $subdomain = $parts[0] ?? '';

        return $this->normalizeNamespace($subdomain);
    }

    private function normalizeNamespace(mixed $candidate): ?string
    {
        if (!is_string($candidate)) {
            return null;
        }

        $normalized = strtolower(trim($candidate));
        if ($normalized === '') {
            return null;
        }

        if (!preg_match(self::NAMESPACE_PATTERN, $normalized)) {
            return null;
        }

        return $normalized;
    }
}
