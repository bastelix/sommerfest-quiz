<?php

declare(strict_types=1);

namespace App\Service;

use DateTimeImmutable;
use DateTimeInterface;
use PDO;
use RuntimeException;

final class CertificateZoneRegistry
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ISSUED = 'issued';
    public const STATUS_ERROR = 'error';

    public const CERT_VALIDITY_DAYS = 90;
    public const RENEWAL_THRESHOLD_DAYS = 30;

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @return list<array{zone:string,provider:string,wildcard_enabled:bool,status:string,last_issued_at:?string,last_error:?string,next_renewal_after:?string}>
     */
    public function listWildcardEnabled(): array
    {
        $stmt = $this->pdo->query(
            'SELECT zone, provider, wildcard_enabled, status, last_issued_at, last_error, next_renewal_after
             FROM certificate_zones WHERE wildcard_enabled = TRUE ORDER BY zone ASC'
        );
        $rows = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $zones = [];
        foreach ($rows as $row) {
            $zones[] = [
                'zone' => strtolower(trim((string) ($row['zone'] ?? ''))),
                'provider' => (string) ($row['provider'] ?? ''),
                'wildcard_enabled' => (bool) ($row['wildcard_enabled'] ?? false),
                'status' => (string) ($row['status'] ?? self::STATUS_PENDING),
                'last_issued_at' => $row['last_issued_at'] !== null ? (string) $row['last_issued_at'] : null,
                'last_error' => $row['last_error'] !== null ? (string) $row['last_error'] : null,
                'next_renewal_after' => $row['next_renewal_after'] !== null ? (string) $row['next_renewal_after'] : null,
            ];
        }

        return $zones;
    }

    /**
     * Ensure a certificate zone entry exists.
     */
    public function ensureZone(string $zone, string $provider = 'hetzner', bool $wildcardEnabled = true): void
    {
        $normalized = strtolower(trim($zone));
        if ($normalized === '') {
            throw new RuntimeException('Zone cannot be empty.');
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO certificate_zones (zone, provider, wildcard_enabled, status)
             VALUES (?, ?, ?, ?) ON CONFLICT(zone) DO UPDATE SET provider = EXCLUDED.provider'
        );
        $stmt->execute([$normalized, $provider, $wildcardEnabled, self::STATUS_PENDING]);
    }

    public function markIssued(string $zone, ?DateTimeImmutable $issuedAt = null): void
    {
        $issuedAt ??= new DateTimeImmutable();
        $this->updateStatus(
            $zone,
            self::STATUS_ISSUED,
            null,
            $issuedAt,
            $this->calculateNextRenewalWindow($issuedAt)
        );
    }

    public function markError(string $zone, string $message): void
    {
        $this->updateStatus($zone, self::STATUS_ERROR, $message, null);
    }

    public function markPending(string $zone, ?string $message = null, ?DateTimeImmutable $nextRenewalAfter = null): void
    {
        $this->updateStatus($zone, self::STATUS_PENDING, $message, null, $nextRenewalAfter);
    }

    /**
     * Ensure certificate zones exist for all active domains.
     */
    public function backfillActiveDomains(string $provider = 'hetzner', bool $wildcardEnabled = true): void
    {
        $stmt = $this->pdo->query('SELECT DISTINCT zone FROM domains WHERE is_active = TRUE');
        if ($stmt === false) {
            return;
        }

        $zones = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        foreach ($zones as $zone) {
            $normalized = strtolower(trim((string) $zone));
            if ($normalized === '') {
                continue;
            }

            $this->ensureZone($normalized, $provider, $wildcardEnabled);
        }
    }

    public function calculateNextRenewalWindow(?DateTimeImmutable $lastIssuedAt): ?DateTimeImmutable
    {
        if ($lastIssuedAt === null) {
            return null;
        }

        return $lastIssuedAt->modify(
            sprintf('+%d days', self::CERT_VALIDITY_DAYS - self::RENEWAL_THRESHOLD_DAYS)
        );
    }

    public function isRenewalEligible(?DateTimeImmutable $lastIssuedAt, ?DateTimeImmutable $now = null): bool
    {
        if ($lastIssuedAt === null) {
            return true;
        }

        $now ??= new DateTimeImmutable();
        $nextWindow = $this->calculateNextRenewalWindow($lastIssuedAt);

        return $nextWindow === null || $now >= $nextWindow;
    }

    private function updateStatus(
        string $zone,
        string $status,
        ?string $error,
        ?DateTimeImmutable $issuedAt,
        ?DateTimeImmutable $nextRenewalAfter = null
    ): void
    {
        $normalized = strtolower(trim($zone));
        if ($normalized === '') {
            throw new RuntimeException('Zone cannot be empty.');
        }

        $timestamp = $issuedAt?->format(DateTimeInterface::ATOM);
        $nextRenewalTimestamp = $nextRenewalAfter?->format(DateTimeInterface::ATOM);

        $stmt = $this->pdo->prepare(
            'UPDATE certificate_zones SET status = ?, last_error = ?, last_issued_at = COALESCE(?, last_issued_at),
             next_renewal_after = COALESCE(?, next_renewal_after) WHERE zone = ?'
        );
        $stmt->execute([$status, $error, $timestamp, $nextRenewalTimestamp, $normalized]);
    }
}
