<?php

declare(strict_types=1);

namespace App\Service;

use PDO;

/**
 * Service for persisting and retrieving quiz results.
 */
class ResultService
{
    private PDO $pdo;

    /**
     * Inject database connection.
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Fetch all stored results along with catalog names.
     */
    public function getAll(): array
    {
        $sql = 'SELECT r.name, r.catalog, r.attempt, r.correct, r.total, r.time, r.puzzleTime, r.photo, ' .
            'c.name AS catalogName '
            . 'FROM results r '
            . 'LEFT JOIN catalogs c ON c.uid = r.catalog '
            . 'OR CAST(c.sort_order AS TEXT) = r.catalog '
            . 'ORDER BY r.id';
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retrieve per-question results including prompts.
     */
    public function getQuestionResults(): array
    {
        $sql = 'SELECT qr.name, qr.catalog, qr.question_id, qr.attempt, qr.correct,' .
            ' q.prompt, c.name AS catalogName '
            . 'FROM question_results qr '
            . 'LEFT JOIN questions q ON q.id = qr.question_id '
            . 'LEFT JOIN catalogs c ON c.uid = q.catalog_uid '
            . 'OR CAST(c.sort_order AS TEXT) = qr.catalog '
            . 'ORDER BY qr.id';
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
        $wrong = array_map('intval', $data['wrong'] ?? []);
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
        $this->addQuestionResults($name, $catalog, $attempt, $wrong, $entry['total']);
        return $entry;
    }

    /**
     * Store individual question results.
     *
     * @param list<int> $wrongIdx
     */
    private function addQuestionResults(string $name, string $catalog, int $attempt, array $wrongIdx, int $total): void
    {
        $uidStmt = $this->pdo->prepare('SELECT uid FROM catalogs WHERE uid=? OR CAST(sort_order AS TEXT)=? OR slug=?');
        $uidStmt->execute([$catalog, $catalog, $catalog]);
        $uid = $uidStmt->fetchColumn();
        if ($uid === false) {
            return;
        }
        $qStmt = $this->pdo->prepare('SELECT id FROM questions WHERE catalog_uid=? ORDER BY sort_order');
        $qStmt->execute([$uid]);
        $ids = $qStmt->fetchAll(PDO::FETCH_COLUMN);
        if (!$ids) {
            return;
        }
        $ins = $this->pdo->prepare('INSERT INTO question_results(name,catalog,question_id,attempt,correct) VALUES(?,?,?,?,?)');
        for ($i = 0; $i < min(count($ids), $total); $i++) {
            $qid = (int)$ids[$i];
            $correct = in_array($i + 1, $wrongIdx, true) ? 0 : 1;
            $ins->execute([$name, $catalog, $qid, $attempt, $correct]);
        }
    }

    /**
     * Remove all result entries.
     */
    public function clear(): void
    {
        $this->pdo->exec('DELETE FROM results');
    }

    /**
     * Mark the puzzle word as solved for the latest entry of the given user.
     */
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

    /**
     * Associate a photo path with the latest result entry for the user.
     */
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

    /**
     * Replace all results with the provided list.
     *
     * @param list<array<string, mixed>> $results
     */
    public function saveAll(array $results): void
    {
        $this->pdo->beginTransaction();
        $this->pdo->exec('DELETE FROM results');
        $stmt = $this->pdo->prepare(
            'INSERT INTO results(name,catalog,attempt,correct,total,time,puzzleTime,photo) '
            . 'VALUES(?,?,?,?,?,?,?,?)'
        );
        foreach ($results as $row) {
            $stmt->execute([
                (string)($row['name'] ?? ''),
                (string)($row['catalog'] ?? ''),
                (int)($row['attempt'] ?? 1),
                (int)($row['correct'] ?? 0),
                (int)($row['total'] ?? 0),
                (int)($row['time'] ?? time()),
                isset($row['puzzleTime']) ? (int)$row['puzzleTime'] : null,
                isset($row['photo']) ? (string)$row['photo'] : null,
            ]);
        }
        $this->pdo->commit();
    }
}
