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
            $stmt = $this->pdo->query('SELECT uid,id,file,name,description,qrcode_url,raetsel_buchstabe,comment FROM catalogs ORDER BY id');
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return json_encode($data, JSON_PRETTY_PRINT);
        }

        $stmt = $this->pdo->prepare('SELECT id FROM catalogs WHERE file=?');
        $stmt->execute([basename($file)]);
        $catId = $stmt->fetchColumn();
        if ($catId === false) {
            return null;
        }
        $qStmt = $this->pdo->prepare('SELECT type,prompt,options,answers,terms,items FROM questions WHERE catalog_id=? ORDER BY id');
        $qStmt->execute([$catId]);
        $questions = [];
        while ($row = $qStmt->fetch(PDO::FETCH_ASSOC)) {
            foreach (["options","answers","terms","items"] as $k) {
                if ($row[$k] !== null) {
                    $row[$k] = json_decode((string)$row[$k], true);
                } else {
                    unset($row[$k]);
                }
            }
            $questions[] = $row;
        }
        return json_encode($questions, JSON_PRETTY_PRINT);
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
        if ($file === 'catalogs.json') {
            if (!is_array($data)) {
                $data = json_decode((string)$data, true) ?? [];
            }
            $this->pdo->beginTransaction();
            $this->pdo->exec('DELETE FROM catalogs');
            $stmt = $this->pdo->prepare('INSERT INTO catalogs(uid,id,file,name,description,qrcode_url,raetsel_buchstabe,comment) VALUES(?,?,?,?,?,?,?,?)');
            foreach ($data as $cat) {
                $stmt->execute([
                    $cat['uid'] ?? '',
                    $cat['id'] ?? '',
                    $cat['file'] ?? '',
                    $cat['name'] ?? '',
                    $cat['description'] ?? null,
                    $cat['qrcode_url'] ?? null,
                    $cat['raetsel_buchstabe'] ?? null,
                    $cat['comment'] ?? null,
                ]);
            }
            $this->pdo->commit();
            return;
        }

        if (!is_array($data)) {
            $data = json_decode((string)$data, true) ?? [];
        }
        $stmt = $this->pdo->prepare('SELECT id FROM catalogs WHERE file=?');
        $stmt->execute([basename($file)]);
        $catId = $stmt->fetchColumn();
        if ($catId === false) {
            return;
        }
        $this->pdo->beginTransaction();
        $del = $this->pdo->prepare('DELETE FROM questions WHERE catalog_id=?');
        $del->execute([$catId]);
        $qStmt = $this->pdo->prepare('INSERT INTO questions(catalog_id,type,prompt,options,answers,terms,items) VALUES(?,?,?,?,?,?,?)');
        foreach ($data as $q) {
            $qStmt->execute([
                $catId,
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
            $this->pdo->exec('DELETE FROM questions');
            $this->pdo->exec('DELETE FROM catalogs');
            return;
        }
        $stmt = $this->pdo->prepare('SELECT id FROM catalogs WHERE file=?');
        $stmt->execute([basename($file)]);
        $catId = $stmt->fetchColumn();
        if ($catId !== false) {
            $this->pdo->prepare('DELETE FROM questions WHERE catalog_id=?')->execute([$catId]);
            $this->pdo->prepare('DELETE FROM catalogs WHERE id=?')->execute([$catId]);
        }
        $id = pathinfo($file, PATHINFO_FILENAME);
        $this->pdo->beginTransaction();
        $this->pdo->prepare('DELETE FROM questions WHERE catalog_id=?')->execute([$id]);
        $this->pdo->prepare('DELETE FROM catalogs WHERE id=?')->execute([$id]);
        $this->pdo->commit();
    }

    public function deleteQuestion(string $file, int $index): void
    {
        $stmt = $this->pdo->prepare('SELECT id FROM catalogs WHERE file=?');
        $stmt->execute([basename($file)]);
        $catId = $stmt->fetchColumn();
        if ($catId === false) {
            return;
        }
        $qStmt = $this->pdo->prepare('SELECT id FROM questions WHERE catalog_id=? ORDER BY id');
        $qStmt->execute([$catId]);
        $rows = $qStmt->fetchAll(PDO::FETCH_COLUMN);
        if (!isset($rows[$index])) {
            return;
        }
        $del = $this->pdo->prepare('DELETE FROM questions WHERE id=?');
        $del->execute([$rows[$index]]);

    }
}
