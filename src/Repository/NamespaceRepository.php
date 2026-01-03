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

    public function getConnection(): PDO
    {
        return $this->pdo;
    }

    /**
     * @return list<array{namespace:string,label:?string,is_active:bool,created_at:?string,updated_at:?string}>
     */
    public function list(): array
    {
        $this->assertTableExists();

        $stmt = $this->pdo->query(
            'SELECT namespace, label, is_active, created_at, updated_at FROM namespaces ORDER BY namespace'
        );

        $rows = [];
        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            $namespace = trim((string) ($row['namespace'] ?? ''));
            if ($namespace === '') {
                continue;
            }
            $rows[] = [
                'namespace' => $namespace,
                'label' => $row['label'] !== null ? (string) $row['label'] : null,
                'is_active' => (bool) ($row['is_active'] ?? false),
                'created_at' => $row['created_at'] ?? null,
                'updated_at' => $row['updated_at'] ?? null,
            ];
        }
        $stmt->closeCursor();

        return $rows;
    }

    /**
     * @return list<string>
     */
    public function listKnownNamespaces(): array
    {
        $sources = [
            'pages' => 'namespace',
            'namespace_profile' => 'namespace',
            'marketing_newsletter_configs' => 'namespace',
            'newsletter_campaigns' => 'namespace',
            'user_namespaces' => 'namespace',
        ];

        $namespaces = [];
        foreach ($sources as $table => $column) {
            if (!$this->hasTable($table)) {
                continue;
            }

            $stmt = $this->pdo->query(sprintf('SELECT DISTINCT %s AS namespace FROM %s', $column, $table));
            while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
                $namespace = trim((string) ($row['namespace'] ?? ''));
                if ($namespace === '') {
                    continue;
                }
                $namespaces[] = $namespace;
            }
            $stmt->closeCursor();
        }

        return $namespaces;
    }

    public function exists(string $namespace): bool
    {
        $this->assertTableExists();

        $stmt = $this->pdo->prepare('SELECT 1 FROM namespaces WHERE namespace = ?');
        $stmt->execute([$namespace]);
        $exists = $stmt->fetchColumn() !== false;
        $stmt->closeCursor();

        return $exists;
    }

    /**
     * @return array{namespace:string,label:?string,is_active:bool,created_at:?string,updated_at:?string}|null
     */
    public function find(string $namespace): ?array
    {
        $this->assertTableExists();

        $stmt = $this->pdo->prepare(
            'SELECT namespace, label, is_active, created_at, updated_at FROM namespaces WHERE namespace = ?'
        );
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
            'label' => $row['label'] !== null ? (string) $row['label'] : null,
            'is_active' => (bool) ($row['is_active'] ?? false),
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    public function create(string $namespace, ?string $label = null, bool $isActive = true): void
    {
        $this->assertTableExists();

        $stmt = $this->pdo->prepare('INSERT INTO namespaces (namespace, label, is_active) VALUES (?, ?, ?)');
        $stmt->execute([$namespace, $label, $isActive]);
        $stmt->closeCursor();
    }

    public function update(
        string $namespace,
        string $newNamespace,
        ?string $label = null,
        ?bool $isActive = null,
        bool $updateLabel = false
    ): void {
        $this->assertTableExists();

        $fields = [];
        $params = [];
        if ($newNamespace !== $namespace) {
            $fields[] = 'namespace = ?';
            $params[] = $newNamespace;
        }
        if ($updateLabel) {
            $fields[] = 'label = ?';
            $params[] = $label;
        }
        if ($isActive !== null) {
            $fields[] = 'is_active = ?';
            $params[] = $isActive;
        }

        if ($fields === []) {
            return;
        }

        $params[] = $namespace;
        $sql = 'UPDATE namespaces SET ' . implode(', ', $fields) . ' WHERE namespace = ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $stmt->closeCursor();
    }

    public function delete(string $namespace): void
    {
        $this->assertTableExists();

        $stmt = $this->pdo->prepare('DELETE FROM namespaces WHERE namespace = ?');
        $stmt->execute([$namespace]);
        $stmt->closeCursor();
    }

    /**
     * @return list<string>
     */
    public function findUsage(string $namespace): array
    {
        $usage = [];

        if ($this->hasTable('pages') && $this->hasNamespaceReference('pages', 'namespace', $namespace)) {
            $usage[] = 'pages';
        }
        if (
            $this->hasTable('namespace_profile')
            && $this->hasNamespaceReference('namespace_profile', 'namespace', $namespace)
        ) {
            $usage[] = 'namespace_profile';
        }
        if (
            $this->hasTable('marketing_newsletter_configs')
            && $this->hasNamespaceReference('marketing_newsletter_configs', 'namespace', $namespace)
        ) {
            $usage[] = 'marketing_newsletter_configs';
        }
        if (
            $this->hasTable('newsletter_campaigns')
            && $this->hasNamespaceReference('newsletter_campaigns', 'namespace', $namespace)
        ) {
            $usage[] = 'newsletter_campaigns';
        }
        if ($this->hasTable('user_namespaces') && $this->hasNamespaceReference('user_namespaces', 'namespace', $namespace)) {
            $usage[] = 'user_namespaces';
        }

        return $usage;
    }

    public function deactivate(string $namespace): void
    {
        $this->assertTableExists();

        $stmt = $this->pdo->prepare('UPDATE namespaces SET is_active = FALSE WHERE namespace = ?');
        $stmt->execute([$namespace]);
        $stmt->closeCursor();
    }

    private function assertTableExists(): void
    {
        if (!$this->hasTable('namespaces')) {
            throw new RuntimeException('Namespaces table is not available.');
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

    private function hasNamespaceReference(string $table, string $column, string $namespace): bool
    {
        $stmt = $this->pdo->prepare(sprintf('SELECT 1 FROM %s WHERE %s = ? LIMIT 1', $table, $column));
        $stmt->execute([$namespace]);
        $exists = $stmt->fetchColumn() !== false;
        $stmt->closeCursor();

        return $exists;
    }
}
