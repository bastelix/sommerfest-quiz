<?php

declare(strict_types=1);

namespace App\Service\RagChat;

use App\Infrastructure\Database;
use App\Support\DomainNameHelper;
use InvalidArgumentException;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * Manage the mapping between domains and marketing wiki articles for AI indexing.
 */
final class DomainWikiSelectionService
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connectFromEnv();
    }

    /**
     * @return int[]
     */
    public function getSelectedArticleIds(string $domain): array
    {
        $normalized = $this->normalizeDomain($domain);

        $stmt = $this->pdo->prepare('SELECT article_id FROM domain_chat_wiki_articles WHERE domain = ? ORDER BY article_id');
        $stmt->execute([$normalized]);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        return array_map(static fn ($value): int => (int) $value, $rows);
    }

    /**
     * @param list<int|string> $articleIds
     */
    public function replaceSelection(string $domain, array $articleIds): void
    {
        $normalized = $this->normalizeDomain($domain);
        $filtered = [];

        foreach ($articleIds as $id) {
            $candidate = $this->normalizeArticleId($id);

            if ($candidate <= 0) {
                throw new InvalidArgumentException('Article identifiers must be positive integers.');
            }

            $filtered[] = $candidate;
        }

        $unique = array_values(array_unique($filtered));

        try {
            $this->pdo->beginTransaction();

            $delete = $this->pdo->prepare('DELETE FROM domain_chat_wiki_articles WHERE domain = ?');
            $delete->execute([$normalized]);

            if ($unique !== []) {
                $insert = $this->pdo->prepare('INSERT INTO domain_chat_wiki_articles (domain, article_id) VALUES (?, ?)');
                foreach ($unique as $articleId) {
                    $insert->execute([$normalized, $articleId]);
                }
            }

            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            $message = $exception instanceof PDOException ? 'Failed to update wiki selection.' : $exception->getMessage();
            throw new RuntimeException($message, 0, $exception);
        }
    }

    public function clearSelection(string $domain): void
    {
        $normalized = $this->normalizeDomain($domain);

        $stmt = $this->pdo->prepare('DELETE FROM domain_chat_wiki_articles WHERE domain = ?');
        $stmt->execute([$normalized]);
    }

    private function normalizeDomain(string $domain): string
    {
        $normalized = DomainNameHelper::canonicalizeSlug($domain);
        if ($normalized === '') {
            throw new InvalidArgumentException('Invalid domain supplied.');
        }

        return $normalized;
    }

    /**
     * @param int|string $value
     */
    private function normalizeArticleId($value): int
    {
        if (is_int($value)) {
            return $value;
        }

        $numeric = trim((string) $value);
        if ($numeric === '' || !ctype_digit($numeric)) {
            throw new InvalidArgumentException('Invalid article identifier provided.');
        }

        return (int) $numeric;
    }
}
