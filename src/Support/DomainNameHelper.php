<?php

declare(strict_types=1);

namespace App\Support;

use App\Service\MarketingDomainProvider;
use Throwable;

/**
 * Normalises host names for domain-specific features.
 */
final class DomainNameHelper
{
    private static ?MarketingDomainProvider $marketingDomainProvider = null;

    private function __construct()
    {
    }

    public static function setMarketingDomainProvider(?MarketingDomainProvider $provider): void
    {
        self::$marketingDomainProvider = $provider;
    }

    public static function getMarketingDomainProvider(): ?MarketingDomainProvider
    {
        return self::$marketingDomainProvider;
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

    public static function canonicalizeSlug(string $domain): string
    {
        $normalized = self::normalize($domain);
        if ($normalized === '') {
            return '';
        }

        $marketingDomains = self::getMarketingDomains();
        if ($marketingDomains !== [] && isset($marketingDomains[$normalized])) {
            return self::stripMarketingSuffix($normalized);
        }

        return $normalized;
    }

    /**
     * Determine additional hostnames that map to the same canonical slug.
     *
     * @return list<string>
     */
    public static function marketingAliases(string $domain): array
    {
        $canonical = self::canonicalizeSlug($domain);
        if ($canonical === '') {
            return [];
        }

        $aliases = [];
        $marketingDomains = self::getMarketingDomains();
        if ($marketingDomains === []) {
            return $aliases;
        }

        foreach (array_keys($marketingDomains) as $entry) {
            if ($entry === $canonical) {
                continue;
            }

            if (self::stripMarketingSuffix($entry) === $canonical) {
                $aliases[] = $entry;
            }
        }

        return array_values(array_unique($aliases));
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

    /**
     * @return array<string,true>
     */
    private static function getMarketingDomains(): array
    {
        if (self::$marketingDomainProvider !== null) {
            try {
                $domains = self::$marketingDomainProvider->getMarketingDomains();
                if ($domains === []) {
                    return [];
                }

                $map = [];
                foreach ($domains as $domain) {
                    $domain = strtolower(trim((string) $domain));
                    if ($domain === '') {
                        continue;
                    }

                    $map[$domain] = true;
                }

                if ($map !== []) {
                    return $map;
                }
            } catch (Throwable $exception) {
                // Ignore provider failures and fall back to environment configuration.
            }
        }

        $config = getenv('MARKETING_DOMAINS');
        if ($config === false || trim((string) $config) === '') {
            return [];
        }

        $entries = preg_split('/[\s,]+/', strtolower((string) $config)) ?: [];
        $domains = [];

        foreach ($entries as $entry) {
            $normalized = self::normalize($entry);
            if ($normalized === '') {
                continue;
            }

            $domains[$normalized] = true;
        }

        return $domains;
    }

    private static function stripMarketingSuffix(string $domain): string
    {
        $parts = array_values(array_filter(explode('.', $domain), static fn (string $part): bool => $part !== ''));
        if ($parts === []) {
            return $domain;
        }

        return $parts[0];
    }
}

