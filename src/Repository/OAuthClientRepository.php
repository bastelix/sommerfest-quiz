<?php

declare(strict_types=1);

namespace App\Repository;

use App\Infrastructure\Database;
use PDO;

final class OAuthClientRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connectFromEnv();
    }

    /**
     * @param list<string> $redirectUris
     * @return array{clientId: string, clientSecret: string}
     */
    public function create(string $name, string $namespace, array $redirectUris, string $scope): array
    {
        $clientId = $this->generateId();
        $clientSecret = $this->generateSecret();
        $hash = password_hash($clientSecret, PASSWORD_DEFAULT);

        $stmt = $this->pdo->prepare(
            'INSERT INTO oauth_clients (id, secret_hash, name, redirect_uris, scope, namespace) VALUES (?, ?, ?, ?::jsonb, ?, ?)'
        );
        $stmt->execute([
            $clientId,
            $hash,
            $name,
            json_encode(array_values($redirectUris), JSON_THROW_ON_ERROR),
            $scope,
            $namespace,
        ]);

        return ['clientId' => $clientId, 'clientSecret' => $clientSecret];
    }

    /**
     * @return array{id: string, name: string, secret_hash: string, redirect_uris: list<string>, scope: string, namespace: string}|null
     */
    public function findById(string $clientId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, name, secret_hash, redirect_uris, scope, namespace FROM oauth_clients WHERE id = ?');
        $stmt->execute([$clientId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        $uris = [];
        try {
            $decoded = json_decode((string) ($row['redirect_uris'] ?? '[]'), true, 512, JSON_THROW_ON_ERROR);
            if (is_array($decoded)) {
                $uris = array_values(array_filter($decoded, 'is_string'));
            }
        } catch (\Throwable) {
            $uris = [];
        }

        return [
            'id' => (string) $row['id'],
            'name' => (string) ($row['name'] ?? ''),
            'secret_hash' => (string) ($row['secret_hash'] ?? ''),
            'redirect_uris' => $uris,
            'scope' => (string) ($row['scope'] ?? ''),
            'namespace' => (string) ($row['namespace'] ?? ''),
        ];
    }

    public function verifySecret(string $clientId, string $clientSecret): bool
    {
        $client = $this->findById($clientId);
        if ($client === null) {
            return false;
        }

        return password_verify($clientSecret, $client['secret_hash']);
    }

    private function generateId(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0x0fff) | 0x4000,
            random_int(0, 0x3fff) | 0x8000,
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0xffff)
        );
    }

    private function generateSecret(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
}
