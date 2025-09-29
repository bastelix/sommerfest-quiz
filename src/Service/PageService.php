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
    private PDO $pdo;

    public function __construct(?PDO $pdo = null) {
        $this->pdo = $pdo ?? Database::connectFromEnv();
    }

    public function get(string $slug): ?string {
        $page = $this->findBySlug($slug);
        return $page?->getContent();
    }

    public function save(string $slug, string $content): void {
        $stmt = $this->pdo->prepare('UPDATE pages SET content = ?, updated_at = CURRENT_TIMESTAMP WHERE slug = ?');
        $stmt->execute([$content, $slug]);
    }

    public function delete(string $slug): void {
        $normalized = trim($slug);
        if ($normalized === '') {
            return;
        }

        $stmt = $this->pdo->prepare('DELETE FROM pages WHERE slug = ?');
        $stmt->execute([$normalized]);
    }

    public function create(string $slug, string $title, string $content): Page {
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

        if ($this->findBySlug($normalizedSlug) !== null) {
            throw new LogicException(sprintf('Eine Seite mit dem Slug "%s" existiert bereits.', $normalizedSlug));
        }

        $html = (string) $content;

        $stmt = $this->pdo->prepare('INSERT INTO pages (slug, title, content) VALUES (?, ?, ?)');

        try {
            $stmt->execute([$normalizedSlug, $normalizedTitle, $html]);
        } catch (PDOException $exception) {
            throw new RuntimeException('Die Seite konnte nicht angelegt werden.', 0, $exception);
        }

        return $this->loadCreatedPage($normalizedSlug);
    }

    /**
     * Fetch all stored pages.
     *
     * @return Page[]
     */
    public function getAll(): array {
        $stmt = $this->pdo->query('SELECT id, slug, title, content FROM pages ORDER BY title');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $pages = [];
        foreach ($rows as $row) {
            $id = isset($row['id']) ? (int) $row['id'] : 0;
            $slug = isset($row['slug']) ? (string) $row['slug'] : '';
            $title = isset($row['title']) ? (string) $row['title'] : '';
            $content = isset($row['content']) ? (string) $row['content'] : '';

            if ($id <= 0 || $slug === '' || $title === '') {
                continue;
            }

            $pages[] = new Page($id, $slug, $title, $content);
        }

        return $pages;
    }

    public function findById(int $id): ?Page {
        if ($id <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT id, slug, title, content FROM pages WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        $slug = isset($row['slug']) ? (string) $row['slug'] : '';
        $title = isset($row['title']) ? (string) $row['title'] : '';
        if ($slug === '' || $title === '') {
            return null;
        }

        return new Page((int) $row['id'], $slug, $title, (string) ($row['content'] ?? ''));
    }

    public function findBySlug(string $slug): ?Page {
        $normalized = trim($slug);
        if ($normalized === '') {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT id, slug, title, content FROM pages WHERE slug = ?');
        $stmt->execute([$normalized]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        $pageSlug = isset($row['slug']) ? (string) $row['slug'] : '';
        $title = isset($row['title']) ? (string) $row['title'] : '';
        if ($pageSlug === '' || $title === '') {
            return null;
        }

        return new Page((int) $row['id'], $pageSlug, $title, (string) ($row['content'] ?? ''));
    }

    private function loadCreatedPage(string $slug): Page {
        $page = $this->findBySlug($slug);
        if ($page === null) {
            throw new RuntimeException('Die neu angelegte Seite konnte nicht geladen werden.');
        }

        return $page;
    }
}
