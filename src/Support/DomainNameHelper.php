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

        if (str_contains($domain, '://')) {
            $host = parse_url($domain, PHP_URL_HOST);
            if (is_string($host) && $host !== '') {
                $domain = $host;
            }
        }

        $prefixes = $stripAdmin
            ? self::getStrippablePrefixes()
            : ['www'];

        if ($prefixes !== []) {
            $pattern = sprintf('/^(%s)\./', implode('|', array_map(
                static fn (string $prefix): string => preg_quote($prefix, '/'),
                $prefixes
            )));
            $normalized = (string) preg_replace($pattern, '', $domain);
        } else {
            $normalized = $domain;
        }

        $normalized = preg_replace('/[^a-z0-9\-.]/', '', $normalized) ?? '';
        $normalized = trim($normalized, '.');

        return $normalized;
    }

    /**
     * @return list<string>
     */
    private static function getStrippablePrefixes(): array
    {
        $env = getenv('DOMAIN_STRIPPED_PREFIXES');
        if ($env !== false) {
            $raw = preg_split('/[\s,]+/', strtolower((string) $env)) ?: [];
        } else {
            $raw = [];
        }

        $raw = array_filter(array_map(static fn (string $prefix): string => trim($prefix), $raw));

        if ($raw === []) {
            $raw = ['www', 'admin', 'assistant'];
        }

        return array_values(array_unique(array_merge(['www'], $raw)));
    }
}

