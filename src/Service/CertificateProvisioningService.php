<?php

declare(strict_types=1);

namespace App\Service;

use App\Support\DomainNameHelper;
use InvalidArgumentException;
use RuntimeException;

use function App\runBackgroundProcess;

/**
 * Provisions or renews TLS certificates for marketing domains via the
 * acme-companion setup.
 */
final class CertificateProvisioningService
{
    private MarketingDomainProvider $marketingDomainProvider;

    public function __construct(MarketingDomainProvider $marketingDomainProvider)
    {
        $this->marketingDomainProvider = $marketingDomainProvider;
    }

    /**
     * Trigger certificate issuance for the given domain in the background.
     */
    public function provisionMarketingDomain(string $domain): void
    {
        $normalized = DomainNameHelper::normalize($domain, stripAdmin: false);
        if ($normalized === '') {
            throw new InvalidArgumentException('Invalid domain supplied.');
        }

        $domains = $this->collectMarketingDomains($normalized);
        $envValue = implode(',', $domains);

        $script = dirname(__DIR__, 2) . '/scripts/renew_ssl.sh';
        if (!is_file($script)) {
            throw new RuntimeException('Renew script not found.');
        }

        $this->runWithMarketingEnv($envValue, static function () use ($script): void {
            runBackgroundProcess($script, ['--main']);
        });
    }

    /**
     * Marketing domains are sourced from the admin database/provider and act as
     * the preferred source of truth.
     * The MARKETING_DOMAINS env var is only used as an optional fallback when
     * no entries are configured in the database.
     *
     * @return list<string>
     */
    private function collectMarketingDomains(string $primary): array
    {
        $domains = [];

        foreach ($this->marketingDomainProvider->getMarketingDomains(stripAdmin: false) as $entry) {
            $normalized = DomainNameHelper::normalize((string) $entry, stripAdmin: false);
            if ($normalized !== '') {
                $domains[] = $normalized;
            }
        }

        $domains[] = $primary;

        $domains = array_values(array_unique(array_filter($domains, static fn ($value): bool => $value !== '')));

        return $domains;
    }

    private function runWithMarketingEnv(string $marketingDomains, callable $callback): void
    {
        $previous = getenv('MARKETING_DOMAINS');
        putenv('MARKETING_DOMAINS=' . $marketingDomains);
        $_ENV['MARKETING_DOMAINS'] = $marketingDomains;

        try {
            $callback();
        } finally {
            if ($previous === false) {
                putenv('MARKETING_DOMAINS');
                unset($_ENV['MARKETING_DOMAINS']);
            } else {
                putenv('MARKETING_DOMAINS=' . $previous);
                $_ENV['MARKETING_DOMAINS'] = $previous;
            }
        }
    }
}
