<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Page;
use App\Infrastructure\Database;
use InvalidArgumentException;
use LogicException;
use PDO;
use PDOException;
use RuntimeException;

/**
 * Simple service for loading and saving static pages from the database.
 */
class PageService
{
    public const DEFAULT_NAMESPACE = 'default';
    private const SLUG_PATTERN = '/^[a-z0-9][a-z0-9\-]{0,99}$/';

    private PDO $pdo;

    public function __construct(?PDO $pdo = null) {
        $this->pdo = $pdo ?? Database::connectFromEnv();
    }

    public function getByKey(string $namespace, string $slug): ?string {
        $page = $this->findByKey($namespace, $slug);
        return $page?->getContent();
    }

    public function save(string $namespace, string $slug, string $content): void {
        $stmt = $this->pdo->prepare(
            'UPDATE pages SET content = ?, updated_at = CURRENT_TIMESTAMP WHERE namespace = ? AND slug = ?'
        );
        $stmt->execute([$content, $namespace, $slug]);
    }

    public function delete(string $namespace, string $slug): void {
        $normalized = trim($slug);
        if ($normalized === '') {
            return;
        }

        $stmt = $this->pdo->prepare('DELETE FROM pages WHERE namespace = ? AND slug = ?');
        $stmt->execute([$namespace, $normalized]);
    }

    public function create(string $namespace, string $slug, string $title, string $content): Page {
        $normalizedNamespace = $this->normalizeNamespaceInput($namespace);
        $this->assertValidNamespace($normalizedNamespace);

        $normalizedSlug = $this->normalizeSlugInput($slug);
        $this->assertValidSlug($normalizedSlug);

        $normalizedTitle = trim($title);
        if ($normalizedTitle === '') {
            throw new InvalidArgumentException('Bitte gib einen Titel für die Seite an.');
        }

        if ($this->findByKey($normalizedNamespace, $normalizedSlug) !== null) {
            throw new LogicException(
                sprintf('Eine Seite mit dem Namespace "%s" und dem Slug "%s" existiert bereits.', $normalizedNamespace, $normalizedSlug)
            );
        }

        $html = (string) $content;

        $sortOrder = $this->getNextSortOrder($normalizedNamespace, null);
        $stmt = $this->pdo->prepare(
            'INSERT INTO pages (namespace, slug, title, content, sort_order) VALUES (?, ?, ?, ?, ?)'
        );

        try {
            $stmt->execute([$normalizedNamespace, $normalizedSlug, $normalizedTitle, $html, $sortOrder]);
        } catch (PDOException $exception) {
            throw new RuntimeException('Die Seite konnte nicht angelegt werden.', 0, $exception);
        }

        return $this->loadCreatedPage($normalizedNamespace, $normalizedSlug);
    }

