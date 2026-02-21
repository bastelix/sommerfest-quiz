<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Plan;
use App\Exception\QuotaExceededException;
use PDO;
use PDOException;

/**
 * Central service for namespace quota checking and tracking.
 *
 * Reads limits from the plan_limits table (with fallback to Plan enum)
 * and tracks current usage in namespace_quota_usage.
 */
class QuotaService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Check whether a resource can be created within the quota limit.
     */
    public function canCreate(string $namespaceId, string $metric): bool
    {
        $plan = $this->getPlan($namespaceId);
        $limit = $this->getLimit($plan, $metric);
        $current = $this->getUsage($namespaceId, $metric);

        return $current < $limit;
    }

    /**
     * Assert that a resource can be created, throwing if the quota is exceeded.
     *
     * @throws QuotaExceededException
     */
    public function assertCanCreate(string $namespaceId, string $metric): void
    {
        $plan = $this->getPlan($namespaceId);
        $limit = $this->getLimit($plan, $metric);
        $current = $this->getUsage($namespaceId, $metric);

        if ($current >= $limit) {
            throw new QuotaExceededException($metric, $plan, $current, $limit);
        }
    }

    /**
     * Increment the usage counter after a successful resource creation.
     */
    public function increment(string $namespaceId, string $metric, int $amount = 1): void
    {
        if ($amount < 1) {
            return;
        }

        $this->upsertUsage($namespaceId, $metric, $amount);
    }

    /**
     * Decrement the usage counter after a resource deletion. Never goes below 0.
     */
    public function decrement(string $namespaceId, string $metric, int $amount = 1): void
    {
        if ($amount < 1) {
            return;
        }

        try {
            $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        } catch (PDOException) {
            $driver = 'unknown';
        }

        if ($driver === 'sqlite') {
            $stmt = $this->pdo->prepare(
                'UPDATE namespace_quota_usage '
                . 'SET current_value = MAX(0, current_value - :amount) '
                . 'WHERE namespace_id = :ns AND metric = :metric'
            );
            $stmt->execute([
                ':ns' => $namespaceId,
                ':metric' => $metric,
                ':amount' => $amount,
            ]);
        } else {
            $stmt = $this->pdo->prepare(
                'UPDATE namespace_quota_usage '
                . 'SET current_value = GREATEST(0, current_value - :amount), '
                . "last_updated = now() "
                . 'WHERE namespace_id = :ns::uuid AND metric = :metric'
            );
            $stmt->execute([
                ':ns' => $namespaceId,
                ':metric' => $metric,
                ':amount' => $amount,
            ]);
        }
    }

    /**
     * Set the usage counter to an absolute value (useful for recount/sync).
     */
    public function setUsage(string $namespaceId, string $metric, int $value): void
    {
        $value = max(0, $value);

        try {
            $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        } catch (PDOException) {
            $driver = 'unknown';
        }

        if ($driver === 'sqlite') {
            $stmt = $this->pdo->prepare(
                'INSERT INTO namespace_quota_usage (namespace_id, metric, current_value) '
                . 'VALUES (:ns, :metric, :val) '
                . 'ON CONFLICT (namespace_id, metric) DO UPDATE SET current_value = :val2'
            );
            $stmt->execute([
                ':ns' => $namespaceId,
                ':metric' => $metric,
                ':val' => $value,
                ':val2' => $value,
            ]);
        } else {
            $stmt = $this->pdo->prepare(
                'INSERT INTO namespace_quota_usage (namespace_id, metric, current_value, last_updated) '
                . 'VALUES (:ns::uuid, :metric, :val, now()) '
                . 'ON CONFLICT (namespace_id, metric) DO UPDATE '
                . 'SET current_value = :val2, last_updated = now()'
            );
            $stmt->execute([
                ':ns' => $namespaceId,
                ':metric' => $metric,
                ':val' => $value,
                ':val2' => $value,
            ]);
        }
    }

    /**
     * Get the current usage value for a specific metric.
     */
    public function getUsage(string $namespaceId, string $metric): int
    {
        try {
            $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        } catch (PDOException) {
            $driver = 'unknown';
        }

        if ($driver === 'sqlite') {
            $stmt = $this->pdo->prepare(
                'SELECT current_value FROM namespace_quota_usage '
                . 'WHERE namespace_id = :ns AND metric = :metric'
            );
        } else {
            $stmt = $this->pdo->prepare(
                'SELECT current_value FROM namespace_quota_usage '
                . 'WHERE namespace_id = :ns::uuid AND metric = :metric'
            );
        }

        $stmt->execute([':ns' => $namespaceId, ':metric' => $metric]);
        $value = $stmt->fetchColumn();

        return $value !== false ? (int) $value : 0;
    }

    /**
     * Get a full quota overview for a namespace (all metrics with current value and limit).
     *
     * @return list<array{metric:string,current_value:int,max_value:int}>
     */
    public function getQuotaOverview(string $namespaceId): array
    {
        $plan = $this->getPlan($namespaceId);
        $metrics = Plan::allMetrics();
        $overview = [];

        foreach ($metrics as $metric) {
            $overview[] = [
                'metric' => $metric,
                'current_value' => $this->getUsage($namespaceId, $metric),
                'max_value' => $this->getLimit($plan, $metric),
            ];
        }

        return $overview;
    }

    /**
     * Resolve the plan for a namespace from the namespace_projects table.
     */
    private function getPlan(string $namespaceId): string
    {
        try {
            $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        } catch (PDOException) {
            $driver = 'unknown';
        }

        if ($driver === 'sqlite') {
            $stmt = $this->pdo->prepare(
                'SELECT plan FROM namespace_projects WHERE id = :ns'
            );
        } else {
            $stmt = $this->pdo->prepare(
                'SELECT plan FROM namespace_projects WHERE id = :ns::uuid'
            );
        }

        $stmt->execute([':ns' => $namespaceId]);
        $plan = $stmt->fetchColumn();

        return $plan !== false ? (string) $plan : 'free';
    }

    /**
     * Get the limit for a metric on a given plan.
     * Checks plan_limits table first, falls back to Plan enum.
     */
    private function getLimit(string $plan, string $metric): int
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT max_value FROM plan_limits WHERE plan = :plan AND metric = :metric'
            );
            $stmt->execute([':plan' => $plan, ':metric' => $metric]);
            $value = $stmt->fetchColumn();

            if ($value !== false) {
                return (int) $value;
            }
        } catch (PDOException) {
            // Table may not exist yet, fall through to enum
        }

        $planEnum = Plan::tryFrom($plan);
        if ($planEnum !== null) {
            return $planEnum->limits()[$metric] ?? 0;
        }

        return 0;
    }

    /**
     * UPSERT a usage counter increment.
     */
    private function upsertUsage(string $namespaceId, string $metric, int $amount): void
    {
        try {
            $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        } catch (PDOException) {
            $driver = 'unknown';
        }

        if ($driver === 'sqlite') {
            $stmt = $this->pdo->prepare(
                'INSERT INTO namespace_quota_usage (namespace_id, metric, current_value) '
                . 'VALUES (:ns, :metric, :amount) '
                . 'ON CONFLICT (namespace_id, metric) DO UPDATE '
                . 'SET current_value = current_value + :amount2'
            );
            $stmt->execute([
                ':ns' => $namespaceId,
                ':metric' => $metric,
                ':amount' => $amount,
                ':amount2' => $amount,
            ]);
        } else {
            $stmt = $this->pdo->prepare(
                'INSERT INTO namespace_quota_usage (namespace_id, metric, current_value, last_updated) '
                . 'VALUES (:ns::uuid, :metric, :amount, now()) '
                . 'ON CONFLICT (namespace_id, metric) DO UPDATE '
                . 'SET current_value = namespace_quota_usage.current_value + :amount2, '
                . 'last_updated = now()'
            );
            $stmt->execute([
                ':ns' => $namespaceId,
                ':metric' => $metric,
                ':amount' => $amount,
                ':amount2' => $amount,
            ]);
        }
    }
}
