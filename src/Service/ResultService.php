<?php

declare(strict_types=1);

namespace App\Service;

use PDO;

class ResultService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getAll(): array
    {
        $sql = 'SELECT r.name, r.catalog, r.attempt, r.correct, r.total, r.time, r.puzzleTime, r.photo, ' .
            'c.name AS catalogName '
            . 'FROM results r '
            . 'LEFT JOIN catalogs c ON c.uid = r.catalog OR c.id = r.catalog '
            . 'ORDER BY r.id';
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function add(array $data): array
    {
        $name = (string)($data['name'] ?? '');
        $catalog = (string)($data['catalog'] ?? '');
        $stmt = $this->pdo->prepare('SELECT COALESCE(MAX(attempt),0) FROM results WHERE name=? AND catalog=?');
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
        $stmt = $this->pdo->prepare('INSERT INTO results(name,catalog,attempt,correct,total,time,puzzleTime,photo) VALUES(?,?,?,?,?,?,?,?)');
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
        $stmt = $this->pdo->prepare('SELECT id,puzzleTime FROM results WHERE name=? AND catalog=? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$name, $catalog]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && $row['puzzleTime'] === null) {
            $upd = $this->pdo->prepare('UPDATE results SET puzzleTime=? WHERE id=?');
            $upd->execute([$time, $row['id']]);
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
