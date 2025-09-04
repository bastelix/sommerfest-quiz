<?php

declare(strict_types=1);

namespace App\Service;

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
}
