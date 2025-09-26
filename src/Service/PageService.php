<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Page;
use App\Infrastructure\Database;
use InvalidArgumentException;
use PDO;
use PDOException;
use RuntimeException;

/**
 * Simple service for loading and saving static pages from the database.
 */
class PageService
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connectFromEnv();
    }

    public function get(string $slug): ?string
    {
        $page = $this->findBySlug($slug);
        return $page?->getContent();
    }

    public function save(string $slug, string $content): void
    {
        $stmt = $this->pdo->prepare('UPDATE pages SET content = ?, updated_at = CURRENT_TIMESTAMP WHERE slug = ?');
        $stmt->execute([$content, $slug]);
    }

    public function create(string $slug, string $title, string $content): Page
    {
        $normalizedSlug = strtolower(trim($slug));
        if ($normalizedSlug === '') {
            throw new InvalidArgumentException('Slug darf nicht leer sein.');
        }
        if (!preg_match('/^[a-z0-9][a-z0-9\-]*$/', $normalizedSlug)) {
            throw new InvalidArgumentException('Slug darf nur Kleinbuchstaben, Zahlen und Bindestriche enthalten.');
        }
        if (strlen($normalizedSlug) > 100) {
            throw new InvalidArgumentException('Slug darf maximal 100 Zeichen lang sein.');
        }

        $normalizedTitle = trim($title);
        if ($normalizedTitle === '') {
            throw new InvalidArgumentException('Titel darf nicht leer sein.');
        }
        if (mb_strlen($normalizedTitle) > 150) {
            throw new InvalidArgumentException('Titel darf maximal 150 Zeichen lang sein.');
        }

        $page = $this->findBySlug($normalizedSlug);
        if ($page !== null) {
            throw new RuntimeException('Eine Seite mit diesem Slug existiert bereits.');
        }

        $contentValue = (string) $content;

        try {
            $stmt = $this->pdo->prepare('INSERT INTO pages (slug, title, content) VALUES (?, ?, ?)');
            $stmt->execute([$normalizedSlug, $normalizedTitle, $contentValue]);
        } catch (PDOException $e) {
            if (stripos($e->getMessage(), 'unique') !== false) {
                throw new RuntimeException('Eine Seite mit diesem Slug existiert bereits.', 0, $e);
            }

            throw new RuntimeException('Seite konnte nicht erstellt werden.', 0, $e);
        }

        $id = (int) $this->pdo->lastInsertId();
        if ($id <= 0) {
            $created = $this->findBySlug($normalizedSlug);
            if ($created !== null) {
                return $created;
            }

            throw new RuntimeException('Seite konnte nach dem Speichern nicht geladen werden.');
        }

        return new Page($id, $normalizedSlug, $normalizedTitle, $contentValue);
    }

    /**
     * Fetch all stored pages.
     *
     * @return Page[]
     */
    public function getAll(): array
    {
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

    public function findById(int $id): ?Page
    {
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

    public function findBySlug(string $slug): ?Page
    {
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
}
