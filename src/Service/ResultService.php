<?php

declare(strict_types=1);

namespace App\Service;

class ResultService
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
     * @param array<string, mixed> $data
     */
    public function add(array $data): array
    {
        $results = $this->getAll();
        $name = (string)($data['name'] ?? '');
        $catalog = (string)($data['catalog'] ?? '');
        $attempt = 1;
        foreach ($results as $r) {
            if (($r['name'] ?? '') === $name && ($r['catalog'] ?? '') === $catalog) {
                $attempt = max($attempt, (int)($r['attempt'] ?? 0) + 1);
            }
        }
        $entry = [
            'name' => $name,
            'catalog' => $catalog,
            'attempt' => $attempt,
            'correct' => (int)($data['correct'] ?? 0),
            'total' => (int)($data['total'] ?? 0),
            'time' => time(),
        ];
        $results[] = $entry;
        file_put_contents($this->path, json_encode($results, JSON_PRETTY_PRINT) . "\n");
        return $entry;
    }

    public function clear(): void
    {
        file_put_contents($this->path, "[]\n");
    }
}
