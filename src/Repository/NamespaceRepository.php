<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;
use RuntimeException;

final class NamespaceRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @return list<array{namespace:string,created_at:?string}>
     */
    public function all(): array
    {
        $this->assertTableExists();

        $stmt = $this->pdo->query(
            'SELECT namespace, created_at FROM namespace_profile ORDER BY namespace'
        );

        $rows = [];
        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            $namespace = trim((string) ($row['namespace'] ?? ''));
            if ($namespace === '') {
                continue;
            }
            $rows[] = [
                'namespace' => $namespace,
                'created_at' => $row['created_at'] ?? null,
            ];
        }
        $stmt->closeCursor();

        return $rows;
    }

    public function exists(string $namespace): bool
    {
        $this->assertTableExists();

        $stmt = $this->pdo->prepare('SELECT 1 FROM namespace_profile WHERE namespace = ?');
        $stmt->execute([$namespace]);
        $exists = $stmt->fetchColumn() !== false;
        $stmt->closeCursor();

        return $exists;
    }

    /**
     * @return array{namespace:string,created_at:?string}|null
     */
    public function find(string $namespace): ?array
    {
        $this->assertTableExists();

        $stmt = $this->pdo->prepare('SELECT namespace, created_at FROM namespace_profile WHERE namespace = ?');
        $stmt->execute([$namespace]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if ($row === false) {
            return null;
        }

        $name = trim((string) ($row['namespace'] ?? ''));
        if ($name === '') {
            return null;
        }

        return [
            'namespace' => $name,
            'created_at' => $row['created_at'] ?? null,
        ];
    }

    public function insert(string $namespace): void
    {
        $this->assertTableExists();

        $stmt = $this->pdo->prepare('INSERT INTO namespace_profile (namespace) VALUES (?)');
        $stmt->execute([$namespace]);
        $stmt->closeCursor();
    }

    public function rename(string $namespace, string $newNamespace): void
    {
        $this->assertTableExists();

        $stmt = $this->pdo->prepare('UPDATE namespace_profile SET namespace = ? WHERE namespace = ?');
        $stmt->execute([$newNamespace, $namespace]);
        $stmt->closeCursor();
    }

    public function delete(string $namespace): void
    {
        $this->assertTableExists();

        $stmt = $this->pdo->prepare('DELETE FROM namespace_profile WHERE namespace = ?');
        $stmt->execute([$namespace]);
        $stmt->closeCursor();
    }

    private function assertTableExists(): void
    {
        if (!$this->hasTable('namespace_profile')) {
            throw new RuntimeException('Namespace profile table is not available.');
        }
    }

    private function hasTable(string $name): bool
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $stmt = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name = ?");
            $stmt->execute([$name]);
            return $stmt->fetchColumn() !== false;
        }

        $stmt = $this->pdo->prepare('SELECT to_regclass(?)');
        $stmt->execute([$name]);
        return $stmt->fetchColumn() !== null;
    }
}
