<?php

declare(strict_types=1);

namespace App\Application\RateLimiting;

/**
 * @phpstan-type RateLimitEntry array{count:int,start:int}
 */
class FilesystemRateLimitStore implements RateLimitStore
{
    private string $directory;
    private bool $isWritable = true;

    public function __construct(?string $directory = null) {
        $directory = $directory ?? sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rate_limit';
        $this->directory = rtrim($directory, DIRECTORY_SEPARATOR);
    }

    public function increment(string $key, int $windowSeconds): int {
        $path = $this->pathFor($key);
        $now = time();
        $entry = $this->read($path, $now, $windowSeconds);
        $entry['count']++;

        $this->write($path, $entry);

        return $entry['count'];
    }

    public function reset(): void {
        $this->isWritable = true;

        if (!is_dir($this->directory)) {
            return;
        }

        $pattern = $this->directory . DIRECTORY_SEPARATOR . 'rlm_*.json';
        $files = glob($pattern) ?: [];
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    /**
     * @return RateLimitEntry
     */
    private function read(string $path, int $now, int $windowSeconds): array {
        if (is_file($path)) {
            $contents = file_get_contents($path);
            if ($contents !== false) {
                $decoded = json_decode($contents, true);
                if (is_array($decoded)) {
                    $start = (int) ($decoded['start'] ?? 0);
                    if ($start !== 0 && ($now - $start) <= $windowSeconds) {
                        return [
                            'count' => (int) ($decoded['count'] ?? 0),
                            'start' => $start,
                        ];
                    }
                }
            }
        }

        return ['count' => 0, 'start' => $now];
    }

    /**
     * @param RateLimitEntry $data
     */
    private function write(string $path, array $data): void {
        if (!$this->isWritable) {
            return;
        }

        $dir = dirname($path);
        if (is_file($dir)) {
            $this->isWritable = false;

            return;
        }

        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0777, true) && !is_dir($dir)) {
                $this->isWritable = false;

                return;
            }
        }

        $payload = json_encode($data);
        if ($payload === false) {
            return;
        }

        $result = @file_put_contents($path, $payload, LOCK_EX);
        if ($result === false) {
            $this->isWritable = false;
        }
    }

    private function pathFor(string $key): string {
        return $this->directory . DIRECTORY_SEPARATOR . 'rlm_' . $key . '.json';
    }
}
