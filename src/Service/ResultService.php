<?php

declare(strict_types=1);

namespace App\Service;

use App\Infrastructure\Database;
use PDO;

class ResultService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connect();
    }

    public function getAll(): array
    {
        $stmt = $this->pdo->query('SELECT name,catalog,attempt,correct,total,time,puzzleTime,photo FROM results ORDER BY id');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function add(array $data): array
    {
        $name = (string)($data['name'] ?? '');
        $catalog = (string)($data['catalog'] ?? '');
        $stmt = $this->pdo->prepare('SELECT MAX(attempt) FROM results WHERE name=? AND catalog=?');
        $stmt->execute([$name, $catalog]);
        $attempt = (int)$stmt->fetchColumn() + 1;
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
        $sql = 'INSERT INTO results(name,catalog,attempt,correct,total,time,puzzleTime,photo) VALUES(?,?,?,?,?,?,?,?)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $entry['name'],
            $entry['catalog'],
            $entry['attempt'],
            $entry['correct'],
            $entry['total'],
            $entry['time'],
            $entry['puzzleTime'],
            $entry['photo'],
        ]);
        return $entry;
    }

    public function clear(): void
    {
        $this->pdo->exec('DELETE FROM results');
    }

    public function markPuzzle(string $name, string $catalog, int $time): void
    {
        $stmt = $this->pdo->prepare('SELECT id FROM results WHERE name=? AND catalog=? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$name, $catalog]);
        $id = $stmt->fetchColumn();
        if ($id !== false) {
            $upd = $this->pdo->prepare('UPDATE results SET puzzleTime=? WHERE id=? AND puzzleTime IS NULL');
            $upd->execute([$time, $id]);
        }
    }

    public function setPhoto(string $name, string $catalog, string $path): void
    {
        $stmt = $this->pdo->prepare('SELECT id FROM results WHERE name=? AND catalog=? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$name, $catalog]);
        $id = $stmt->fetchColumn();
        if ($id !== false) {
            $upd = $this->pdo->prepare('UPDATE results SET photo=? WHERE id=?');
            $upd->execute([$path, $id]);
        }
    }
}
