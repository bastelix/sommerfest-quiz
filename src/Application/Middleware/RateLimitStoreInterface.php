<?php

declare(strict_types=1);

namespace App\Application\Middleware;

interface RateLimitStoreInterface
{
    /**
     * Increment the counter for the given key and return the current value.
     */
    public function increment(string $key, int $ttlSeconds): int;
}
