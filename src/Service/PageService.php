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
        $normalizedNamespace = strtolower(trim($namespace));
        if ($normalizedNamespace === '') {
            throw new InvalidArgumentException('Bitte gib einen Namespace an.');
        }

        if (!preg_match('/^[a-z0-9][a-z0-9\-]{0,99}$/', $normalizedNamespace)) {
            throw new InvalidArgumentException(
                'Der Namespace darf nur Kleinbuchstaben, Zahlen und Bindestriche enthalten '
                . '(max. 100 Zeichen).'
            );
        }

        $normalizedSlug = strtolower(trim($slug));
        if ($normalizedSlug === '') {
            throw new InvalidArgumentException('Bitte gib einen Slug an.');
        }

        if (!preg_match('/^[a-z0-9][a-z0-9\-]{0,99}$/', $normalizedSlug)) {
            throw new InvalidArgumentException(
                'Der Slug darf nur Kleinbuchstaben, Zahlen und Bindestriche enthalten '
                . '(max. 100 Zeichen).'
            );
        }

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

    public function copyToNamespace(string $sourceNamespace, string $slug, string $targetNamespace): Page {
        $page = $this->findByKey($sourceNamespace, $slug);
        if ($page === null) {
            throw new InvalidArgumentException('Die Seite wurde nicht gefunden.');
        }

        $normalizedTarget = $this->assertValidNamespace($targetNamespace);
        if ($normalizedTarget === $page->getNamespace()) {
            throw new LogicException('Ziel- und Quell-Namespace dürfen nicht identisch sein.');
        }

        if ($this->findByKey($normalizedTarget, $page->getSlug()) !== null) {
            throw new LogicException(
                sprintf(
                    'Eine Seite mit dem Namespace "%s" und dem Slug "%s" existiert bereits.',
                    $normalizedTarget,
                    $page->getSlug()
                )
            );
        }

        $sortOrder = $this->getNextSortOrder($normalizedTarget, null);
        $stmt = $this->pdo->prepare(
            'INSERT INTO pages (namespace, slug, title, content, type, parent_id, sort_order, status, language, content_source) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        try {
            $stmt->execute([
                $normalizedTarget,
                $page->getSlug(),
                $page->getTitle(),
                $page->getContent(),
                $page->getType(),
                null,
                $sortOrder,
                $page->getStatus(),
                $page->getLanguage(),
                $page->getContentSource(),
            ]);
        } catch (PDOException $exception) {
            throw new RuntimeException('Die Seite konnte nicht kopiert werden.', 0, $exception);
        }

        return $this->loadCreatedPage($normalizedTarget, $page->getSlug());
    }

    public function moveToNamespace(string $sourceNamespace, string $slug, string $targetNamespace): Page {
        $page = $this->findByKey($sourceNamespace, $slug);
        if ($page === null) {
            throw new InvalidArgumentException('Die Seite wurde nicht gefunden.');
        }

        $normalizedTarget = $this->assertValidNamespace($targetNamespace);
        if ($normalizedTarget === $page->getNamespace()) {
            throw new LogicException('Ziel- und Quell-Namespace dürfen nicht identisch sein.');
        }

        if ($this->findByKey($normalizedTarget, $page->getSlug()) !== null) {
            throw new LogicException(
                sprintf(
                    'Eine Seite mit dem Namespace "%s" und dem Slug "%s" existiert bereits.',
                    $normalizedTarget,
                    $page->getSlug()
                )
            );
        }

        $sortOrder = $this->getNextSortOrder($normalizedTarget, null);
        $stmt = $this->pdo->prepare(
            'UPDATE pages SET namespace = ?, parent_id = NULL, sort_order = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?'
        );

        try {
            $stmt->execute([$normalizedTarget, $sortOrder, $page->getId()]);
        } catch (PDOException $exception) {
            throw new RuntimeException('Die Seite konnte nicht verschoben werden.', 0, $exception);
        }

        $updated = $this->findById($page->getId());
        if ($updated === null) {
            throw new RuntimeException('Die Seite konnte nach dem Verschieben nicht geladen werden.');
        }

        return $updated;
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

    private function assertValidNamespace(string $namespace): string {
        $normalizedNamespace = strtolower(trim($namespace));
        if ($normalizedNamespace === '') {
            throw new InvalidArgumentException('Bitte gib einen Namespace an.');
        }

        if (!preg_match('/^[a-z0-9][a-z0-9\-]{0,99}$/', $normalizedNamespace)) {
            throw new InvalidArgumentException(
                'Der Namespace darf nur Kleinbuchstaben, Zahlen und Bindestriche enthalten '
                . '(max. 100 Zeichen).'
            );
        }

        return $normalizedNamespace;
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
