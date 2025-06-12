<?php

declare(strict_types=1);

namespace App\Service;

class TeamService
{
    private string $path;

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    public function getAll(): array
    {
        if (!file_exists($this->path)) {
            return [];
        }
        $json = file_get_contents($this->path);
        return json_decode($json, true) ?? [];
    }

    /**
     * @param array<int, string> $teams
     */
    public function saveAll(array $teams): void
    {
        $data = array_values(array_map('strval', $teams));
        file_put_contents($this->path, json_encode($data, JSON_PRETTY_PRINT) . "\n");
    }
}
