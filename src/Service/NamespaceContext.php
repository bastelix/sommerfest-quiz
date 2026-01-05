<?php

declare(strict_types=1);

namespace App\Service;

final class NamespaceContext
{
    private string $namespace;

    /** @var list<string> */
    private array $candidates;

    private string $host;

    private bool $usedFallback;

    /**
     * @param list<string> $candidates
     */
    public function __construct(string $namespace, array $candidates, string $host = '', bool $usedFallback = false)
    {
        $this->namespace = $namespace;
        $this->candidates = $candidates;
        $this->host = $host;
        $this->usedFallback = $usedFallback;
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    /**
     * @return list<string>
     */
    public function getCandidates(): array
    {
        return $this->candidates;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function usedFallback(): bool
    {
        return $this->usedFallback;
    }
}
