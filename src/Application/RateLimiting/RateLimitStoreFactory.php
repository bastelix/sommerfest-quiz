<?php

declare(strict_types=1);

namespace App\Application\RateLimiting;

final class RateLimitStoreFactory
{
    public static function createDefault(?string $directory = null): RateLimitStore {
        if (ApcuRateLimitStore::isSupported()) {
            return new ApcuRateLimitStore();
        }

        return new FilesystemRateLimitStore($directory);
    }
}
