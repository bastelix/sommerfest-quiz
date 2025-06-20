<?php

declare(strict_types=1);

namespace App\Service;


use PDO;

class CatalogService
{
    private PDO $pdo;
    /** @var bool|null detected presence of the comment column */
    private ?bool $hasComment = null;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    private function hasCommentColumn(): bool
    {
        if ($this->hasComment !== null) {
            return $this->hasComment;
        }
        try {
            $this->pdo->query('SELECT comment FROM catalogs LIMIT 1');
            $this->hasComment = true;
        } catch (\PDOException $e) {
            try {
                $this->pdo->exec('ALTER TABLE catalogs ADD COLUMN comment TEXT');
                $this->hasComment = true;
            } catch (\PDOException $e2) {
                $this->hasComment = false;
            }
        }
        return $this->hasComment;
    }

    public function slugByFile(string $file): ?string
    {
        $stmt = $this->pdo->prepare('SELECT slug FROM catalogs WHERE file=?');
        $stmt->execute([basename($file)]);
        $slug = $stmt->fetchColumn();
        return $slug === false ? null : (string)$slug;
    }

    public function read(string $file): ?string
    {
        if ($file === 'catalogs.json') {
            $fields = 'uid,id,slug,file,name,description,qrcode_url,raetsel_buchstabe';
            if ($this->hasCommentColumn()) {
                $fields .= ',comment';
            }
            $stmt = $this->pdo->query("SELECT $fields FROM catalogs ORDER BY id");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($data as &$row) {
                $row['id'] = (int)$row['id'];
            }
            if (!$this->hasCommentColumn()) {
                foreach ($data as &$row) {
                    $row['comment'] = '';
                }
            }
            return json_encode($data, JSON_PRETTY_PRINT);
        }

        $stmt = $this->pdo->prepare('SELECT id, slug FROM catalogs WHERE file=?');
        $stmt->execute([basename($file)]);
        $cat = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($cat === false) {
            return null;
        }
        $qStmt = $this->pdo->prepare('SELECT type,prompt,options,answers,terms,items FROM questions WHERE catalog_id=? OR catalog_id=? ORDER BY id');
        $qStmt->execute([$cat['id'], $cat['slug']]);
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
            $fields = 'uid,id,slug,file,name,description,qrcode_url,raetsel_buchstabe';
            $placeholders = '?,?,?,?,?,?,?,?';
            if ($this->hasCommentColumn()) {
                $fields .= ',comment';
                $placeholders .= ',?';
            }

            $uids = [];
            $updateClauses = [
                'id' => '',
                'slug' => '',
                'file' => '',
                'name' => '',
                'description' => '',
                'qrcode_url' => '',
                'raetsel_buchstabe' => '',
            ];
            if ($this->hasCommentColumn()) {
                $updateClauses['comment'] = '';
            }
            $params = [];
            foreach ($data as $cat) {
                $uid = $cat['uid'] ?? '';
                $uids[] = $uid;
                $updateClauses['id'] .= ' WHEN ? THEN ?';
                $params[] = $uid;
                $params[] = $cat['id'] ?? '';
                $updateClauses['slug'] .= ' WHEN ? THEN ?';
                $params[] = $uid;
                $params[] = $cat['slug'] ?? ($cat['id'] ?? '');
                $updateClauses['file'] .= ' WHEN ? THEN ?';
                $params[] = $uid;
                $params[] = $cat['file'] ?? '';
                $updateClauses['name'] .= ' WHEN ? THEN ?';
                $params[] = $uid;
                $params[] = $cat['name'] ?? '';
                $updateClauses['description'] .= ' WHEN ? THEN ?';
                $params[] = $uid;
                $params[] = $cat['description'] ?? null;
                $updateClauses['qrcode_url'] .= ' WHEN ? THEN ?';
                $params[] = $uid;
                $params[] = $cat['qrcode_url'] ?? null;
                $updateClauses['raetsel_buchstabe'] .= ' WHEN ? THEN ?';
                $params[] = $uid;
                $params[] = $cat['raetsel_buchstabe'] ?? null;
                if ($this->hasCommentColumn()) {
                    $updateClauses['comment'] .= ' WHEN ? THEN ?';
                    $params[] = $uid;
                    $params[] = $cat['comment'] ?? null;
                }
            }

            if ($uids !== []) {
                $setParts = [];
                foreach ($updateClauses as $col => $case) {
                    $setParts[] = "$col = CASE uid$case ELSE $col END";
                }
                $sql = 'UPDATE catalogs SET ' . implode(',', $setParts)
                    . ' WHERE uid IN (' . implode(',', array_fill(0, count($uids), '?')) . ')';
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute(array_merge($params, $uids));
            }

            $insertSql = "INSERT INTO catalogs($fields) VALUES($placeholders) ON CONFLICT(uid) DO NOTHING";
            $ins = $this->pdo->prepare($insertSql);
            foreach ($data as $cat) {
                $row = [
                    $cat['uid'] ?? '',
                    $cat['id'] ?? '',
                    $cat['slug'] ?? ($cat['id'] ?? ''),
                    $cat['file'] ?? '',
                    $cat['name'] ?? '',
                    $cat['description'] ?? null,
                    $cat['qrcode_url'] ?? null,
                    $cat['raetsel_buchstabe'] ?? null,
                ];
                if ($this->hasCommentColumn()) {
                    $row[] = $cat['comment'] ?? null;
                }
                $ins->execute($row);
            }

            if ($uids === []) {
                $this->pdo->exec('DELETE FROM catalogs');
            } else {
                $in  = implode(',', array_fill(0, count($uids), '?'));
                $del = $this->pdo->prepare("DELETE FROM catalogs WHERE uid NOT IN ($in)");
                $del->execute($uids);
            }

            $this->pdo->commit();
            return;
        }

