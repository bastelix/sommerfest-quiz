<?php

declare(strict_types=1);

namespace App\Service;

final class NamespaceContext
{
    private string $namespace;

    /** @var list<string> */
    private array $candidates;

    /**
     * @param list<string> $candidates
     */
    public function __construct(string $namespace, array $candidates)
    {
        $this->namespace = $namespace;
        $this->candidates = $candidates;
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
}
