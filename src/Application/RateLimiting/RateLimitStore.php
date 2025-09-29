<?php

declare(strict_types=1);

namespace App\Application\RateLimiting;

interface RateLimitStore
{
    /**
     * Increment the counter for the given key and return the current total within the window.
     */
    public function increment(string $key, int $windowSeconds): int;

    /**
     * Remove all persisted counters for this store.
     */
    public function reset(): void;
}
