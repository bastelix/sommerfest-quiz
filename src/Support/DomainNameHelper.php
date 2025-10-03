<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Normalises host names for domain-specific features.
 */
final class DomainNameHelper
{
    private function __construct()
    {
    }

    public static function normalize(string $domain, bool $stripAdmin = true): string
    {
        $domain = strtolower(trim($domain));
        if ($domain === '') {
            return '';
        }

        $pattern = $stripAdmin ? '/^(www|admin)\./' : '/^www\./';
        $normalized = (string) preg_replace($pattern, '', $domain);
        $normalized = preg_replace('/[^a-z0-9\-.]/', '', $normalized) ?? '';
        $normalized = trim($normalized, '.');

        return $normalized;
    }
}

