<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Page;
use App\Infrastructure\Database;
use PDO;

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
        $stmt = $this->pdo->prepare('SELECT content FROM pages WHERE slug = ?');
        $stmt->execute([$slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? (string) ($row['content'] ?? '') : null;
    }

    public function save(string $slug, string $content): void
    {
        $stmt = $this->pdo->prepare('UPDATE pages SET content = ?, updated_at = CURRENT_TIMESTAMP WHERE slug = ?');
        $stmt->execute([$content, $slug]);
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
}
