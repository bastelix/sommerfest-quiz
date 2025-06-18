<?php

declare(strict_types=1);

namespace App\Service;

use PDO;

class CatalogService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function read(string $file): ?string
    {
        if ($file === 'catalogs.json') {
            $stmt = $this->pdo->query('SELECT uid,id,file,name,description,qrcode_url,raetsel_buchstabe FROM catalogs ORDER BY id');
            return json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT);
        }

        $id = pathinfo($file, PATHINFO_FILENAME);
        $stmt = $this->pdo->prepare('SELECT type,prompt,options,answers,terms,items FROM questions WHERE catalog_id=? ORDER BY id');
        $stmt->execute([$id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            foreach (['options','answers','terms','items'] as $col) {
                if ($r[$col] !== null) {
                    $r[$col] = json_decode((string)$r[$col], true);
                } else {
                    unset($r[$col]);
                }
            }
        }
        return json_encode($rows, JSON_PRETTY_PRINT);
    }

    /**
     * Persist a catalog JSON file.
     *
     * The target directory is created automatically if it does not exist.
     * When an array is provided it will be encoded as pretty printed JSON with
     * a trailing newline to avoid truncated files.
     *
     * @param array|string $data
     */
    public function write(string $file, $data): void
    {
        if (is_string($data)) {
            $data = json_decode($data, true) ?? [];
        }

        if ($file === 'catalogs.json') {
            $this->pdo->beginTransaction();
            $this->pdo->exec('DELETE FROM catalogs');
            $stmt = $this->pdo->prepare('INSERT INTO catalogs(uid,id,file,name,description,qrcode_url,raetsel_buchstabe) VALUES(?,?,?,?,?,?,?)');
            foreach ($data as $cat) {
                $stmt->execute([
                    $cat['uid'] ?? '',
                    $cat['id'] ?? '',
                    $cat['file'] ?? '',
                    $cat['name'] ?? '',
                    $cat['description'] ?? null,
                    $cat['qrcode_url'] ?? null,
                    $cat['raetsel_buchstabe'] ?? null,
                ]);
            }
            $this->pdo->commit();
            return;
        }

        $id = pathinfo($file, PATHINFO_FILENAME);
        $this->pdo->beginTransaction();
        $del = $this->pdo->prepare('DELETE FROM questions WHERE catalog_id=?');
        $del->execute([$id]);
        $stmt = $this->pdo->prepare('INSERT INTO questions(catalog_id,type,prompt,options,answers,terms,items) VALUES(?,?,?,?,?,?,?)');
        foreach ($data as $q) {
            $stmt->execute([
                $id,
                $q['type'] ?? '',
                $q['prompt'] ?? '',
                isset($q['options']) ? json_encode($q['options']) : null,
                isset($q['answers']) ? json_encode($q['answers']) : null,
                isset($q['terms']) ? json_encode($q['terms']) : null,
                isset($q['items']) ? json_encode($q['items']) : null,
            ]);
        }
        $this->pdo->commit();
    }

    public function delete(string $file): void
    {
        if ($file === 'catalogs.json') {
            $this->pdo->exec('DELETE FROM catalogs');
            $this->pdo->exec('DELETE FROM questions');
            return;
        }
        $id = pathinfo($file, PATHINFO_FILENAME);
        $this->pdo->beginTransaction();
        $this->pdo->prepare('DELETE FROM questions WHERE catalog_id=?')->execute([$id]);
        $this->pdo->prepare('DELETE FROM catalogs WHERE id=?')->execute([$id]);
        $this->pdo->commit();
    }

    public function deleteQuestion(string $file, int $index): void
    {
        $id = pathinfo($file, PATHINFO_FILENAME);
        $stmt = $this->pdo->prepare('SELECT id FROM questions WHERE catalog_id=? ORDER BY id LIMIT 1 OFFSET ?');
        $stmt->execute([$id, $index]);
        $qid = $stmt->fetchColumn();
        if ($qid !== false) {
            $this->pdo->prepare('DELETE FROM questions WHERE id=?')->execute([$qid]);
        }
    }
}