    /**
     * Fetch all stored pages.
     *
     * @return Page[]
     */
    public function getAll(): array {
        $stmt = $this->pdo->query(
            'SELECT id, namespace, slug, title, content, type, parent_id, sort_order, status, language, content_source '
            . 'FROM pages ORDER BY title'
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $pages = [];
        foreach ($rows as $row) {
            $page = $this->mapRowToPage($row);
            if ($page === null) {
                continue;
            }

            $pages[] = $page;
        }

        return $pages;
    }

    /**
     * Fetch all stored pages for the given namespace.
     *
     * @return Page[]
     */
    public function getAllForNamespace(string $namespace): array {
        $normalized = $this->normalizeNamespace($namespace);
        if ($normalized === '') {
            return [];
        }

        $stmt = $this->pdo->prepare(
            'SELECT id, namespace, slug, title, content, type, parent_id, sort_order, status, language, content_source '
            . 'FROM pages WHERE namespace = ? ORDER BY title'
        );
        $stmt->execute([$normalized]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $pages = [];
        foreach ($rows as $row) {
            $page = $this->mapRowToPage($row);
            if ($page === null) {
                continue;
            }

            $pages[] = $page;
        }

        return $pages;
    }

    public function findById(int $id): ?Page {
        if ($id <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT id, namespace, slug, title, content, type, parent_id, sort_order, status, language, content_source '
            . 'FROM pages WHERE id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        return $this->mapRowToPage($row);
    }

    public function findByKey(string $namespace, string $slug): ?Page {
        $normalizedNamespace = trim($namespace);
        if ($normalizedNamespace === '') {
            return null;
        }

        $normalized = trim($slug);
        if ($normalized === '') {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT id, namespace, slug, title, content, type, parent_id, sort_order, status, language, content_source '
            . 'FROM pages WHERE namespace = ? AND slug = ?'
        );
        $stmt->execute([$normalizedNamespace, $normalized]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        return $this->mapRowToPage($row);
    }

    /**
     * @return array{page: Page, copied: array<int, array<string, int|string>>}
     */
    public function copy(string $sourceNamespace, string $slug, string $targetNamespace): array {
        $sourceNamespace = $this->normalizeNamespaceInput($sourceNamespace);
        $this->assertValidNamespace($sourceNamespace);
        $targetNamespace = $this->normalizeNamespaceInput($targetNamespace);
        $this->assertValidNamespace($targetNamespace);

        $normalizedSlug = $this->normalizeSlugInput($slug);
        $this->assertValidSlug($normalizedSlug);

        if ($sourceNamespace === $targetNamespace) {
            throw new InvalidArgumentException('Quell- und Ziel-Namespace müssen unterschiedlich sein.');
        }

        $sourcePage = $this->findByKey($sourceNamespace, $normalizedSlug);
        if ($sourcePage === null) {
            throw new LogicException('Die Quelle wurde nicht gefunden.');
        }

        $rows = $this->loadRowsForNamespace($sourceNamespace);
        $subtree = $this->buildSubtreeRows($rows, $sourcePage->getId());
        if ($subtree === []) {
            throw new LogicException('Die Quelle wurde nicht gefunden.');
        }

        $this->assertNoSlugConflicts($targetNamespace, $subtree);

        $created = [];
        $idMap = [];
        $insert = $this->pdo->prepare(
            'INSERT INTO pages '
            . '(namespace, slug, title, content, type, parent_id, sort_order, status, language, content_source) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id'
        );

        $this->pdo->beginTransaction();
        try {
            foreach ($subtree as $row) {
                $parentId = $row['parent_id'] !== null && isset($idMap[$row['parent_id']])
                    ? $idMap[$row['parent_id']]
                    : null;

                $insert->execute([
                    $targetNamespace,
                    $row['slug'],
                    $row['title'],
                    $row['content'],
                    $row['type'],
                    $parentId,
                    $row['sort_order'],
                    $row['status'],
                    $row['language'],
                    $row['content_source'],
                ]);

                $newId = (int) $insert->fetchColumn();
                $idMap[$row['id']] = $newId;
                $created[] = [
                    'id' => $newId,
                    'slug' => (string) $row['slug'],
                    'namespace' => $targetNamespace,
                    'source_id' => (int) $row['id'],
                ];
            }

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw new RuntimeException('Die Seite konnte nicht kopiert werden.', 0, $exception);
        }

        $newRootId = $idMap[$sourcePage->getId()] ?? 0;
        $newRoot = $this->findById($newRootId);
        if ($newRoot === null) {
            throw new RuntimeException('Die kopierte Seite konnte nicht geladen werden.');
        }

        return [
            'page' => $newRoot,
            'copied' => $created,
        ];
    }

    /**
     * @return array{page: Page, moved: array<int, array<string, int|string>>}
     */
    public function move(string $sourceNamespace, string $slug, string $targetNamespace): array {
        $sourceNamespace = $this->normalizeNamespaceInput($sourceNamespace);
        $this->assertValidNamespace($sourceNamespace);
        $targetNamespace = $this->normalizeNamespaceInput($targetNamespace);
        $this->assertValidNamespace($targetNamespace);

        $normalizedSlug = $this->normalizeSlugInput($slug);
        $this->assertValidSlug($normalizedSlug);

        if ($sourceNamespace === $targetNamespace) {
            throw new InvalidArgumentException('Quell- und Ziel-Namespace müssen unterschiedlich sein.');
        }

        $sourcePage = $this->findByKey($sourceNamespace, $normalizedSlug);
        if ($sourcePage === null) {
            throw new LogicException('Die Quelle wurde nicht gefunden.');
        }

        $rows = $this->loadRowsForNamespace($sourceNamespace);
        $subtree = $this->buildSubtreeRows($rows, $sourcePage->getId());
        if ($subtree === []) {
            throw new LogicException('Die Quelle wurde nicht gefunden.');
        }

        $this->assertNoSlugConflicts($targetNamespace, $subtree);

        $subtreeIds = array_map(static fn (array $row): int => (int) $row['id'], $subtree);
        $subtreeLookup = array_flip($subtreeIds);

        $rootParentId = $subtree[0]['parent_id'];
        if ($rootParentId !== null && !isset($subtreeLookup[$rootParentId])) {
            $rootParentId = null;
        }

        $updateNamespace = $this->pdo->prepare('UPDATE pages SET namespace = ? WHERE id = ?');
        $updateRoot = $this->pdo->prepare('UPDATE pages SET namespace = ?, parent_id = ? WHERE id = ?');

        $moved = [];

        $this->pdo->beginTransaction();
        try {
            foreach ($subtree as $row) {
                $id = (int) $row['id'];
                if ($id === $sourcePage->getId()) {
                    $updateRoot->execute([$targetNamespace, $rootParentId, $id]);
                } else {
                    $updateNamespace->execute([$targetNamespace, $id]);
                }
                $moved[] = [
                    'id' => $id,
                    'slug' => (string) $row['slug'],
                    'namespace' => $targetNamespace,
                ];
            }

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw new RuntimeException('Die Seite konnte nicht verschoben werden.', 0, $exception);
        }

        $movedRoot = $this->findByKey($targetNamespace, $normalizedSlug);
        if ($movedRoot === null) {
            throw new RuntimeException('Die verschobene Seite konnte nicht geladen werden.');
        }

        return [
            'page' => $movedRoot,
            'moved' => $moved,
        ];
    }

    private function loadCreatedPage(string $namespace, string $slug): Page {
        $page = $this->findByKey($namespace, $slug);
        if ($page === null) {
            throw new RuntimeException('Die neu angelegte Seite konnte nicht geladen werden.');
        }

        return $page;
    }

    /**
     * Build a recursive page tree grouped by namespace.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTree(): array {
        $pages = $this->getAllForTree();
        $nodes = [];
        foreach ($pages as $page) {
            $nodes[$page->getId()] = [
                'id' => $page->getId(),
                'namespace' => $page->getNamespace(),
                'slug' => $page->getSlug(),
                'title' => $page->getTitle(),
                'type' => $page->getType(),
                'parent_id' => $page->getParentId(),
                'sort_order' => $page->getSortOrder(),
                'status' => $page->getStatus(),
                'language' => $page->getLanguage(),
                'children' => [],
            ];
        }

        $tree = [];
        foreach ($nodes as $id => &$node) {
            $parentId = $node['parent_id'];
            if ($parentId !== null && isset($nodes[$parentId])) {
                $nodes[$parentId]['children'][] = &$node;
                continue;
            }

            $namespace = $node['namespace'] !== '' ? $node['namespace'] : self::DEFAULT_NAMESPACE;
            if (!isset($tree[$namespace])) {
                $tree[$namespace] = [];
            }
            $tree[$namespace][] = &$node;
        }
        unset($node);

        $payload = [];
        foreach ($tree as $namespace => $items) {
            $this->sortTree($items);
            $payload[] = [
                'namespace' => $namespace,
                'pages' => $items,
            ];
        }

        return $payload;
    }

    /**
     * Fetch all pages for tree rendering.
     *
     * @return Page[]
     */
    public function getAllForTree(): array {
        $stmt = $this->pdo->query(
            'SELECT id, namespace, slug, title, content, type, parent_id, sort_order, status, language, content_source '
            . 'FROM pages ORDER BY namespace, parent_id, sort_order, title'
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $pages = [];
        foreach ($rows as $row) {
            $page = $this->mapRowToPage($row);
            if ($page === null) {
                continue;
            }

            $pages[] = $page;
        }

        return $pages;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapRowToPage(array $row): ?Page {
        $id = isset($row['id']) ? (int) $row['id'] : 0;
        $namespace = isset($row['namespace']) ? (string) $row['namespace'] : '';
        $slug = isset($row['slug']) ? (string) $row['slug'] : '';
        $title = isset($row['title']) ? (string) $row['title'] : '';
        $content = isset($row['content']) ? (string) $row['content'] : '';

        if ($id <= 0 || $namespace === '' || $slug === '' || $title === '') {
            return null;
        }

        $type = isset($row['type']) ? (string) $row['type'] : null;
        $parentId = isset($row['parent_id']) ? (int) $row['parent_id'] : null;
        $sortOrder = isset($row['sort_order']) ? (int) $row['sort_order'] : 0;
        $status = isset($row['status']) ? (string) $row['status'] : null;
        $language = isset($row['language']) ? (string) $row['language'] : null;
        $contentSource = isset($row['content_source']) ? (string) $row['content_source'] : null;

        if ($type === '') {
            $type = null;
        }
        if ($status === '') {
            $status = null;
        }
        if ($language === '') {
            $language = null;
        }
        if ($contentSource === '') {
            $contentSource = null;
        }

        return new Page(
            $id,
            $namespace,
            $slug,
            $title,
            $content,
            $type,
            $parentId,
            $sortOrder,
            $status,
            $language,
            $contentSource
        );
    }

    private function getNextSortOrder(string $namespace, ?int $parentId): int {
        if ($parentId === null) {
            $stmt = $this->pdo->prepare(
                'SELECT COALESCE(MAX(sort_order), 0) FROM pages WHERE namespace = ? AND parent_id IS NULL'
            );
            $stmt->execute([$namespace]);
        } else {
            $stmt = $this->pdo->prepare(
                'SELECT COALESCE(MAX(sort_order), 0) FROM pages WHERE namespace = ? AND parent_id = ?'
            );
            $stmt->execute([$namespace, $parentId]);
        }

        return ((int) $stmt->fetchColumn()) + 1;
    }

    private function normalizeNamespace(string $namespace): string {
        return strtolower(trim($namespace));
    }

    private function normalizeNamespaceInput(string $namespace): string
    {
        return strtolower(trim($namespace));
    }

    private function normalizeSlugInput(string $slug): string
    {
        return strtolower(trim($slug));
    }

    private function assertValidNamespace(string $namespace): void
    {
        if ($namespace === '') {
            throw new InvalidArgumentException('Bitte gib einen Namespace an.');
        }

        if (!preg_match(self::SLUG_PATTERN, $namespace)) {
            throw new InvalidArgumentException(
                'Der Namespace darf nur Kleinbuchstaben, Zahlen und Bindestriche enthalten '
                . '(max. 100 Zeichen).'
            );
        }
    }

    private function assertValidSlug(string $slug): void
    {
        if ($slug === '') {
            throw new InvalidArgumentException('Bitte gib einen Slug an.');
        }

        if (!preg_match(self::SLUG_PATTERN, $slug)) {
            throw new InvalidArgumentException(
                'Der Slug darf nur Kleinbuchstaben, Zahlen und Bindestriche enthalten '
                . '(max. 100 Zeichen).'
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadRowsForNamespace(string $namespace): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, namespace, slug, title, content, type, parent_id, sort_order, status, language, content_source '
            . 'FROM pages WHERE namespace = ? ORDER BY parent_id, sort_order, title'
        );
        $stmt->execute([$namespace]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildSubtreeRows(array $rows, int $rootId): array
    {
        $rowsById = [];
        $childrenByParent = [];

        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $parentId = $row['parent_id'];
            if ($parentId !== null) {
                $parentId = (int) $parentId;
            }

            $row['id'] = $id;
            $row['parent_id'] = $parentId;
            $rowsById[$id] = $row;
            $childrenByParent[$parentId ?? 0][] = $id;
        }

        if (!isset($rowsById[$rootId])) {
            return [];
        }

        foreach ($childrenByParent as &$children) {
            usort($children, function (int $leftId, int $rightId) use ($rowsById): int {
                $left = $rowsById[$leftId];
                $right = $rowsById[$rightId];

                $leftOrder = (int) ($left['sort_order'] ?? 0);
                $rightOrder = (int) ($right['sort_order'] ?? 0);
                if ($leftOrder !== $rightOrder) {
                    return $leftOrder <=> $rightOrder;
                }

                $leftTitle = (string) ($left['title'] ?? '');
                $rightTitle = (string) ($right['title'] ?? '');
                $titleCmp = strcasecmp($leftTitle, $rightTitle);
                if ($titleCmp !== 0) {
                    return $titleCmp;
                }

                return $leftId <=> $rightId;
            });
        }
        unset($children);

        $ordered = [];
        $queue = [$rootId];
        while ($queue !== []) {
            $currentId = array_shift($queue);
            if (!isset($rowsById[$currentId])) {
                continue;
            }
            $ordered[] = $rowsById[$currentId];

            foreach ($childrenByParent[$currentId] ?? [] as $childId) {
                $queue[] = $childId;
            }
        }

        return $ordered;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function assertNoSlugConflicts(string $targetNamespace, array $rows): void
    {
        $slugs = [];
        foreach ($rows as $row) {
            $slug = (string) ($row['slug'] ?? '');
            if ($slug === '') {
                continue;
            }
            $slugs[$slug] = true;
        }

        if ($slugs === []) {
            return;
        }

        $slugList = array_keys($slugs);
        $placeholders = implode(',', array_fill(0, count($slugList), '?'));
        $stmt = $this->pdo->prepare(
            sprintf('SELECT slug FROM pages WHERE namespace = ? AND slug IN (%s)', $placeholders)
        );
        $stmt->execute(array_merge([$targetNamespace], $slugList));
        $conflicts = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        if ($conflicts !== []) {
            $conflicts = array_unique(array_map('strval', $conflicts));
            sort($conflicts);
            throw new LogicException(
                sprintf(
                    'Im Ziel-Namespace existieren bereits Seiten mit folgenden Slugs: %s',
                    implode(', ', $conflicts)
                )
            );
        }
    }

    /**
     * @param array<int, array<string, mixed>> $nodes
     */
    private function sortTree(array &$nodes): void {
        usort($nodes, function (array $left, array $right): int {
            $leftOrder = (int) ($left['sort_order'] ?? 0);
            $rightOrder = (int) ($right['sort_order'] ?? 0);
            if ($leftOrder !== $rightOrder) {
                return $leftOrder <=> $rightOrder;
            }

            $leftTitle = (string) ($left['title'] ?? '');
            $rightTitle = (string) ($right['title'] ?? '');
            $titleCmp = strcasecmp($leftTitle, $rightTitle);
            if ($titleCmp !== 0) {
                return $titleCmp;
            }

            return ((int) ($left['id'] ?? 0)) <=> ((int) ($right['id'] ?? 0));
        });

        foreach ($nodes as &$node) {
            if (!empty($node['children']) && is_array($node['children'])) {
                $this->sortTree($node['children']);
            }
        }
        unset($node);
    }
}
