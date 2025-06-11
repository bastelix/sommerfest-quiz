<?php

declare(strict_types=1);

namespace App\Service;

class ConfigService
{
    private string $path;

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    public function getJs(): ?string
    {
        if (!file_exists($this->path)) {
            return null;
        }

        return file_get_contents($this->path);
    }

    public function getConfig(): array
    {
        $content = $this->getJs();
        if ($content === null) {
            return [];
        }

        $prefix = 'window.quizConfig = ';
        if (str_starts_with($content, $prefix)) {
            $json = trim(substr($content, strlen($prefix)));
        } else {
            $json = trim($content);
        }

        $json = rtrim($json, ";\n");

        return json_decode($json, true) ?? [];
    }

    public function saveConfig(array $data): void
    {
        $content = 'window.quizConfig = ' . json_encode($data, JSON_PRETTY_PRINT) . "\n";
        file_put_contents($this->path, $content);
    }
}
