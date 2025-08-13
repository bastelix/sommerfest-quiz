<?php

namespace App\Service;

class StripeService
{
    public function __construct()
    {
    }

    public function findCustomerIdByEmail(string $email): ?string
    {
        return null;
    }

    public function createCustomer(string $email, ?string $name = null): string
    {
        return 'cus_test';
    }

    public static function isConfigured(): bool
    {
        return true;
    }
}
