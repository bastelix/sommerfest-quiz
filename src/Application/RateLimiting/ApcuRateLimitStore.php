<?php

declare(strict_types=1);

namespace App\Application\RateLimiting;

use RuntimeException;

class ApcuRateLimitStore implements RateLimitStoreInterface
{
    private string $prefix;

    /** @var array<string, true> */
    private array $keys = [];

    public function __construct(string $prefix = 'rlm:')
    {
        if (!self::isSupported()) {
            throw new RuntimeException('APCu is not available.');
        }

        $this->prefix = $prefix;
    }

    public function increment(string $key, int $windowSeconds): int
    {
        $now = time();
        $storageKey = $this->prefix . $key;
        $entry = apcu_fetch($storageKey, $success);
        if (!$success || !is_array($entry) || $this->isExpired($entry, $now, $windowSeconds)) {
            $entry = ['count' => 0, 'start' => $now];
        }

        $entry['count'] = (int) ($entry['count'] ?? 0) + 1;
        $entry['start'] = (int) ($entry['start'] ?? $now);
        apcu_store($storageKey, $entry, $windowSeconds);
        $this->keys[$storageKey] = true;

        return (int) $entry['count'];
    }

    public function reset(): void
    {
        if (!self::isSupported()) {
            return;
        }

        if (class_exists('\\APCUIterator')) {
            /** @var iterable<array{key:string}> $iterator */
            $iterator = new \APCUIterator('/^' . preg_quote($this->prefix, '/') . '/');
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

    public static function isSupported(): bool
    {
        return function_exists('apcu_fetch')
            && function_exists('apcu_store')
            && function_exists('apcu_delete')
            && (!function_exists('apcu_enabled') || apcu_enabled());
    }

    /**
     * @param array<string, int> $entry
     */
    private function isExpired(array $entry, int $now, int $windowSeconds): bool
    {
        if (!isset($entry['start'])) {
            return true;
        }

        return ($now - (int) $entry['start']) > $windowSeconds;
    }
}
