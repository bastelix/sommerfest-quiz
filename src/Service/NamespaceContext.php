<?php

declare(strict_types=1);

namespace App\Service;

final class NamespaceContext
{
    private string $namespace;

    /** @var list<string> */
    private array $candidates;

    private string $host;

    /**
     * @param list<string> $candidates
     */
    public function __construct(string $namespace, array $candidates, string $host = '')
    {
        $this->namespace = $namespace;
        $this->candidates = $candidates;
        $this->host = $host;
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
}
