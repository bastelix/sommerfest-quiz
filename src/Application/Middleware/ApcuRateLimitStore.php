<?php

declare(strict_types=1);

namespace App\Application\Middleware;

final class ApcuRateLimitStore implements RateLimitStoreInterface
{
    private function __construct()
    {
    }

    public static function createIfAvailable(): ?self
    {
        if (!self::isAvailable()) {
            return null;
        }

        return new self();
    }

    public static function isAvailable(): bool
    {
        return function_exists('apcu_fetch')
            && function_exists('apcu_store')
            && function_exists('apcu_exists')
            && (!function_exists('apcu_enabled') || apcu_enabled());
    }

    public function increment(string $key, int $ttlSeconds): int
    {
        $now = time();
        $entry = ['count' => 0, 'expires' => $now + $ttlSeconds];
        if (apcu_exists($key)) {
            $stored = apcu_fetch($key);
            if (is_array($stored) && isset($stored['count'], $stored['expires'])) {
                if ((int) $stored['expires'] >= $now) {
                    $entry = [
                        'count' => (int) $stored['count'],
                        'expires' => (int) $stored['expires'],
                    ];
                }
            }
        }

        if ($entry['expires'] < $now) {
            $entry['count'] = 0;
            $entry['expires'] = $now + $ttlSeconds;
        }

        $entry['count']++;
        apcu_store($key, $entry, $ttlSeconds);

        return $entry['count'];
    }
}
