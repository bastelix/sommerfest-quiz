<?php

declare(strict_types=1);

namespace App\Repository;

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
}
