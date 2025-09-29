<?php

declare(strict_types=1);

namespace App\Application\RateLimiting;

interface RateLimitStoreInterface
{
    public function increment(string $key, int $windowSeconds): int;

    public function reset(): void;
}
