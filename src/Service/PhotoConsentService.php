<?php

declare(strict_types=1);

namespace App\Service;

class PhotoConsentService
{
    private string $path;

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    public function add(string $team, int $time): void
    {
        $entries = [];
        if (file_exists($this->path)) {
            $entries = json_decode(file_get_contents($this->path), true) ?? [];
        }
        $entries[] = ['team' => $team, 'time' => $time];
        file_put_contents($this->path, json_encode($entries, JSON_PRETTY_PRINT) . "\n");
    }
}
