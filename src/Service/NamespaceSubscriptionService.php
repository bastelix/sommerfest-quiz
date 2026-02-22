<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Plan;
use PDO;
use PDOException;

/**
 * Manages subscriptions at the namespace level.
 *
 * Each namespace (slug in namespace_projects) can have its own plan,
 * Stripe customer, and Stripe subscription. This replaces the tenant-level
 * subscription handling for all namespace-scoped operations.
 */
final class NamespaceSubscriptionService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Find a namespace project by its slug.
     *
     * @return array{
     *   id:string,
     *   slug:string,
     *   plan:string,
     *   stripe_customer_id:?string,
     *   stripe_sub_id:?string,
     *   stripe_price_id:?string,
     *   stripe_status:?string,
     *   stripe_current_period_end:?string,
     *   stripe_cancel_at_period_end:bool,
     *   display_name:string,
     *   status:string
     * }|null
     */
    public function findBySlug(string $slug): ?array
    {
        $slug = strtolower(trim($slug));
        if ($slug === '') {
            return null;
        }

        try {
            $driver = $this->driverName();
        } catch (PDOException) {
            $driver = 'unknown';
        }

        $sql = $driver === 'sqlite'
            ? 'SELECT * FROM namespace_projects WHERE slug = :slug'
            : 'SELECT * FROM namespace_projects WHERE slug = :slug';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':slug' => $slug]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException) {
            return null;
        }

        if ($row === false) {
            return null;
        }

        return $this->normalizeRow($row);
    }

    /**
     * Find or create a namespace project for the given slug.
     *
     * @return array The namespace project row
     */
    public function findOrCreate(string $slug, ?int $ownerUserId = null): array
    {
        $existing = $this->findBySlug($slug);
        if ($existing !== null) {
            return $existing;
        }

        $driver = $this->driverName();
        $id = $driver === 'sqlite' ? bin2hex(random_bytes(16)) : null;

        if ($driver === 'sqlite') {
            $stmt = $this->pdo->prepare(
                'INSERT INTO namespace_projects (id, slug, owner_user_id, display_name, plan) '
                . 'VALUES (:id, :slug, :owner, :name, :plan)'
            );
            $stmt->execute([
                ':id' => $id,
                ':slug' => strtolower(trim($slug)),
                ':owner' => $ownerUserId ?? 0,
                ':name' => $slug,
                ':plan' => Plan::FREE->value,
            ]);
        } else {
            $stmt = $this->pdo->prepare(
                'INSERT INTO namespace_projects (slug, owner_user_id, display_name, plan) '
                . 'VALUES (:slug, :owner, :name, :plan) '
                . 'ON CONFLICT (slug) DO NOTHING'
            );
            $stmt->execute([
                ':slug' => strtolower(trim($slug)),
                ':owner' => $ownerUserId ?? 0,
                ':name' => $slug,
                ':plan' => Plan::FREE->value,
            ]);
        }

        return $this->findBySlug($slug) ?? [
            'id' => $id ?? '',
            'slug' => strtolower(trim($slug)),
            'plan' => Plan::FREE->value,
            'stripe_customer_id' => null,
            'stripe_sub_id' => null,
            'stripe_price_id' => null,
            'stripe_status' => null,
            'stripe_current_period_end' => null,
            'stripe_cancel_at_period_end' => false,
            'display_name' => $slug,
            'status' => 'active',
        ];
    }

    /**
     * Get the plan for a namespace.
     */
    public function getPlan(string $slug): string
    {
        $project = $this->findBySlug($slug);
        return $project['plan'] ?? Plan::FREE->value;
    }

    /**
     * Get the effective limits for a namespace based on its plan.
     *
     * @return array<string,int>
     */
    public function getLimits(string $slug): array
    {
        $plan = $this->getPlan($slug);
        $planEnum = Plan::tryFrom($plan);

        $enumLimits = $planEnum !== null ? $planEnum->limits() : Plan::FREE->limits();

        // Check plan_limits table for overrides
        try {
            $stmt = $this->pdo->prepare(
                'SELECT metric, max_value FROM plan_limits WHERE plan = :plan'
            );
            $stmt->execute([':plan' => $plan]);
            $overrides = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (PDOException) {
            $overrides = [];
        }

        return array_merge($enumLimits, $overrides);
    }

    /**
     * Update the plan for a namespace project.
     */
    public function updatePlan(string $slug, string $plan): void
    {
        $planEnum = Plan::tryFrom($plan);
        if ($planEnum === null) {
            throw new \InvalidArgumentException('invalid-plan');
        }

        $project = $this->findOrCreate($slug);
        $driver = $this->driverName();

        if ($driver === 'sqlite') {
            $stmt = $this->pdo->prepare(
                'UPDATE namespace_projects SET plan = :plan WHERE id = :id'
            );
            $stmt->execute([':plan' => $plan, ':id' => $project['id']]);
        } else {
            $stmt = $this->pdo->prepare(
                'UPDATE namespace_projects SET plan = :plan, updated_at = now() WHERE id = :id::uuid'
            );
            $stmt->execute([':plan' => $plan, ':id' => $project['id']]);
        }
    }

    /**
     * Update Stripe-related fields for a namespace project.
     *
     * @param array<string,mixed> $stripeData
     */
    public function updateStripeInfo(string $slug, array $stripeData): void
    {
        $project = $this->findOrCreate($slug);
        $driver = $this->driverName();

        $sets = [];
        $params = [':id' => $project['id']];

        $allowedFields = [
            'stripe_customer_id',
            'stripe_sub_id',
            'stripe_price_id',
            'stripe_status',
            'stripe_current_period_end',
            'stripe_cancel_at_period_end',
        ];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $stripeData)) {
                $paramKey = ':' . $field;
                $sets[] = "$field = $paramKey";
                $params[$paramKey] = $stripeData[$field];
            }
        }

        if ($sets === []) {
            return;
        }

        $setClause = implode(', ', $sets);

        if ($driver === 'sqlite') {
            $sql = "UPDATE namespace_projects SET $setClause WHERE id = :id";
        } else {
            $sql = "UPDATE namespace_projects SET $setClause, updated_at = now() WHERE id = :id::uuid";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    /**
     * Get Stripe subscription status for a namespace (for the subscription page).
     *
     * @return array{
     *   plan:?string,
     *   amount:int,
     *   currency:string,
     *   subscription_status:?string,
     *   next_payment:?string,
     *   cancel_at_period_end:bool
     * }
     */
    public function getSubscriptionStatus(string $slug): array
    {
        $project = $this->findBySlug($slug);

        if ($project === null) {
            return [
                'plan' => Plan::FREE->value,
                'amount' => 0,
                'currency' => 'eur',
                'subscription_status' => null,
                'next_payment' => null,
                'cancel_at_period_end' => false,
            ];
        }

        $customerId = $project['stripe_customer_id'] ?? '';

        if ($customerId === '' || !StripeService::isConfigured()['ok']) {
            return [
                'plan' => $project['plan'],
                'amount' => 0,
                'currency' => 'eur',
                'subscription_status' => null,
                'next_payment' => null,
                'cancel_at_period_end' => false,
            ];
        }

        // Fetch live status from Stripe
        try {
            $service = new StripeService();
            $info = $service->getActiveSubscription($customerId);
            if ($info !== null) {
                return $info;
            }
        } catch (\Throwable) {
            // Fall back to local data
        }

        return [
            'plan' => $project['plan'],
            'amount' => 0,
            'currency' => 'eur',
            'subscription_status' => $project['stripe_status'],
            'next_payment' => $project['stripe_current_period_end'],
            'cancel_at_period_end' => $project['stripe_cancel_at_period_end'],
        ];
    }

    /**
     * Get invoices for a namespace's Stripe customer.
     *
     * @return list<array>
     */
    public function getInvoices(string $slug): array
    {
        $project = $this->findBySlug($slug);
        $customerId = $project['stripe_customer_id'] ?? '';

        if ($customerId === '' || !StripeService::isConfigured()['ok']) {
            return [];
        }

        try {
            $service = new StripeService();
            return $service->listInvoices($customerId);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * List all namespace projects.
     *
     * @return list<array>
     */
    public function listAll(): array
    {
        try {
            $stmt = $this->pdo->query('SELECT * FROM namespace_projects ORDER BY slug');
            $rows = [];
            while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
                $rows[] = $this->normalizeRow($row);
            }
            return $rows;
        } catch (PDOException) {
            return [];
        }
    }

    private function normalizeRow(array $row): array
    {
        return [
            'id' => (string) ($row['id'] ?? ''),
            'slug' => (string) ($row['slug'] ?? ''),
            'plan' => (string) ($row['plan'] ?? Plan::FREE->value),
            'stripe_customer_id' => $row['stripe_customer_id'] ?? null,
            'stripe_sub_id' => $row['stripe_sub_id'] ?? null,
            'stripe_price_id' => $row['stripe_price_id'] ?? null,
            'stripe_status' => $row['stripe_status'] ?? null,
            'stripe_current_period_end' => $row['stripe_current_period_end'] ?? null,
            'stripe_cancel_at_period_end' => (bool) ($row['stripe_cancel_at_period_end'] ?? false),
            'display_name' => (string) ($row['display_name'] ?? $row['slug'] ?? ''),
            'status' => (string) ($row['status'] ?? 'active'),
        ];
    }

    private function driverName(): string
    {
        try {
            return (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        } catch (PDOException) {
            return 'unknown';
        }
    }
}
