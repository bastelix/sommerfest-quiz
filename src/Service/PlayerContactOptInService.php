<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\PlayerNameConflictException;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use PDO;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Handle double opt-in workflow for player contact details.
 */
class PlayerContactOptInService
{
    private PDO $pdo;

    private PlayerService $playerService;

    private int $ttl;

    private LoggerInterface $logger;

    public function __construct(
        PDO $pdo,
        PlayerService $playerService,
        int $ttlSeconds = 86400,
        ?LoggerInterface $logger = null
    ) {
        $this->pdo = $pdo;
        $this->playerService = $playerService;
        $this->ttl = $ttlSeconds;
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Start the opt-in process by persisting a token for the given player.
     *
     * @return array{token:string,event_uid:string,player_uid:string,player_name:string,email:string}
     */
    public function createRequest(
        string $eventUid,
        string $playerUid,
        string $playerName,
        string $email,
        ?string $requestIp = null
    ): array {
        $eventUid = trim($eventUid);
        $playerUid = trim($playerUid);
        $email = $this->normalizeEmail($email);

        if ($eventUid === '' || $playerUid === '' || $email === null) {
            throw new InvalidArgumentException('Missing required information for contact opt-in.');
        }

        $resolvedName = $this->resolvePlayerName($eventUid, $playerUid, $playerName);
        if ($resolvedName === null) {
            throw new InvalidArgumentException('Player name is required for contact opt-in.');
        }

        try {
            $this->playerService->save($eventUid, $resolvedName, $playerUid);
        } catch (PlayerNameConflictException $exception) {
            throw new InvalidArgumentException('Player name already in use.', 0, $exception);
        }

        $storedName = $this->playerService->findName($eventUid, $playerUid) ?? $resolvedName;

        $token = $this->generateToken();
        $tokenHash = hash('sha256', $token);
        $now = new DateTimeImmutable();
        $expiresAt = $now->add(new DateInterval('PT' . $this->ttl . 'S'));

        $this->deleteExistingRequests($eventUid, $playerUid);

        $stmt = $this->pdo->prepare(
            'INSERT INTO player_contact_optins '
            . '(event_uid, player_uid, player_name, email, token_hash, request_ip, created_at, expires_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $eventUid,
            $playerUid,
            $storedName,
            $email,
            $tokenHash,
            $requestIp !== null ? trim($requestIp) : null,
            $now->format(DateTimeInterface::ATOM),
            $expiresAt->format(DateTimeInterface::ATOM),
        ]);

        $this->logger->info('Player contact opt-in requested', [
            'event_uid' => $eventUid,
            'player_uid' => $playerUid,
        ]);

        return [
            'token' => $token,
            'event_uid' => $eventUid,
            'player_uid' => $playerUid,
            'player_name' => $storedName,
            'email' => $email,
        ];
    }

    /**
     * Confirm the opt-in token and persist the contact information.
     *
     * @return array{status:string,event_uid?:string,player_uid?:string,player_name?:string,email?:string}
     */
    public function confirm(string $token, ?string $confirmationIp = null): array
    {
        $token = trim($token);
        if ($token === '') {
            return ['status' => 'invalid'];
        }

        $tokenHash = hash('sha256', $token);
        $stmt = $this->pdo->prepare(
            'SELECT id, event_uid, player_uid, player_name, email, expires_at, consumed_at '
            . 'FROM player_contact_optins WHERE token_hash = ?'
        );
        $stmt->execute([$tokenHash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return ['status' => 'not_found'];
        }

        $expiresAt = $this->createDateTime($row['expires_at'] ?? null);
        $consumedAt = $this->createDateTime($row['consumed_at'] ?? null);
        $now = new DateTimeImmutable();

        if ($consumedAt instanceof DateTimeImmutable) {
            return ['status' => 'consumed'];
        }

        if ($expiresAt instanceof DateTimeImmutable && $expiresAt < $now) {
            $this->deleteById((int) $row['id']);
            $this->logger->info('Player contact opt-in token expired', [
                'event_uid' => $row['event_uid'],
                'player_uid' => $row['player_uid'],
            ]);

            return ['status' => 'expired'];
        }

        $eventUid = (string) $row['event_uid'];
        $playerUid = (string) $row['player_uid'];
        $playerName = (string) $row['player_name'];
        $email = (string) $row['email'];

        try {
            $this->playerService->save(
                $eventUid,
                $playerName,
                $playerUid,
                $email,
                $now,
                true
            );
        } catch (PlayerNameConflictException $exception) {
            $this->logger->error('Failed to confirm contact opt-in because of name conflict', [
                'event_uid' => $eventUid,
                'player_uid' => $playerUid,
            ]);

            return ['status' => 'conflict'];
        }

        $update = $this->pdo->prepare(
            'UPDATE player_contact_optins SET consumed_at = ?, confirmation_ip = ? WHERE id = ?'
        );
        $update->execute([
            $now->format(DateTimeInterface::ATOM),
            $confirmationIp !== null ? trim($confirmationIp) : null,
            (int) $row['id'],
        ]);

        $storedName = $this->playerService->findName($eventUid, $playerUid) ?? $playerName;

        $this->logger->info('Player contact opt-in confirmed', [
            'event_uid' => $eventUid,
            'player_uid' => $playerUid,
        ]);

        return [
            'status' => 'success',
            'event_uid' => $eventUid,
            'player_uid' => $playerUid,
            'player_name' => $storedName,
            'email' => $email,
        ];
    }

    /**
     * Remove persisted contact information for the given player.
     */
    public function remove(string $eventUid, string $playerUid): bool
    {
        $eventUid = trim($eventUid);
        $playerUid = trim($playerUid);
        if ($eventUid === '' || $playerUid === '') {
            return false;
        }

        $player = $this->playerService->find($eventUid, $playerUid);
        if ($player === null) {
            return false;
        }

        $playerName = (string) $player['player_name'];
        if ($playerName === '') {
            return false;
        }

        try {
            $this->playerService->save($eventUid, $playerName, $playerUid, null, null, true);
        } catch (PlayerNameConflictException $exception) {
            return false;
        }

        $this->deleteExistingRequests($eventUid, $playerUid);

        $this->logger->info('Player contact removed', [
            'event_uid' => $eventUid,
            'player_uid' => $playerUid,
        ]);

        return true;
    }

    private function deleteExistingRequests(string $eventUid, string $playerUid): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM player_contact_optins WHERE event_uid = ? AND player_uid = ?');
        $stmt->execute([$eventUid, $playerUid]);
    }

    private function deleteById(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM player_contact_optins WHERE id = ?');
        $stmt->execute([$id]);
    }

    private function normalizeEmail(string $email): ?string
    {
        $email = trim($email);
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        return mb_strtolower($email);
    }

    private function resolvePlayerName(string $eventUid, string $playerUid, string $playerName): ?string
    {
        $candidate = trim($playerName);
        if ($candidate !== '') {
            return $candidate;
        }

        $existing = $this->playerService->findName($eventUid, $playerUid);
        if ($existing !== null && trim($existing) !== '') {
            return $existing;
        }

        return null;
    }

    private function generateToken(): string
    {
        $bytes = random_bytes(32);
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    private function createDateTime(mixed $value): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        try {
            return new DateTimeImmutable((string) $value);
        } catch (\Exception $exception) {
            $this->logger->warning('Failed to parse player contact timestamp', ['value' => $value]);
            return null;
        }
    }
}