        if (!is_array($data)) {
            $data = json_decode((string)$data, true) ?? [];
        }
        $stmt = $this->pdo->prepare('SELECT id, slug FROM catalogs WHERE file=?');
        $stmt->execute([basename($file)]);
        $cat = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($cat === false) {
            return;
        }
        $this->pdo->beginTransaction();
        $del = $this->pdo->prepare('DELETE FROM questions WHERE catalog_id=? OR catalog_id=?');
        $del->execute([$cat['id'], $cat['slug']]);
        $qStmt = $this->pdo->prepare('INSERT INTO questions(catalog_id,type,prompt,options,answers,terms,items) VALUES(?,?,?,?,?,?,?)');
        foreach ($data as $q) {
            $qStmt->execute([
                $cat['slug'],
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
        $stmt = $this->pdo->prepare('SELECT id, slug FROM catalogs WHERE file=?');
        $stmt->execute([basename($file)]);
        $cat = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($cat !== false) {
            $this->pdo->prepare('DELETE FROM questions WHERE catalog_id=? OR catalog_id=?')->execute([$cat['id'], $cat['slug']]);
            $this->pdo->prepare('DELETE FROM catalogs WHERE id=?')->execute([$cat['id']]);
        }
        $id = pathinfo($file, PATHINFO_FILENAME);
        $this->pdo->beginTransaction();
        $this->pdo->prepare('DELETE FROM questions WHERE catalog_id=? OR catalog_id=?')->execute([$id, $id]);
        $this->pdo->prepare('DELETE FROM catalogs WHERE id=?')->execute([$id]);
        $this->pdo->commit();
    }

    public function deleteQuestion(string $file, int $index): void
    {
        $stmt = $this->pdo->prepare('SELECT id, slug FROM catalogs WHERE file=?');
        $stmt->execute([basename($file)]);
        $cat = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($cat === false) {
            return;
        }
        $qStmt = $this->pdo->prepare('SELECT id FROM questions WHERE catalog_id=? OR catalog_id=? ORDER BY id');
        $qStmt->execute([$cat['id'], $cat['slug']]);
        $rows = $qStmt->fetchAll(PDO::FETCH_COLUMN);
        if (!isset($rows[$index])) {
            return;
        }
        $del = $this->pdo->prepare('DELETE FROM questions WHERE id=?');
        $del->execute([$rows[$index]]);

    }
}
