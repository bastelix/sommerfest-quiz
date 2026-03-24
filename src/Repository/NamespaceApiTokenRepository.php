<?php

declare(strict_types=1);

namespace App\Repository;

use App\Infrastructure\Database;
use PDO;
use PDOException;

final class NamespaceApiTokenRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connectFromEnv();
    }

    /**
     * Create a new token record and return the plaintext token (ONLY for first display).
     *
     * @param list<string> $scopes
     * @return array{token:string,id:int}
     */
    public function create(string $namespace, string $label, array $scopes): array
    {
        $token = $this->generateToken();
        $hash = password_hash($token, PASSWORD_DEFAULT);

        $stmt = $this->pdo->prepare(
            'INSERT INTO namespace_api_tokens (namespace, label, token_hash, scopes) VALUES (?, ?, ?, ?::jsonb) RETURNING id'
        );
        $stmt->execute([
            $namespace,
            $label,
            $hash,
            json_encode(array_values($scopes), JSON_THROW_ON_ERROR),
        ]);

        $id = (int) $stmt->fetchColumn();

        return ['token' => $token, 'id' => $id];
    }

    /**
     * Verify a plaintext token and return its namespace + scopes.
     *
     * @return array{namespace:string,scopes:list<string>,tokenId:int}|null
     */
    public function verify(string $token): ?array
    {
        // Fast-path: load only non-revoked tokens.
        $stmt = $this->pdo->query(
            'SELECT id, namespace, token_hash, scopes FROM namespace_api_tokens WHERE revoked_at IS NULL'
        );
        if ($stmt === false) {
            return null;
        }

        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            $hash = (string) ($row['token_hash'] ?? '');
            if ($hash === '') {
                continue;
            }
            if (!password_verify($token, $hash)) {
                continue;
            }

            $scopes = [];
            try {
                $decoded = json_decode((string) ($row['scopes'] ?? '[]'), true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    foreach ($decoded as $s) {
                        if (is_string($s) && $s !== '') {
                            $scopes[] = $s;
                        }
                    }
                }
            } catch (\Throwable) {
                $scopes = [];
            }

            $tokenId = (int) ($row['id'] ?? 0);
            $namespace = (string) ($row['namespace'] ?? '');

            $this->touchUsage($tokenId);

            return [
                'namespace' => $namespace,
                'scopes' => $scopes,
                'tokenId' => $tokenId,
            ];
        }

        return null;
    }

    /**
     * @return list<array{id:int,namespace:string,label:string,scopes:list<string>,created_at:?string,revoked_at:?string,last_used_at:?string}>
     */
    public function listForNamespace(string $namespace): array
    {
        $normalized = trim($namespace);
        if ($normalized === '') {
            return [];
        }

        $stmt = $this->pdo->prepare(
            'SELECT id, namespace, label, scopes, created_at, revoked_at, last_used_at '
            . 'FROM namespace_api_tokens WHERE namespace = ? ORDER BY id DESC'
        );
        $stmt->execute([$normalized]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $out = [];
        foreach ($rows as $row) {
            $scopes = [];
            try {
                $decoded = json_decode((string) ($row['scopes'] ?? '[]'), true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    foreach ($decoded as $s) {
                        if (is_string($s) && $s !== '') {
                            $scopes[] = $s;
                        }
                    }
                }
            } catch (\Throwable) {
                $scopes = [];
            }

            $out[] = [
                'id' => (int) ($row['id'] ?? 0),
                'namespace' => (string) ($row['namespace'] ?? ''),
                'label' => (string) ($row['label'] ?? ''),
                'scopes' => $scopes,
                'created_at' => $row['created_at'] !== null ? (string) $row['created_at'] : null,
                'revoked_at' => $row['revoked_at'] !== null ? (string) $row['revoked_at'] : null,
                'last_used_at' => $row['last_used_at'] !== null ? (string) $row['last_used_at'] : null,
            ];
        }

        return $out;
    }

    public function revoke(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE namespace_api_tokens SET revoked_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM namespace_api_tokens WHERE id = ?');
        $stmt->execute([$id]);
    }

    private function touchUsage(int $id): void
    {
        if ($id <= 0) {
            return;
        }

        try {
            $stmt = $this->pdo->prepare(
                'UPDATE namespace_api_tokens SET last_used_at = CURRENT_TIMESTAMP WHERE id = ?'
            );
            $stmt->execute([$id]);
        } catch (PDOException) {
            // best effort
        }
    }

    private function generateToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
}
