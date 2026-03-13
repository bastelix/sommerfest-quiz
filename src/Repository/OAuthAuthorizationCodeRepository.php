<?php

declare(strict_types=1);

namespace App\Repository;

use App\Infrastructure\Database;
use PDO;

final class OAuthAuthorizationCodeRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connectFromEnv();
    }

    /**
     * @param list<string> $scopes
     */
    public function create(
        string $code,
        string $clientId,
        string $namespace,
        array $scopes,
        string $redirectUri,
        ?string $codeChallenge
    ): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO oauth_authorization_codes (code, client_id, namespace, scopes, redirect_uri, code_challenge, expires_at) '
            . "VALUES (?, ?, ?, ?::jsonb, ?, ?, CURRENT_TIMESTAMP + INTERVAL '10 minutes')"
        );
        $stmt->execute([
            $code,
            $clientId,
            $namespace,
            json_encode(array_values($scopes), JSON_THROW_ON_ERROR),
            $redirectUri,
            $codeChallenge,
        ]);
    }

    /**
     * Consume an authorization code (single-use). Returns null if invalid, expired, or already used.
     *
     * @return array{client_id: string, namespace: string, scopes: list<string>, redirect_uri: string, code_challenge: ?string}|null
     */
    public function consume(string $code): ?array
    {
        $stmt = $this->pdo->prepare(
            'UPDATE oauth_authorization_codes SET used_at = CURRENT_TIMESTAMP '
            . 'WHERE code = ? AND used_at IS NULL AND expires_at > CURRENT_TIMESTAMP '
            . 'RETURNING client_id, namespace, scopes, redirect_uri, code_challenge'
        );
        $stmt->execute([$code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
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

        return [
            'client_id' => (string) $row['client_id'],
            'namespace' => (string) $row['namespace'],
            'scopes' => $scopes,
            'redirect_uri' => (string) $row['redirect_uri'],
            'code_challenge' => $row['code_challenge'] !== null ? (string) $row['code_challenge'] : null,
        ];
    }

    public function cleanup(): void
    {
        $this->pdo->exec('DELETE FROM oauth_authorization_codes WHERE expires_at < CURRENT_TIMESTAMP');
    }

    public static function generateCode(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
}
