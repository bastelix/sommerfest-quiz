<?php

declare(strict_types=1);

namespace App\Service;

class StripeService
{
    public static array $calls = [];

    public function __construct() {
    }

    public function findCustomerIdByEmail(string $email): ?string {
        return null;
    }

    public function createCustomer(string $email, ?string $name = null): string {
        return 'cus_test';
    }

    public static ?array $activeSubscription = null;

    public function getActiveSubscription(string $customerId): ?array {
        self::$calls[] = ['getActiveSubscription', $customerId];
        return self::$activeSubscription;
    }

    public function updateSubscriptionForCustomer(string $customerId, string $priceId): void {
        self::$calls[] = ['update', $customerId, $priceId];
    }

    public function cancelSubscriptionForCustomer(string $customerId): void {
        self::$calls[] = ['cancel', $customerId];
    }

    public function reactivateSubscriptionForCustomer(string $customerId): void {
        self::$calls[] = ['reactivate', $customerId];
    }

    public static function isConfigured(): array {
        return ['ok' => true, 'missing' => [], 'warnings' => []];
    }

    public static function priceIdForPlan(string $plan): string {
        $useSandbox = filter_var(getenv('STRIPE_SANDBOX'), FILTER_VALIDATE_BOOLEAN);
        $prefix = $useSandbox ? 'STRIPE_SANDBOX_' : 'STRIPE_';
        $map = [
            'free' => getenv($prefix . 'PRICE_FREE') ?: '',
            'starter' => getenv($prefix . 'PRICE_STARTER') ?: '',
            'standard' => getenv($prefix . 'PRICE_STANDARD') ?: '',
        ];
        return $map[$plan] ?? '';
    }
}
