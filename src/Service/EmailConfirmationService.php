<?php

declare(strict_types=1);

namespace App\Service;

use DateTimeImmutable;
use PDO;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Manage email confirmation tokens for double opt-in.
 */
class EmailConfirmationService
{
    private PDO $pdo;
    private int $ttl;
    private LoggerInterface $logger;

    public function __construct(PDO $pdo, int $ttlSeconds = 86400, ?LoggerInterface $logger = null) {
        $this->pdo = $pdo;
        $this->ttl = $ttlSeconds;
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Generate token for email and store it.
     */
    public function createToken(string $email): string {
        $this->cleanupExpired();

        $token = bin2hex(random_bytes(16));
        $expires = (new DateTimeImmutable())
            ->modify('+' . $this->ttl . ' seconds')
            ->format('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare('DELETE FROM email_confirmations WHERE email = ?');
        $stmt->execute([$email]);

        $stmt = $this->pdo->prepare(
            'INSERT INTO email_confirmations(email, token, confirmed, expires_at) VALUES(?,?,0,?)'
        );
        $stmt->execute([$email, $token, $expires]);

        $this->logger->info('Email confirmation token created', ['email' => $email]);

        return $token;
    }

    /**
     * Verify token and mark email as confirmed.
     */
    public function confirmToken(string $token): ?string {
        $this->cleanupExpired();

        $stmt = $this->pdo->prepare('SELECT email, expires_at FROM email_confirmations WHERE token = ?');
        $stmt->execute([$token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            $this->logger->warning('Email confirmation token not found', ['token' => $token]);
            return null;
        }

        $email = (string) $row['email'];
        $expires = new DateTimeImmutable((string) $row['expires_at']);

        if ($expires < new DateTimeImmutable()) {
            $this->logger->warning('Email confirmation token expired', ['email' => $email]);
            $this->deleteToken($email);
            return null;
        }

        $stmt = $this->pdo->prepare('UPDATE email_confirmations SET confirmed = 1 WHERE email = ?');
        $stmt->execute([$email]);

        $this->deleteToken($email);

        $this->logger->info('Email confirmed', ['email' => $email]);

        return $email;
    }

    /**
     * Check whether given email is confirmed.
     */
    public function isConfirmed(string $email): bool {
        $this->cleanupExpired();

        $stmt = $this->pdo->prepare('SELECT confirmed FROM email_confirmations WHERE email = ?');
        $stmt->execute([$email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false && (int) $row['confirmed'] === 1;
    }

    private function deleteToken(string $email): void {
        $stmt = $this->pdo->prepare('DELETE FROM email_confirmations WHERE email = ?');
        $stmt->execute([$email]);
    }

    /**
     * Remove expired tokens.
     */
    public function cleanupExpired(): void {
        $stmt = $this->pdo->prepare('DELETE FROM email_confirmations WHERE expires_at <= ?');
        $stmt->execute([(new DateTimeImmutable())->format('Y-m-d H:i:s')]);
    }
}
