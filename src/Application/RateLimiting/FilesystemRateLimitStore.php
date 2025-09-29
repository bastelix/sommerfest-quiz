<?php

declare(strict_types=1);

namespace App\Application\RateLimiting;

class FilesystemRateLimitStore implements RateLimitStoreInterface
{
    private string $prefix;
    private string $directory;

    public function __construct(string $prefix = 'rlm_', ?string $directory = null)
    {
        $this->prefix = $prefix;
        $this->directory = $directory ?? (sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rate_limit');
    }

    public function increment(string $key, int $windowSeconds): int
    {
        $now = time();
        $path = $this->buildPath($key);
        $entry = $this->readEntry($path, $now, $windowSeconds);
        $entry['count'] = (int) ($entry['count'] ?? 0) + 1;
        $entry['start'] = (int) ($entry['start'] ?? $now);
        $this->writeEntry($path, $entry);

        return (int) $entry['count'];
    }

    public function reset(): void
    {
        $pattern = $this->directory . DIRECTORY_SEPARATOR . $this->prefix . '*.json';
        $files = glob($pattern);
        if (!is_array($files)) {
            return;
        }

        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    /**
     * @param array<string, int> $entry
     */
    private function writeEntry(string $path, array $entry): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        file_put_contents($path, json_encode($entry), LOCK_EX);
    }

    /**
     * @return array{count:int,start:int}
     */
    private function readEntry(string $path, int $now, int $windowSeconds): array
    {
        if (is_file($path)) {
            $contents = file_get_contents($path);
            if ($contents !== false) {
                $data = json_decode($contents, true);
                if (is_array($data) && !$this->isExpired($data, $now, $windowSeconds)) {
                    return [
                        'count' => (int) ($data['count'] ?? 0),
                        'start' => (int) ($data['start'] ?? $now),
                    ];
                }
            }
        }

        return ['count' => 0, 'start' => $now];
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

    private function buildPath(string $key): string
    {
        return rtrim($this->directory, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . $this->prefix
            . $key
            . '.json';
    }
}
