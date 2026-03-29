<?php

declare(strict_types=1);

namespace App\Service;

use PDO;

class AppSubscriptionService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @return array{id:int,account_id:int,app:string,stripe_subscription_id:?string,plan:?string,status:string,valid_until:?string,created_at:string}|null
     */
    public function findByAccountAndApp(int $accountId, string $app): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM app_subscriptions WHERE account_id = ? AND app = ?'
        );
        $stmt->execute([$accountId, $app]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    public function create(
        int $accountId,
        string $app,
        ?string $stripeSubscriptionId = null,
        ?string $plan = null,
        string $status = 'active'
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO app_subscriptions (account_id, app, stripe_subscription_id, plan, status)
             VALUES (?, ?, ?, ?, ?)
             RETURNING id'
        );
        $stmt->execute([$accountId, $app, $stripeSubscriptionId, $plan, $status]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return list<array{id:int,account_id:int,app:string,stripe_subscription_id:?string,plan:?string,status:string,valid_until:?string,created_at:string}>
     */
    public function findByAccountId(int $accountId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM app_subscriptions WHERE account_id = ? ORDER BY created_at DESC'
        );
        $stmt->execute([$accountId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateByStripeSubscriptionId(string $subscriptionId, array $data): void
    {
        $allowed = ['status', 'plan', 'valid_until'];
        $sets = [];
        $values = [];
        foreach ($data as $key => $value) {
            if (in_array($key, $allowed, true)) {
                $sets[] = "$key = ?";
                $values[] = $value;
            }
        }
        if ($sets === []) {
            return;
        }
        $values[] = $subscriptionId;
        $sql = 'UPDATE app_subscriptions SET ' . implode(', ', $sets) . ' WHERE stripe_subscription_id = ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($values);
    }
}
