<?php

declare(strict_types=1);

namespace App\Service;

use App\Infrastructure\Database;
use App\Exception\NamespaceNotFoundException;
use App\Repository\NamespaceRepository;
use App\Service\DesignTokenService;
use App\Service\NamespaceService;
use App\Service\DomainService;
use App\Support\DomainNameHelper;
use Psr\Http\Message\ServerRequestInterface as Request;
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
        $candidates = [];

        $explicit = $this->normalizeNamespace(
            $request->getAttribute('legalPageNamespace')
                ?? $request->getAttribute('pageNamespace')
                ?? $request->getAttribute('namespace')
        );

        $this->pushCandidate($candidates, $explicit);

        $domainNamespace = $this->normalizeNamespace($request->getAttribute('domainNamespace'))
            ?? $this->resolveDomainNamespace($request);
        $this->pushCandidate($candidates, $domainNamespace);

        if ($candidates === []) {
            throw new RuntimeException('Namespace could not be resolved for request.');
        }

        $namespace = $candidates[0];
        $this->ensureNamespaceExists($namespace);

        $host = DomainNameHelper::normalize($request->getUri()->getHost(), stripAdmin: false);

        return new NamespaceContext($namespace, $candidates, $host, false);
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

    private function resolveDomainNamespace(Request $request): ?string
    {
        $pdo = Database::connectFromEnv();
        $service = new DomainService($pdo, $this->validator);
        $domain = $service->getDomainForHost($request->getUri()->getHost(), includeInactive: true);

        if ($domain === null || !isset($domain['namespace'])) {
            return null;
        }

        return $this->normalizeNamespace($domain['namespace']);
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
