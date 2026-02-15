<?php

declare(strict_types=1);

namespace App\Service;

use App\Support\TokenCipher;
use PDO;

/**
 * Manages dashboard share tokens (generation, storage, verification).
 */
class DashboardTokenService
{
    private PDO $pdo;
    private TokenCipher $tokenCipher;

    public function __construct(PDO $pdo, TokenCipher $tokenCipher)
    {
        $this->pdo = $pdo;
        $this->tokenCipher = $tokenCipher;
    }

    /**
     * Create a new random dashboard token consisting of URL-safe characters.
     */
    public function generate(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
    }

    /**
     * Persist a dashboard token for the given event.
     */
    public function set(string $eventUid, string $variant, ?string $token): void
    {
        if ($eventUid === '') {
            return;
        }
        $column = $variant === 'sponsor' ? 'dashboard_sponsor_token' : 'dashboard_share_token';
        $sql = "UPDATE config SET {$column} = :token WHERE event_uid = :uid";
        $stmt = $this->pdo->prepare($sql);
        $normalized = $token !== null ? trim($token) : '';
        if ($normalized === '') {
            $stmt->bindValue(':token', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':token', $this->tokenCipher->encrypt($normalized));
        }
        $stmt->bindValue(':uid', $eventUid);
        $stmt->execute();
    }

    /**
     * Retrieve decrypted dashboard tokens for the event.
     *
     * @return array{public:?string,sponsor:?string}
     */
    public function getTokens(string $eventUid): array
    {
        if ($eventUid === '') {
            return ['public' => null, 'sponsor' => null];
        }
        $stmt = $this->pdo->prepare(
            'SELECT dashboard_share_token, dashboard_sponsor_token FROM config WHERE event_uid = ? LIMIT 1'
        );
        $stmt->execute([$eventUid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $public = $row['dashboard_share_token'] ?? null;
        $sponsor = $row['dashboard_sponsor_token'] ?? null;

        return [
            'public' => $this->resolve(is_string($public) ? $public : null),
            'sponsor' => $this->resolve(is_string($sponsor) ? $sponsor : null),
        ];
    }

    /**
     * Validate a share token for the specified event and return the matched variant.
     */
    public function verify(string $eventUid, string $token, ?string $variant = null): ?string
    {
        $token = trim($token);
        if ($eventUid === '' || $token === '') {
            return null;
        }
        $tokens = $this->getTokens($eventUid);
        if (
            ($variant === null || $variant === 'public')
            && $tokens['public'] !== null
            && hash_equals($tokens['public'], $token)
        ) {
            return 'public';
        }
        if (
            ($variant === null || $variant === 'sponsor')
            && $tokens['sponsor'] !== null
            && hash_equals($tokens['sponsor'], $token)
        ) {
            return 'sponsor';
        }

        return null;
    }

    /**
     * Decrypt a stored dashboard token while supporting legacy plaintext values.
     */
    private function resolve(?string $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $plain = $this->tokenCipher->decrypt($trimmed);
        if (is_string($plain) && $plain !== '') {
            return $plain;
        }

        if (preg_match('/^[A-Za-z0-9_-]{16,}$/', $trimmed) === 1) {
            return $trimmed;
        }

        return null;
    }
}
