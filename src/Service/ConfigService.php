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

    public function getJson(): ?string
    {
        if (!file_exists($this->path)) {
            return null;
        }

        return file_get_contents($this->path);
    }

    public function getConfig(): array
    {
        $content = $this->getJson();
        if ($content === null) {
            return [];
        }

        return json_decode($content, true) ?? [];
    }

    public function saveConfig(array $data): void
    {
        $json = json_encode($data, JSON_PRETTY_PRINT) . "\n";
        file_put_contents($this->path, $json);
    }
}
