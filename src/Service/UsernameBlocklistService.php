<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\DuplicateUsernameBlocklistException;
use DateTimeImmutable;
use PDO;
use PDOException;
use RuntimeException;
use InvalidArgumentException;
use function array_map;
use function is_array;
use function mb_strlen;
use function mb_strtolower;
use function trim;

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

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($rows)) {
            return [];
        }

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
