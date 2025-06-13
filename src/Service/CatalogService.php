<?php

declare(strict_types=1);

namespace App\Service;

class CatalogService
{
    private string $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/');
    }

    private function path(string $file): string
    {
        return $this->basePath . '/' . basename($file);
    }

    public function read(string $file): ?string
    {
        $path = $this->path($file);
        if (!file_exists($path)) {
            return null;
        }

        return file_get_contents($path);
    }

    /**
     * Persist a catalog JSON file.
     *
     * The target directory is created automatically if it does not exist.
     * When an array is provided it will be encoded as pretty printed JSON with
     * a trailing newline to avoid truncated files.
     *
     * @param array|string $data
     */
    public function write(string $file, $data): void
    {
        $path = $this->path($file);

        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        if (is_array($data)) {
            $data = json_encode($data, JSON_PRETTY_PRINT) . "\n";
        }

        file_put_contents($path, (string) $data, LOCK_EX);
    }

    public function delete(string $file): void
    {
        $path = $this->path($file);
        if (file_exists($path)) {
            unlink($path);
        }
    }

    public function deleteQuestion(string $file, int $index): void
    {
        $path = $this->path($file);
        if (!file_exists($path)) {
            return;
        }
        $json = file_get_contents($path);
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return;
        }
        if ($index < 0 || $index >= count($data)) {
            return;
        }
        array_splice($data, $index, 1);
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));
    }
}
