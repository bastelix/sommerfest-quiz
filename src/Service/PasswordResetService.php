<?php

declare(strict_types=1);

namespace App\Service;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

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

    private LoggerInterface $logger;

    private string $secret;

    public function __construct(
        PDO $pdo,
        int $ttlSeconds = 3600,
        ?string $secret = null,
        ?LoggerInterface $logger = null
    ) {
        $this->pdo = $pdo;
        $this->ttl = $ttlSeconds;
        $this->secret = $secret ?? (string) getenv('PASSWORD_RESET_SECRET');
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Generate and store a new token for the given user id.
     */
    public function createToken(int $userId): string {
        $this->cleanupExpired();

        $stmt = $this->pdo->prepare('DELETE FROM password_resets WHERE user_id=?');
        $stmt->execute([$userId]);

        $token = bin2hex(random_bytes(16));
        $hash = hash_hmac('sha256', $token, $this->secret);
        $expires = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->modify('+' . $this->ttl . ' seconds')
            ->format('Y-m-d H:i:sP');

        $stmt = $this->pdo->prepare(
            'INSERT INTO password_resets(user_id, token_hash, expires_at) VALUES(?,?,?)'
        );
        $stmt->execute([$userId, $hash, $expires]);

        $this->logger->info('Password reset token created', ['userId' => $userId]);

        return $token;
    }

    /**
     * Verify token and return user id if valid.
     *
     * The token is removed regardless of validity.
     */
    public function consumeToken(string $token): ?int {
        $this->cleanupExpired();

        $hash = hash_hmac('sha256', $token, $this->secret);
        $stmt = $this->pdo->prepare('SELECT user_id, expires_at FROM password_resets WHERE token_hash=?');
        $stmt->execute([$hash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            $this->logger->warning('Password reset token not found', ['token' => $token]);
            return null;
        }

        $userId = (int) $row['user_id'];
        $expires = new DateTimeImmutable((string) $row['expires_at']);

        $this->deleteToken($userId, $token);

        if ($expires < new DateTimeImmutable('now', new DateTimeZone('UTC'))) {
            $this->logger->warning('Password reset token expired', ['userId' => $userId]);
            return null;
        }

        $this->logger->info('Password reset token consumed', ['userId' => $userId]);

        return $userId;
    }

    /**
     * Remove a single token.
     */
    public function deleteToken(int $userId, string $token): void {
        $hash = hash_hmac('sha256', $token, $this->secret);
        $stmt = $this->pdo->prepare('DELETE FROM password_resets WHERE user_id=? AND token_hash=?');
        $stmt->execute([$userId, $hash]);
    }

    /**
     * Remove expired tokens.
     */
    public function cleanupExpired(): void {
        $stmt = $this->pdo->prepare('DELETE FROM password_resets WHERE expires_at <= ?');
        $stmt->execute([
            (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:sP')
        ]);
    }
}
