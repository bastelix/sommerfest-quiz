<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;

final class NamespaceInUseException extends RuntimeException
{
    /**
     * @param list<string> $sources
     */
    public function __construct(private array $sources)
    {
        parent::__construct('namespace-in-use');
    }

    /**
     * @return list<string>
     */
    public function getSources(): array
    {
        return $this->sources;
    }
}
