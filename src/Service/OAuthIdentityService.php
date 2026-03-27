<?php

declare(strict_types=1);

namespace App\Service;

use PDO;

class OAuthIdentityService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @return array{id:int,account_id:int,provider:string,provider_user_id:string,provider_email:?string,provider_data:?string,created_at:string}|null
     */
    public function findByProvider(string $provider, string $providerUserId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM oauth_identities WHERE provider = ? AND provider_user_id = ?'
        );
        $stmt->execute([$provider, $providerUserId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    /**
     * @param array<string, mixed>|null $providerData
     */
    public function create(
        int $accountId,
        string $provider,
        string $providerUserId,
        ?string $providerEmail = null,
        ?array $providerData = null
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO oauth_identities (account_id, provider, provider_user_id, provider_email, provider_data)
             VALUES (?, ?, ?, ?, ?)
             RETURNING id'
        );
        $stmt->execute([
            $accountId,
            $provider,
            $providerUserId,
            $providerEmail,
            $providerData !== null ? json_encode($providerData, JSON_THROW_ON_ERROR) : null,
        ]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return list<array{id:int,account_id:int,provider:string,provider_user_id:string,provider_email:?string,provider_data:?string,created_at:string}>
     */
    public function findByAccountId(int $accountId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM oauth_identities WHERE account_id = ? ORDER BY created_at'
        );
        $stmt->execute([$accountId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
