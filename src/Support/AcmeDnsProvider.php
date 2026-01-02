<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

final class AcmeDnsProvider
{
    public const DEFAULT_PROVIDER = 'dns_hetzner';

    /** @var list<string> */
    private const SUPPORTED_PROVIDERS = ['dns_cf', 'dns_hetzner'];

    /**
     * Normalize and validate a provider token. Falls back to the default provider when
     * the input is empty and raises an exception for unsupported values.
    */
    public static function normalize(?string $provider): string
    {
        $normalized = preg_replace('/[\s\p{C}]+/u', ' ', (string) ($provider ?? ''));
        $normalized = strtolower(trim($normalized ?? (string) ($provider ?? '')));
        $cleaned = str_replace('-', '_', $normalized);

        if ($cleaned === '') {
            return self::DEFAULT_PROVIDER;
        }

        $normalized = self::mapAlias($cleaned);
        if (!in_array($normalized, self::SUPPORTED_PROVIDERS, true)) {
            throw new InvalidArgumentException(
                'Unsupported ACME DNS provider. Use one of: ' . implode(', ', self::SUPPORTED_PROVIDERS)
            );
        }

        return $normalized;
    }

    public static function fromEnv(): string
    {
        $value = getenv('ACME_WILDCARD_PROVIDER');

        return self::normalize($value === false ? null : $value);
    }

    private static function mapAlias(string $provider): string
    {
        return match ($provider) {
            'hetzner' => 'dns_hetzner',
            'cloudflare', 'cf' => 'dns_cf',
            default => $provider,
        };
    }
}
