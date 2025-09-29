<?php

declare(strict_types=1);

namespace Tests;

use Slim\Psr7\Uri as SlimUri;

class Uri extends SlimUri
{
    private string $basePath = '';

    public function getBasePath(): string {
        return $this->basePath;
    }

    public function withBasePath(string $basePath): self {
        $clone = clone $this;
        $clone->basePath = $basePath;
        return $clone;
    }
}
