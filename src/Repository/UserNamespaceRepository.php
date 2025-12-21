<?php

declare(strict_types=1);

namespace App\Repository;

use App\Service\PageService;
use PDO;

use function trim;

final class UserNamespaceRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @return list<array{namespace:string,is_default:bool}>
     */
    public function loadForUser(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $stmt = $this->pdo->prepare(
            'SELECT namespace, is_default FROM user_namespaces WHERE user_id = ? ORDER BY is_default DESC, namespace'
        );
        $stmt->execute([$userId]);

        $namespaces = [];

        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            $namespace = trim((string) ($row['namespace'] ?? ''));
            if ($namespace === '') {
                continue;
            }

            $namespaces[] = [
                'namespace' => $namespace,
                'is_default' => (bool) ($row['is_default'] ?? false),
            ];
        }

        $stmt->closeCursor();

        return $namespaces;
    }

    public function ensureDefaultNamespace(int $userId, string $namespace = 'default'): void
    {
        if ($userId <= 0) {
            return;
        }

        $namespace = trim($namespace);
        if ($namespace === '') {
            $namespace = 'default';
        }

        $clear = $this->pdo->prepare(
            'UPDATE user_namespaces SET is_default = FALSE WHERE user_id = ? AND is_default = TRUE'
        );
        $clear->execute([$userId]);
        $clear->closeCursor();

        $stmt = $this->pdo->prepare(
            'INSERT INTO user_namespaces (user_id, namespace, is_default) VALUES (?, ?, TRUE)
                ON CONFLICT (user_id, namespace) DO UPDATE SET is_default = EXCLUDED.is_default'
        );
        $stmt->execute([$userId, $namespace]);
        $stmt->closeCursor();
    }

    /**
     * @return list<string>
     */
    public function getKnownNamespaces(): array
    {
        $stmt = $this->pdo->query('SELECT DISTINCT namespace FROM user_namespaces ORDER BY namespace');

        $namespaces = [];
        while (($value = $stmt->fetchColumn()) !== false) {
            $namespace = trim((string) $value);
            if ($namespace === '') {
                continue;
            }
            $namespaces[] = $namespace;
        }
        $stmt->closeCursor();

        if (!in_array(PageService::DEFAULT_NAMESPACE, $namespaces, true)) {
            $namespaces[] = PageService::DEFAULT_NAMESPACE;
        }

        sort($namespaces);

        return $namespaces;
    }

    /**
     * @param list<string> $namespaces
     */
    public function replaceForUser(int $userId, array $namespaces, ?string $defaultNamespace = null): void
    {
        if ($userId <= 0) {
            return;
        }

        $normalized = [];
        foreach ($namespaces as $namespace) {
            if (!is_string($namespace)) {
                continue;
            }
            $value = trim(strtolower($namespace));
            if ($value === '') {
                continue;
            }
            if (!in_array($value, $normalized, true)) {
                $normalized[] = $value;
            }
        }

        if ($normalized === []) {
            $normalized = [PageService::DEFAULT_NAMESPACE];
        }

        $default = $defaultNamespace !== null ? trim(strtolower($defaultNamespace)) : null;
        if ($default === '') {
            $default = null;
        }
        if ($default !== null && !in_array($default, $normalized, true)) {
            $default = $normalized[0] ?? null;
        }

        $delete = $this->pdo->prepare('DELETE FROM user_namespaces WHERE user_id = ?');
        $delete->execute([$userId]);
        $delete->closeCursor();

        $insert = $this->pdo->prepare(
            'INSERT INTO user_namespaces (user_id, namespace, is_default) VALUES (?, ?, ?)'
        );
        foreach ($normalized as $namespace) {
            $insert->execute([$userId, $namespace, $default !== null && $namespace === $default]);
        }
        $insert->closeCursor();
    }
}
