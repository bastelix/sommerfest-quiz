<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\DuplicateUsernameBlocklistException;
use App\Support\UsernameGuard;
use DateTimeImmutable;
use PDO;
use PDOException;
use RuntimeException;
use InvalidArgumentException;
use function array_key_exists;
use function array_map;
use function mb_strlen;
use function mb_strtolower;
use function trim;
use function is_array;
use function is_string;
use function sprintf;

/**
 * Manage username blocklist entries created via the admin UI.
 */
class UsernameBlocklistService
{
    public const ADMIN_CATEGORY = 'Admin';

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @return list<array{id:int,term:string,category:string,created_at:DateTimeImmutable}>
     */
    public function getAdminEntries(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, term, category, created_at
             FROM username_blocklist
             WHERE category = ?
             ORDER BY LOWER(term)'
        );
        $stmt->execute([self::ADMIN_CATEGORY]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(fn (array $row): array => $this->hydrateRow($row), $rows);
    }

    /**
     * @return array{id:int,term:string,category:string,created_at:DateTimeImmutable}
     */
    public function add(string $term): array
    {
        $normalized = mb_strtolower(trim($term));
        if ($normalized === '' || mb_strlen($normalized) < 3) {
            throw new InvalidArgumentException('Term must be at least three characters long.');
        }

        $existing = $this->findByTerm($normalized);
        if ($existing !== null) {
            throw DuplicateUsernameBlocklistException::forTerm($normalized);
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO username_blocklist (term, category) VALUES (?, ?)'
        );

        try {
            $insert->execute([$normalized, self::ADMIN_CATEGORY]);
        } catch (PDOException $exception) {
            throw new RuntimeException('Failed to insert username blocklist entry.', 0, $exception);
        }

        $entry = $this->findByTerm($normalized);
        if ($entry === null) {
            throw new RuntimeException('Failed to fetch inserted username blocklist entry.');
        }

        return $entry;
    }

    /**
     * @param list<array<string,mixed>|mixed> $rows
     */
    public function importEntries(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $categoryMap = [];
        foreach (UsernameGuard::DATABASE_CATEGORIES as $category) {
            $categoryMap[mb_strtolower($category)] = $category;
        }

        $normalized = [];
        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException(sprintf('Row %d must be an array with term and category.', $index));
            }

            if (!array_key_exists('term', $row) || !array_key_exists('category', $row)) {
                throw new InvalidArgumentException(sprintf('Row %d must contain "term" and "category" keys.', $index));
            }

            $termRaw = $row['term'];
            if (!is_string($termRaw)) {
                throw new InvalidArgumentException(sprintf('Row %d has an invalid term value.', $index));
            }

            $term = mb_strtolower(trim($termRaw));
            if ($term === '' || mb_strlen($term) < 3) {
                throw new InvalidArgumentException(sprintf('Row %d must contain a term with at least three characters.', $index));
            }

            $categoryRaw = $row['category'];
            if (!is_string($categoryRaw)) {
                throw new InvalidArgumentException(sprintf('Row %d has an invalid category value.', $index));
            }

            $categoryKey = mb_strtolower(trim($categoryRaw));
            if ($categoryKey === '' || !array_key_exists($categoryKey, $categoryMap)) {
                throw new InvalidArgumentException(sprintf('Row %d references an unknown category "%s".', $index, trim((string) $categoryRaw)));
            }

            $category = $categoryMap[$categoryKey];
            $normalized[$category][$term] = $term;
        }

        $entries = [];
        foreach ($normalized as $category => $terms) {
            foreach ($terms as $term) {
                $entries[] = ['term' => $term, 'category' => $category];
            }
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO username_blocklist (term, category) VALUES (?, ?) ON CONFLICT DO NOTHING'
        );

        if ($statement === false) {
            throw new RuntimeException('Failed to prepare username blocklist import statement.');
        }

        $startedTransaction = !$this->pdo->inTransaction();
        if ($startedTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            foreach ($entries as $entry) {
                $statement->execute([$entry['term'], $entry['category']]);
            }

            if ($startedTransaction) {
                $this->pdo->commit();
            }
        } catch (PDOException $exception) {
            if ($startedTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw new RuntimeException('Failed to import username blocklist entries.', 0, $exception);
        }
    }

    public function remove(int $id): ?array
    {
        $entry = $this->findById($id);
        if ($entry === null || $entry['category'] !== self::ADMIN_CATEGORY) {
            return null;
        }

        $delete = $this->pdo->prepare('DELETE FROM username_blocklist WHERE id = ?');
        $delete->execute([$id]);

        return $entry;
    }

    private function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, term, category, created_at FROM username_blocklist WHERE id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $this->hydrateRow($row);
    }

    private function findByTerm(string $term): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, term, category, created_at
             FROM username_blocklist
             WHERE LOWER(term) = LOWER(?) AND category = ?'
        );
        $stmt->execute([$term, self::ADMIN_CATEGORY]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $this->hydrateRow($row);
    }

    /**
     * @param array{id:mixed,term:mixed,category:mixed,created_at:mixed} $row
     * @return array{id:int,term:string,category:string,created_at:DateTimeImmutable}
     */
    private function hydrateRow(array $row): array
    {
        $createdRaw = (string) ($row['created_at'] ?? '');
        try {
            $createdAt = new DateTimeImmutable($createdRaw ?: 'now');
        } catch (\Exception) {
            $createdAt = new DateTimeImmutable('now');
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'term' => (string) ($row['term'] ?? ''),
            'category' => (string) ($row['category'] ?? ''),
            'created_at' => $createdAt,
        ];
    }
}
