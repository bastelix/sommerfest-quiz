<?php

declare(strict_types=1);

namespace App\Service;

use DateTimeImmutable;
use PDO;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Manage invitation tokens.
 */
class InvitationService
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
     * Generate and store a token for the given email.
     */
    public function createToken(string $email): string {
        $this->cleanupExpired();

        $token = bin2hex(random_bytes(16));
        $expires = (new DateTimeImmutable())
            ->modify('+' . $this->ttl . ' seconds')
            ->format('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare('INSERT INTO invitations(email, token, expires_at) VALUES(?,?,?)');
        $stmt->execute([$email, $token, $expires]);

        $this->logger->info('Invitation token created', ['email' => $email]);

        return $token;
    }

    /**
     * Verify token and return associated email. Token is removed.
     */
    public function consumeToken(string $token): ?string {
        $this->cleanupExpired();

        $stmt = $this->pdo->prepare('SELECT email, expires_at FROM invitations WHERE token = ?');
        $stmt->execute([$token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            $this->logger->warning('Invitation token not found', ['token' => $token]);
            return null;
        }

        $email = (string) $row['email'];
        $expires = new DateTimeImmutable((string) $row['expires_at']);

        $this->deleteToken($email);

        if ($expires < new DateTimeImmutable()) {
            $this->logger->warning('Invitation token expired', ['email' => $email]);
            return null;
        }

        $this->logger->info('Invitation token consumed', ['email' => $email]);

        return $email;
    }

    private function deleteToken(string $email): void {
        $stmt = $this->pdo->prepare('DELETE FROM invitations WHERE email = ?');
        $stmt->execute([$email]);
    }

    /**
     * Remove expired tokens.
     */
    public function cleanupExpired(): void {
        $stmt = $this->pdo->prepare('DELETE FROM invitations WHERE expires_at <= ?');
        $stmt->execute([(new DateTimeImmutable())->format('Y-m-d H:i:s')]);
    }
}
