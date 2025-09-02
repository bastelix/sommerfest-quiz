<?php

declare(strict_types=1);

namespace App\Service;

use PDO;
use PDOException;
use App\Service\ConfigService;
use App\Service\TenantService;

/**
 * Provides accessors for reading and writing quiz catalogs.
 */
class CatalogService
{
    private PDO $pdo;
    private ConfigService $config;
    private ?TenantService $tenants;
    private string $subdomain;
    /** @var bool|null detected presence of the comment column */
    private ?bool $hasComment = null;
    /** @var bool|null detected presence of the design_path column */
    private ?bool $hasDesign = null;

    /**
     * Inject database connection.
     */
    public function __construct(
        PDO $pdo,
        ConfigService $config,
        ?TenantService $tenants = null,
        string $subdomain = ''
    ) {
        $this->pdo = $pdo;
        $this->config = $config;
        $this->tenants = $tenants;
        $this->subdomain = $subdomain;
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
     * Ensure the optional design_path column exists and remember the result.
     */
    private function hasDesignColumn(): bool
    {
        if ($this->hasDesign !== null) {
            return $this->hasDesign;
        }
        try {
            $this->pdo->query('SELECT design_path FROM catalogs LIMIT 1');
            $this->hasDesign = true;
        } catch (\PDOException $e) {
            try {
                $this->pdo->exec('ALTER TABLE catalogs ADD COLUMN design_path TEXT');
                $this->hasDesign = true;
            } catch (\PDOException $e2) {
                $this->hasDesign = false;
            }
        }
        return $this->hasDesign;
    }

    /**
     * Return the catalog slug for the given file name.
     */
    public function slugByFile(string $file): ?string
    {
        $uid = $this->config->getActiveEventUid();
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
        $uid = $this->config->getActiveEventUid();
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
     * Create a catalog entry if it does not already exist.
     */
    public function createCatalog(string $file): void
    {
        $slug = pathinfo($file, PATHINFO_FILENAME);
        $event = $this->config->getActiveEventUid();

        $sql = 'SELECT COUNT(*) FROM catalogs WHERE slug=?';
        $params = [$slug];
        if ($event !== '') {
            $sql .= ' AND event_uid=?';
            $params[] = $event;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $exists = (int) $stmt->fetchColumn() > 0;
        if ($exists) {
            return;
        }

        if ($this->tenants !== null && $this->subdomain !== '') {
            $limits = $this->tenants->getLimitsBySubdomain($this->subdomain);
            $max = $limits['maxCatalogsPerEvent'] ?? null;
            if ($max !== null) {
                $countSql = 'SELECT COUNT(*) FROM catalogs';
                $countParams = [];
                if ($event !== '') {
                    $countSql .= ' WHERE event_uid=?';
                    $countParams[] = $event;
                }
                $cStmt = $this->pdo->prepare($countSql);
                $cStmt->execute($countParams);
                $current = (int) $cStmt->fetchColumn();
                if ($current >= $max) {
                    throw new \RuntimeException('max-catalogs-exceeded');
                }
            }
        }

        $sortSql = 'SELECT COALESCE(MAX(sort_order),0) FROM catalogs';
        $sortParams = [];
        if ($event !== '') {
            $sortSql .= ' WHERE event_uid=?';
            $sortParams[] = $event;
        }
        $sStmt = $this->pdo->prepare($sortSql);
        $sStmt->execute($sortParams);
        $sortOrder = ((int) $sStmt->fetchColumn()) + 1;

        $ins = $this->pdo->prepare(
            'INSERT INTO catalogs(uid,sort_order,slug,file,name,event_uid) '
            . 'VALUES(?,?,?,?,?,?)'
        );
        $ins->execute([
            bin2hex(random_bytes(16)),
            $sortOrder,
            $slug,
            basename($file),
            $slug,
            $event !== '' ? $event : null,
        ]);
    }

    /**
     * Read a catalog or the catalog index and return it as JSON.
     */
    public function read(string $file): ?string
    {
        if ($file === 'catalogs.json') {
            $fields = 'uid,sort_order,slug,file,name,description,raetsel_buchstabe';
            if ($this->hasCommentColumn()) {
                $fields .= ',comment';
            }
            if ($this->hasDesignColumn()) {
                $fields .= ',design_path';
            }
            $uid = $this->config->getActiveEventUid();
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
            if (!$this->hasDesignColumn()) {
                foreach ($data as &$row) {
                    $row['design_path'] = null;
                }
            }
            return json_encode($data, JSON_PRETTY_PRINT);
        }

        $uid = $this->config->getActiveEventUid();
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
        $qStmt = $this->pdo->prepare(
            'SELECT type,prompt,options,answers,terms,items ' .
            'FROM questions WHERE catalog_uid=? ORDER BY sort_order'
        );
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
            if ($row['type'] === 'flip' && isset($row['answers'])) {
                $row['answer'] = is_array($row['answers'])
                    ? reset($row['answers'])
                    : $row['answers'];
                unset($row['answers']);
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
            if ($this->tenants !== null && $this->subdomain !== '') {
                $limits = $this->tenants->getLimitsBySubdomain($this->subdomain);
                $max = $limits['maxCatalogsPerEvent'] ?? null;
                if ($max !== null && count($data) > $max) {
                    throw new \RuntimeException('max-catalogs-exceeded');
                }
            }
            $uid = $this->config->getActiveEventUid();
            $this->pdo->beginTransaction();

            $fields = ['uid', 'sort_order', 'slug', 'file', 'name', 'description', 'raetsel_buchstabe', 'event_uid'];
            $placeholders = array_fill(0, count($fields), '?');
            $updates = [
                'sort_order=excluded.sort_order',
                'slug=excluded.slug',
                'file=excluded.file',
                'name=excluded.name',
                'description=excluded.description',
                'raetsel_buchstabe=excluded.raetsel_buchstabe',
                'event_uid=excluded.event_uid',
            ];
            if ($this->hasCommentColumn()) {
                $fields[] = 'comment';
                $placeholders[] = '?';
                $updates[] = 'comment=excluded.comment';
            }
            if ($this->hasDesignColumn()) {
                $fields[] = 'design_path';
                $placeholders[] = '?';
                $updates[] = 'design_path=excluded.design_path';
            }

            $insertSql = sprintf(
                'INSERT INTO catalogs(%s) VALUES(%s) ON CONFLICT(uid) DO UPDATE SET %s',
                implode(',', $fields),
                implode(',', $placeholders),
                implode(',', $updates)
            );
            $uids = [];
            $prepared = [];
            foreach ($data as $cat) {
                $catUid = $cat['uid'] ?? bin2hex(random_bytes(16));
                $cat['uid'] = $catUid;
                $prepared[] = $cat;
                $uids[] = $catUid;
            }

            if ($uids !== []) {
                foreach ($uids as $i => $u) {
                    if ($uid !== '') {
                        $clr = $this->pdo->prepare('UPDATE catalogs SET sort_order=? WHERE uid=? AND event_uid=?');
                        $clr->execute([-$i - 1, $u, $uid]);
                    } else {
                        $clr = $this->pdo->prepare('UPDATE catalogs SET sort_order=? WHERE uid=?');
                        $clr->execute([-$i - 1, $u]);
                    }
                }
            }

            $ins = $this->pdo->prepare($insertSql);
            foreach ($prepared as $cat) {
                $row = [
                    $cat['uid'],
                    isset($cat['sort_order'])
                        ? (int) $cat['sort_order']
                        : (isset($cat['id']) ? (int) $cat['id'] : 0),
                    $cat['slug'] ?? '',
                    $cat['file'] ?? '',
                    $cat['name'] ?? '',
                    $cat['description'] ?? null,
                    $cat['raetsel_buchstabe'] ?? null,
                    $uid !== '' ? $uid : null,
                ];
                if ($this->hasCommentColumn()) {
                    $row[] = $cat['comment'] ?? null;
                }
                if ($this->hasDesignColumn()) {
                    $row[] = $cat['design_path'] ?? null;
                }
                $ins->execute($row);
            }

            if ($uids !== []) {
                $in = implode(',', array_fill(0, count($uids), '?'));
                if ($uid !== '') {
                    $del = $this->pdo->prepare("DELETE FROM catalogs WHERE uid NOT IN ($in) AND event_uid=?");
                    $del->execute(array_merge($uids, [$uid]));
                } else {
                    $del = $this->pdo->prepare("DELETE FROM catalogs WHERE uid NOT IN ($in)");
                    $del->execute($uids);
                }
            } elseif ($uid !== '') {
                $del = $this->pdo->prepare('DELETE FROM catalogs WHERE event_uid=?');
                $del->execute([$uid]);
            } else {
                $this->pdo->exec('DELETE FROM catalogs');
            }

            $this->pdo->commit();
            return;
        }

        if (!is_array($data)) {
            $data = json_decode((string)$data, true) ?? [];
        }
        if ($this->tenants !== null && $this->subdomain !== '') {
            $limits = $this->tenants->getLimitsBySubdomain($this->subdomain);
            $maxQuestions = $limits['maxQuestionsPerCatalog'] ?? null;
            if ($maxQuestions !== null && count($data) > $maxQuestions) {
                throw new \RuntimeException('max-questions-exceeded');
            }
        }
        $slug = pathinfo($file, PATHINFO_FILENAME);
        $uid = $this->config->getActiveEventUid();
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
            $sql = 'SELECT uid FROM catalogs WHERE file=?';
            $params = [basename($file)];
            if ($uid !== '') {
                $sql .= ' AND event_uid=?';
                $params[] = $uid;
            }
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $cat = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($cat === false && $this->tenants !== null && $this->subdomain !== '') {
                $limits = $this->tenants->getLimitsBySubdomain($this->subdomain);
                $max = $limits['maxCatalogsPerEvent'] ?? null;
                if ($max !== null) {
                    $countSql = 'SELECT COUNT(*) FROM catalogs';
                    $countParams = [];
                    if ($uid !== '') {
                        $countSql .= ' WHERE event_uid=?';
                        $countParams[] = $uid;
                    }
                    $cStmt = $this->pdo->prepare($countSql);
                    $cStmt->execute($countParams);
                    $current = (int) $cStmt->fetchColumn();
                    if ($current >= $max) {
                        throw new \RuntimeException('max-catalogs-exceeded');
                    }
                }
            }
        }
        $this->pdo->beginTransaction();

        if ($cat === false) {
            $cat = ['uid' => bin2hex(random_bytes(16))];
            $fields = 'uid,sort_order,slug,file,name,event_uid';
            $placeholders = '?,?,?,?,?,?';
            if ($this->hasCommentColumn()) {
                $fields .= ',comment';
                $placeholders .= ',?';
            }
            if ($this->hasDesignColumn()) {
                $fields .= ',design_path';
                $placeholders .= ',?';
            }
            $ins = $this->pdo->prepare(
                "INSERT INTO catalogs($fields) VALUES($placeholders)"
            );
            $sortSql = 'SELECT COALESCE(MAX(sort_order),0) FROM catalogs';
            $sortParams = [];
            if ($uid !== '') {
                $sortSql .= ' WHERE event_uid=?';
                $sortParams[] = $uid;
            }
            $sStmt = $this->pdo->prepare($sortSql);
            $sStmt->execute($sortParams);
            $sortOrder = ((int) $sStmt->fetchColumn()) + 1;

            $row = [
                $cat['uid'],
                $sortOrder,
                $slug,
                basename($file),
                '',
                $uid !== '' ? $uid : null,
            ];
            if ($this->hasCommentColumn()) {
                $row[] = null;
            }
            if ($this->hasDesignColumn()) {
                $row[] = null;
            }
            $ins->execute($row);
        }

        $del = $this->pdo->prepare('DELETE FROM questions WHERE catalog_uid=?');
        $del->execute([$cat['uid']]);
        $qStmt = $this->pdo->prepare(
            'INSERT INTO questions(' .
            'catalog_uid,type,prompt,options,answers,terms,items,sort_order)' .
            ' VALUES(?,?,?,?,?,?,?,?)'
        );
        foreach ($data as $i => $q) {
            $answers = null;
            if (isset($q['answer'])) {
                $answers = json_encode($q['answer']);
            } elseif (isset($q['answers'])) {
                $answers = json_encode($q['answers']);
            }

            $qStmt->execute([
                $cat['uid'],
                $q['type'] ?? '',
                $q['prompt'] ?? '',
                isset($q['options']) ? json_encode($q['options']) : null,
                $answers,
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
            $uid = $this->config->getActiveEventUid();
            if ($uid !== '') {
                $stmt = $this->pdo->prepare('DELETE FROM catalogs WHERE event_uid=?');
                $stmt->execute([$uid]);
            } else {
                $this->pdo->exec('DELETE FROM catalogs');
            }
            return;
        }
        $slug = pathinfo($file, PATHINFO_FILENAME);
        $event = $this->config->getActiveEventUid();
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
        $uid = $this->config->getActiveEventUid();
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

    /**
     * Return the design path for the given catalog slug.
     */
    public function getDesignPath(string $slug): ?string
    {
        if (!$this->hasDesignColumn()) {
            return null;
        }
        $uid = $this->config->getActiveEventUid();
        $sql = 'SELECT design_path FROM catalogs WHERE slug=?';
        $params = [$slug];
        if ($uid !== '') {
            $sql .= ' AND event_uid=?';
            $params[] = $uid;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $path = $stmt->fetchColumn();
        return $path === false ? null : (string)$path;
    }

    /**
     * Update the design path for the given catalog slug.
     */
    public function setDesignPath(string $slug, ?string $path): void
    {
        if (!$this->hasDesignColumn()) {
            return;
        }
        $uid = $this->config->getActiveEventUid();
        $sql = 'UPDATE catalogs SET design_path=? WHERE slug=?';
        $params = [$path, $slug];
        if ($uid !== '') {
            $sql .= ' AND event_uid=?';
            $params[] = $uid;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }
}
