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
     * @param array|string $data
     */
    public function write(string $file, $data): void
    {
        $path = $this->path($file);
        if (is_array($data)) {
            $data = json_encode($data, JSON_PRETTY_PRINT);
        }

        file_put_contents($path, (string) $data);
    }

    public function deleteQuestion(string $file, int $index): bool
    {
        $path = $this->path($file);
        if (!file_exists($path)) {
            return false;
        }

        $json = file_get_contents($path);
        $data = json_decode($json, true);
        if (!is_array($data) || $index < 0 || $index >= count($data)) {
            return false;
        }

        array_splice($data, $index, 1);
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));

        return true;
    }
}
