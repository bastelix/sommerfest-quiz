<?php

declare(strict_types=1);

namespace App\Service;


use PDO;
use PDOException;
use App\Service\ConfigService;

/**
 * Provides accessors for reading and writing quiz catalogs.
 */
class CatalogService
{
    private PDO $pdo;
    /** @var bool|null detected presence of the comment column */
    private ?bool $hasComment = null;

    /**
     * Inject database connection.
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Retrieve the UID of the currently active event.
     */
    private function activeEventUid(): string
    {
        try {
            $stmt = $this->pdo->query('SELECT activeEventUid FROM config LIMIT 1');
            $uid = $stmt->fetchColumn();
            return $uid === false ? '' : (string)$uid;
        } catch (PDOException $e) {
            return '';
        }
    }

    /**
     * Ensure the optional comment column exists and remember the result.
     */
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

    /**
     * Return the catalog slug for the given file name.
     */
    public function slugByFile(string $file): ?string
    {
        $uid = $this->activeEventUid();
        $sql = 'SELECT slug FROM catalogs WHERE file=?';
        $params = [basename($file)];
        if ($uid !== '') {
            $sql .= ' AND event_uid=?';
            $params[] = $uid;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $slug = $stmt->fetchColumn();
        return $slug === false ? null : (string)$slug;
    }

    /**
     * Find the catalog UID by its slug.
     */
    public function uidBySlug(string $slug): ?string
    {
        $uid = $this->activeEventUid();
        $sql = 'SELECT uid FROM catalogs WHERE slug=?';
        $params = [$slug];
        if ($uid !== '') {
            $sql .= ' AND event_uid=?';
            $params[] = $uid;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $uid = $stmt->fetchColumn();
        return $uid === false ? null : (string)$uid;
    }

    /**
     * Read a catalog or the catalog index and return it as JSON.
     */
    public function read(string $file): ?string
    {
        if ($file === 'catalogs.json') {
            $fields = 'uid,sort_order,slug,file,name,description,qrcode_url,raetsel_buchstabe';
            if ($this->hasCommentColumn()) {
                $fields .= ',comment';
            }
            $uid = $this->activeEventUid();
            $sql = "SELECT $fields FROM catalogs";
            $params = [];
            if ($uid !== '') {
                $sql .= ' WHERE event_uid=?';
                $params[] = $uid;
            }
            $sql .= ' ORDER BY sort_order';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($data as &$row) {
                $row['sort_order'] = (int)$row['sort_order'];
            }
            if (!$this->hasCommentColumn()) {
                foreach ($data as &$row) {
                    $row['comment'] = '';
                }
            }
            return json_encode($data, JSON_PRETTY_PRINT);
        }

        $uid = $this->activeEventUid();
        $sql = 'SELECT uid FROM catalogs WHERE file=?';
        $params = [basename($file)];
        if ($uid !== '') {
            $sql .= ' AND event_uid=?';
            $params[] = $uid;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $cat = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($cat === false) {
            return null;
        }
        $qStmt = $this->pdo->prepare('SELECT type,prompt,options,answers,terms,items FROM questions WHERE catalog_uid=? ORDER BY sort_order');
        $qStmt->execute([$cat['uid']]);
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
    /**
     * Persist catalog data to disk or database.
     *
     * @param array|string $data
     */
    public function write(string $file, $data): void
    {
        if ($file === 'catalogs.json') {
            if (!is_array($data)) {
                $data = json_decode((string)$data, true) ?? [];
            }
            $uid = $this->activeEventUid();
            $this->pdo->beginTransaction();
            if ($uid !== '') {
                $del = $this->pdo->prepare('DELETE FROM catalogs WHERE event_uid=?');
                $del->execute([$uid]);
            } else {
                $this->pdo->exec('DELETE FROM catalogs');
            }
            $fields = 'uid,sort_order,slug,file,name,description,qrcode_url,raetsel_buchstabe,event_uid';
            $placeholders = '?,?,?,?,?,?,?,?,?';
            if ($this->hasCommentColumn()) {
                $fields .= ',comment';
                $placeholders .= ',?';
            }
            $insertSql = "INSERT INTO catalogs($fields) VALUES($placeholders)";
            $ins = $this->pdo->prepare($insertSql);
            foreach ($data as $cat) {
                $row = [
                    $cat['uid'] ?? bin2hex(random_bytes(16)),
                    $cat['sort_order'] ?? '',
                    $cat['slug'] ?? '',
                    $cat['file'] ?? '',
                    $cat['name'] ?? '',
                    $cat['description'] ?? null,
                    $cat['qrcode_url'] ?? null,
                    $cat['raetsel_buchstabe'] ?? null,
                    $uid,
                ];
                if ($this->hasCommentColumn()) {
                    $row[] = $cat['comment'] ?? null;
                }
                $ins->execute($row);
            }
            $this->pdo->commit();
            return;
        }

        if (!is_array($data)) {
            $data = json_decode((string)$data, true) ?? [];
        }
        $slug = pathinfo($file, PATHINFO_FILENAME);
        $uid = $this->activeEventUid();
        $sql = 'SELECT uid FROM catalogs WHERE slug=?';
        $params = [$slug];
        if ($uid !== '') {
            $sql .= ' AND event_uid=?';
            $params[] = $uid;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $cat = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($cat === false) {
            return;
        }
        $this->pdo->beginTransaction();
        $del = $this->pdo->prepare('DELETE FROM questions WHERE catalog_uid=?');
        $del->execute([$cat['uid']]);
        $qStmt = $this->pdo->prepare('INSERT INTO questions(catalog_uid,type,prompt,options,answers,terms,items,sort_order) VALUES(?,?,?,?,?,?,?,?)');
        foreach ($data as $i => $q) {
            $qStmt->execute([
                $cat['uid'],
                $q['type'] ?? '',
                $q['prompt'] ?? '',
                isset($q['options']) ? json_encode($q['options']) : null,
                isset($q['answers']) ? json_encode($q['answers']) : null,
                isset($q['terms']) ? json_encode($q['terms']) : null,
                isset($q['items']) ? json_encode($q['items']) : null,
                $i + 1,
            ]);
        }
        $this->pdo->commit();
    }

    /**
     * Delete a catalog and all associated questions.
     */
    public function delete(string $file): void
    {
        if ($file === 'catalogs.json') {
            $uid = $this->activeEventUid();
            if ($uid !== '') {
                $stmt = $this->pdo->prepare('DELETE FROM catalogs WHERE event_uid=?');
                $stmt->execute([$uid]);
            } else {
                $this->pdo->exec('DELETE FROM catalogs');
            }
            return;
        }
        $slug = pathinfo($file, PATHINFO_FILENAME);
        $event = $this->activeEventUid();
        $sql = 'SELECT uid FROM catalogs WHERE slug=?';
        $params = [$slug];
        if ($event !== '') {
            $sql .= ' AND event_uid=?';
            $params[] = $event;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $cat = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($cat !== false) {
            $this->pdo->prepare('DELETE FROM questions WHERE catalog_uid=?')->execute([$cat['uid']]);
            $this->pdo->prepare('DELETE FROM catalogs WHERE uid=?')->execute([$cat['uid']]);
        }
        $this->pdo->beginTransaction();
        $uid = $cat['uid'] ?? $slug;
        $this->pdo->prepare('DELETE FROM questions WHERE catalog_uid=?')->execute([$uid]);
        $this->pdo->prepare('DELETE FROM catalogs WHERE uid=?')->execute([$uid]);
        $this->pdo->commit();
    }

    /**
     * Remove a question at a specific index from a catalog file.
     */
    public function deleteQuestion(string $file, int $index): void
    {
        $slug = pathinfo($file, PATHINFO_FILENAME);
        $uid = $this->activeEventUid();
        $sql = 'SELECT uid FROM catalogs WHERE slug=?';
        $params = [$slug];
        if ($uid !== '') {
            $sql .= ' AND event_uid=?';
            $params[] = $uid;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $cat = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($cat === false) {
            return;
        }
        $qStmt = $this->pdo->prepare('SELECT id FROM questions WHERE catalog_uid=? ORDER BY sort_order');
        $qStmt->execute([$cat['uid']]);
        $rows = $qStmt->fetchAll(PDO::FETCH_COLUMN);
        if (!isset($rows[$index])) {
            return;
        }
        $del = $this->pdo->prepare('DELETE FROM questions WHERE id=?');
        $del->execute([$rows[$index]]);

    }
}
