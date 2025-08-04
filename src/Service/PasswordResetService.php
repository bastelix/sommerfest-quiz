<?php

declare(strict_types=1);

namespace App\Service;

use DateTimeImmutable;
use PDO;

/**
 * Manage password reset tokens.
 */
class PasswordResetService
{
    private PDO $pdo;

    /**
     * Token lifetime in seconds.
     */
    private int $ttl;

    public function __construct(PDO $pdo, int $ttlSeconds = 3600)
    {
        $this->pdo = $pdo;
        $this->ttl = $ttlSeconds;
    }

    /**
     * Generate and store a new token for the given user id.
     */
    public function createToken(int $userId): string
    {
        $this->cleanupExpired();

        $token = bin2hex(random_bytes(16));
        $expires = (new DateTimeImmutable())
            ->modify('+' . $this->ttl . ' seconds')
            ->format('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare(
            'INSERT INTO password_resets(user_id, token, expires_at) VALUES(?,?,?)'
        );
        $stmt->execute([$userId, $token, $expires]);

        return $token;
    }

    /**
     * Verify token and return user id if valid.
     *
     * The token is removed regardless of validity.
     */
    public function consumeToken(string $token): ?int
    {
        $this->cleanupExpired();

        $stmt = $this->pdo->prepare('SELECT user_id, expires_at FROM password_resets WHERE token=?');
        $stmt->execute([$token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        $userId = (int) $row['user_id'];
        $expires = new DateTimeImmutable((string) $row['expires_at']);

        $this->deleteToken($userId, $token);

        if ($expires < new DateTimeImmutable()) {
            return null;
        }

        return $userId;
    }

    /**
     * Remove a single token.
     */
    public function deleteToken(int $userId, string $token): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM password_resets WHERE user_id=? AND token=?');
        $stmt->execute([$userId, $token]);
    }

    /**
     * Remove expired tokens.
     */
    public function cleanupExpired(): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM password_resets WHERE expires_at <= ?');
        $stmt->execute([(new DateTimeImmutable())->format('Y-m-d H:i:s')]);
    }
}

