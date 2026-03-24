<?php

declare(strict_types=1);

namespace App\Repository;

use App\Infrastructure\Database;
use PDO;

final class OAuthAccessTokenRepository
{
    private const DEFAULT_EXPIRES_IN = 3600;

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connectFromEnv();
    }

    /**
     * @param list<string> $scopes
     * @return array{token: string, id: int, expiresIn: int}
     */
    public function create(
        string $clientId,
        string $namespace,
        array $scopes,
        int $expiresInSeconds = self::DEFAULT_EXPIRES_IN
    ): array
    {
        $token = $this->generateToken();
        $hash = password_hash($token, PASSWORD_DEFAULT);

        $stmt = $this->pdo->prepare(
            'INSERT INTO oauth_access_tokens (token_hash, client_id, namespace, scopes, expires_at) '
            . "VALUES (?, ?, ?, ?::jsonb, CURRENT_TIMESTAMP + ? * INTERVAL '1 second') RETURNING id"
        );
        $stmt->execute([
            $hash,
            $clientId,
            $namespace,
            json_encode(array_values($scopes), JSON_THROW_ON_ERROR),
            $expiresInSeconds,
        ]);

        $id = (int) $stmt->fetchColumn();

        return ['token' => $token, 'id' => $id, 'expiresIn' => $expiresInSeconds];
    }

    /**
     * Verify an access token and return its metadata.
     *
     * @return array{namespace: string, scopes: list<string>, clientId: string, tokenId: int}|null
     */
    public function verify(string $token): ?array
    {
        $stmt = $this->pdo->query(
            'SELECT id, token_hash, client_id, namespace, scopes FROM oauth_access_tokens '
            . 'WHERE revoked_at IS NULL AND expires_at > CURRENT_TIMESTAMP'
        );
        if ($stmt === false) {
            return null;
        }

        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            $hash = (string) ($row['token_hash'] ?? '');
            if ($hash === '' || !password_verify($token, $hash)) {
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

            return [
                'namespace' => (string) ($row['namespace'] ?? ''),
                'scopes' => $scopes,
                'clientId' => (string) ($row['client_id'] ?? ''),
                'tokenId' => $tokenId,
            ];
        }

        return null;
    }

    public function revoke(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE oauth_access_tokens SET revoked_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([$id]);
    }

    private function generateToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
}
