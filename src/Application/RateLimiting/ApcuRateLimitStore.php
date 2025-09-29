<?php

declare(strict_types=1);

namespace App\Application\RateLimiting;

use APCUIterator;
use function apcu_delete;
use function apcu_enabled;
use function apcu_fetch;
use function apcu_store;
use function preg_quote;

class ApcuRateLimitStore implements RateLimitStore
{
    private const PREFIX = 'rlm:';

    /** @var array<string, true> */
    private array $keys = [];

    public static function isSupported(): bool
    {
        return function_exists('apcu_fetch')
            && function_exists('apcu_store')
            && function_exists('apcu_delete')
            && (!function_exists('apcu_enabled') || apcu_enabled());
    }

    public function increment(string $key, int $windowSeconds): int
    {
        $namespacedKey = self::PREFIX . $key;
        $now = time();

        $entry = apcu_fetch($namespacedKey, $success);
        if (!$success || !is_array($entry) || $this->isExpired($entry, $now, $windowSeconds)) {
            $entry = ['count' => 0, 'start' => $now];
        }

        $count = (int) ($entry['count'] ?? 0) + 1;
        $start = (int) ($entry['start'] ?? $now);

        apcu_store($namespacedKey, ['count' => $count, 'start' => $start], $windowSeconds);
        $this->keys[$namespacedKey] = true;

        return $count;
    }

    public function reset(): void
    {
        if (!self::isSupported()) {
            return;
        }

        if (class_exists(APCUIterator::class)) {
            /** @var iterable<array{key:string}> $iterator */
            $iterator = new APCUIterator('/^' . preg_quote(self::PREFIX, '/') . '/');
            foreach ($iterator as $item) {
                apcu_delete($item['key']);
            }
        } else {
            foreach (array_keys($this->keys) as $key) {
                apcu_delete($key);
            }
        }

        $this->keys = [];
    }

    /**
     * @param array<string, int> $entry
     */
    private function isExpired(array $entry, int $now, int $windowSeconds): bool
    {
        $start = (int) ($entry['start'] ?? 0);

        return $start === 0 || ($now - $start) > $windowSeconds;
    }
}
