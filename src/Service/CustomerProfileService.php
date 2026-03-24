<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\CustomerProfile;
use App\Infrastructure\Database;
use DateTimeImmutable;
use PDO;
use RuntimeException;

final class CustomerProfileService
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connectFromEnv();
    }

    public function getByUserId(int $userId): ?CustomerProfile
    {
        $stmt = $this->pdo->prepare('SELECT * FROM customer_profiles WHERE user_id = ?');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function upsert(
        int $userId,
        ?string $displayName,
        ?string $company,
        ?string $phone,
        ?string $avatarUrl,
    ): CustomerProfile {
        $existing = $this->getByUserId($userId);

        if ($existing === null) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO customer_profiles (user_id, display_name, company, phone, avatar_url) '
                . 'VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$userId, $displayName, $company, $phone, $avatarUrl]);
        } else {
            $stmt = $this->pdo->prepare(
                'UPDATE customer_profiles SET display_name = ?, company = ?, '
                . 'phone = ?, avatar_url = ?, updated_at = CURRENT_TIMESTAMP '
                . 'WHERE user_id = ?'
            );
            $stmt->execute([$displayName, $company, $phone, $avatarUrl, $userId]);
        }

        return $this->getByUserId($userId) ?? throw new RuntimeException('Failed to retrieve customer profile.');
    }

    public function delete(int $userId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM customer_profiles WHERE user_id = ?');
        $stmt->execute([$userId]);
    }

    private function hydrate(array $row): CustomerProfile
    {
        return new CustomerProfile(
            (int) $row['id'],
            (int) $row['user_id'],
            isset($row['display_name']) && $row['display_name'] !== '' ? (string) $row['display_name'] : null,
            isset($row['company']) && $row['company'] !== '' ? (string) $row['company'] : null,
            isset($row['phone']) && $row['phone'] !== '' ? (string) $row['phone'] : null,
            isset($row['avatar_url']) && $row['avatar_url'] !== '' ? (string) $row['avatar_url'] : null,
            new DateTimeImmutable($row['created_at']),
            new DateTimeImmutable($row['updated_at']),
        );
    }
}
