<?php

declare(strict_types=1);

namespace App\Application\RateLimiting;

class FilesystemRateLimitStore implements RateLimitStore
{
    private string $directory;

    public function __construct(?string $directory = null)
    {
        $directory = $directory ?? sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rate_limit';
        $this->directory = rtrim($directory, DIRECTORY_SEPARATOR);
    }

    public function increment(string $key, int $windowSeconds): int
    {
        $path = $this->pathFor($key);
        $now = time();
        $entry = $this->read($path, $now, $windowSeconds);

        $count = $entry['count'] + 1;
        $start = $entry['start'];

        $this->write($path, ['count' => $count, 'start' => $start]);

        return $count;
    }

    public function reset(): void
    {
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
     * @return array{count:int,start:int}
     */
    private function read(string $path, int $now, int $windowSeconds): array
    {
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
     * @param array{count:int,start:int} $data
     */
    private function write(string $path, array $data): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        file_put_contents($path, json_encode($data), LOCK_EX);
    }

    private function pathFor(string $key): string
    {
        return $this->directory . DIRECTORY_SEPARATOR . 'rlm_' . $key . '.json';
    }
}
