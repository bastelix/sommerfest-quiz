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

    public function updateSubscriptionForCustomer(string $customerId, string $priceId): void {
        self::$calls[] = ['update', $customerId, $priceId];
    }

    public function cancelSubscriptionForCustomer(string $customerId): void {
        self::$calls[] = ['cancel', $customerId];
    }

    public static function isConfigured(): array {
        return ['ok' => true, 'missing' => [], 'warnings' => []];
    }
}
