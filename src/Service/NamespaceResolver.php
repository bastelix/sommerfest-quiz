<?php

declare(strict_types=1);

namespace App\Service;

use App\Support\DomainNameHelper;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;

final class NamespaceResolver
{
    private NamespaceValidator $validator;

    public function __construct(?NamespaceValidator $validator = null)
    {
        $this->validator = $validator ?? new NamespaceValidator();
    }

    public function resolve(Request $request): NamespaceContext
    {
        $candidates = $this->collectCandidates($request);

        if ($candidates === []) {
            $candidates[] = PageService::DEFAULT_NAMESPACE;
        }

        $namespace = $candidates[0];

        return new NamespaceContext($namespace, $candidates);
    }

    /**
     * @return list<string>
     */
    private function collectCandidates(Request $request): array
    {
        $candidates = [];

        $path = $request->getUri()->getPath();
        $basePath = RouteContext::fromRequest($request)->getBasePath();
        $adminPrefix = rtrim($basePath, '/') . '/admin';
        if (str_starts_with($path, $adminPrefix)) {
            $queryNamespace = $this->normalizeNamespace($request->getQueryParams()['namespace'] ?? null);
            $this->pushCandidate($candidates, $queryNamespace);
        }

        $explicit = $this->normalizeNamespace(
            $request->getAttribute('legalPageNamespace')
                ?? $request->getAttribute('pageNamespace')
                ?? $request->getAttribute('namespace')
        );
        $this->pushCandidate($candidates, $explicit);

        $routeNamespace = $this->resolveRouteNamespace($request);
        $this->pushCandidate($candidates, $routeNamespace);

        $eventNamespace = $this->resolveEventNamespace($request);
        $this->pushCandidate($candidates, $eventNamespace);

        $tenantNamespace = $this->resolveTenantNamespace($request);
        $this->pushCandidate($candidates, $tenantNamespace);

        $this->pushCandidate($candidates, PageService::DEFAULT_NAMESPACE);

        return $candidates;
    }

    private function resolveRouteNamespace(Request $request): ?string
    {
        $route = RouteContext::fromRequest($request)->getRoute();
        if ($route === null) {
            return null;
        }

        $arguments = $route->getArguments();

        $candidate = $arguments['namespace']
            ?? $arguments['tenantNamespace']
            ?? $arguments['tenant']
            ?? $arguments['subdomain']
            ?? null;

        return $this->normalizeNamespace($candidate);
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
        $subdomain = $parts[0];

        return $this->normalizeNamespace($subdomain);
    }

    private function normalizeNamespace(mixed $candidate): ?string
    {
        return $this->validator->normalizeCandidate($candidate);
    }

    /**
     * @param list<string> $candidates
     */
    private function pushCandidate(array &$candidates, ?string $candidate): void
    {
        if ($candidate === null) {
            return;
        }

        if (in_array($candidate, $candidates, true)) {
            return;
        }

        $candidates[] = $candidate;
    }
}
