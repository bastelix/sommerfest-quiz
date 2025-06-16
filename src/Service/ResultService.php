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
            // optional timestamp when the puzzle word was solved
            'puzzleTime' => isset($data['puzzleTime']) ? (int)$data['puzzleTime'] : null,
            'photo' => isset($data['photo']) ? (string)$data['photo'] : null,
        ];
        $results[] = $entry;
        file_put_contents($this->path, json_encode($results, JSON_PRETTY_PRINT) . "\n");
        return $entry;
    }

    public function clear(): void
    {
        file_put_contents($this->path, "[]\n");
    }

    public function markPuzzle(string $name, string $catalog, int $time): void
    {
        $results = $this->getAll();
        for ($i = count($results) - 1; $i >= 0; $i--) {
            if (($results[$i]['name'] ?? '') === $name && ($results[$i]['catalog'] ?? '') === $catalog) {
                if (!isset($results[$i]['puzzleTime'])) {
                    $results[$i]['puzzleTime'] = $time;
                    file_put_contents($this->path, json_encode($results, JSON_PRETTY_PRINT) . "\n");
                }
                break;
            }
        }
    }

    public function setPhoto(string $name, string $catalog, string $path): void
    {
        $results = $this->getAll();
        for ($i = count($results) - 1; $i >= 0; $i--) {
            if (($results[$i]['name'] ?? '') === $name && ($results[$i]['catalog'] ?? '') === $catalog) {
                $results[$i]['photo'] = $path;
                file_put_contents($this->path, json_encode($results, JSON_PRETTY_PRINT) . "\n");
                break;
            }
        }
    }
}
