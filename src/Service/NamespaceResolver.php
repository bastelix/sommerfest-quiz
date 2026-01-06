<?php

declare(strict_types=1);

namespace App\Service;

use App\Infrastructure\Database;
use App\Exception\NamespaceNotFoundException;
use App\Repository\NamespaceRepository;
use App\Service\DesignTokenService;
use App\Service\NamespaceService;
use App\Support\DomainNameHelper;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;
use RuntimeException;

final class NamespaceResolver
{
    private NamespaceValidator $validator;

    private ?NamespaceService $namespaceService = null;

    public function __construct(?NamespaceValidator $validator = null)
    {
        $this->validator = $validator ?? new NamespaceValidator();
    }

    public function resolve(Request $request): NamespaceContext
    {
        $candidates = $this->collectCandidates($request);

        if ($candidates === []) {
            throw new RuntimeException('Namespace could not be resolved for request.');
        }

        $selection = $this->selectNamespace($candidates);
        $namespace = $selection['namespace'];
        $usedFallback = $selection['usedFallback'];

        $host = DomainNameHelper::normalize($request->getUri()->getHost(), stripAdmin: false);

        return new NamespaceContext($namespace, $candidates, $host, $usedFallback);
    }

    /**
     * @return list<string>
     */
    private function collectCandidates(Request $request): array
    {
        $candidates = [];

        $explicit = $this->normalizeNamespace(
            $request->getAttribute('legalPageNamespace')
                ?? $request->getAttribute('pageNamespace')
                ?? $request->getAttribute('namespace')
        );
        $this->pushCandidate($candidates, $explicit);

        $domainNamespace = $this->normalizeNamespace($request->getAttribute('domainNamespace'));
        $this->pushCandidate($candidates, $domainNamespace);

        $routeNamespace = $this->resolveRouteNamespace($request);
        $this->pushCandidate($candidates, $routeNamespace);

        return $candidates;
    }

    /**
     * @return array{namespace: string, usedFallback: bool}
     */
    private function selectNamespace(array $candidates): array
    {
        foreach ($candidates as $index => $candidate) {
            $normalized = $this->normalizeNamespace($candidate);
            if ($normalized === null) {
                continue;
            }

            try {
                $this->ensureNamespaceExists($normalized);
            } catch (NamespaceNotFoundException) {
                continue;
            }

            return [
                'namespace' => $normalized,
                'usedFallback' => $index > 0,
            ];
        }

        throw new RuntimeException('No valid namespace candidate available.');
    }

    private function resolveRouteNamespace(Request $request): ?string
    {
        try {
            $route = RouteContext::fromRequest($request)->getRoute();
        } catch (RuntimeException) {
            return null;
        }
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

    private function ensureNamespaceExists(string $namespace): void
    {
        $service = $this->namespaceService;
        if ($service === null) {
            $pdo = Database::connectFromEnv();
            $repository = new NamespaceRepository($pdo);
            $service = $this->namespaceService = new NamespaceService(
                $repository,
                $this->validator,
                new DesignTokenService($pdo)
            );
        }

        if (!$service->exists($namespace)) {
            throw new NamespaceNotFoundException('namespace-missing');
        }
    }
}
