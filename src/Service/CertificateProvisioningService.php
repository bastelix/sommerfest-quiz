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

    public function provisionAllDomains(): void
    {
        $domains = $this->collectMarketingDomains();
        if ($domains === []) {
            error_log('No active domains available for certificate provisioning.');
            return;
        }

        $this->triggerProvisioning($domains);
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
        $this->triggerProvisioning($domains);
    }

    /**
     * Marketing domains are sourced from the admin database/provider and act as
     * the preferred source of truth.
     * The MARKETING_DOMAINS env var is only used as an optional fallback when
     * no entries are configured in the database.
     *
     * @return list<string>
     */
    private function collectMarketingDomains(?string $primary = null): array
    {
        $domains = [];

        $mainDomain = $this->marketingDomainProvider->getMainDomain();
        if ($mainDomain !== null && $mainDomain !== '') {
            $domains[] = $mainDomain;
        }

        foreach ($this->marketingDomainProvider->getMarketingDomains(stripAdmin: false) as $entry) {
            $normalized = DomainNameHelper::normalize((string) $entry, stripAdmin: false);
            if ($normalized !== '') {
                $domains[] = $normalized;
            }
        }

        if ($primary !== null) {
            $domains[] = $primary;
        }

        $domains = array_values(array_unique(array_filter($domains, static fn ($value): bool => $value !== '')));

        return $domains;
    }

    /**
     * @param list<string> $domains
     */
    private function triggerProvisioning(array $domains): void
    {
        $envValue = implode(',', $domains);
        $script = dirname(__DIR__, 2) . '/scripts/request_ssl_for_domains.sh';
        if (!is_file($script)) {
            throw new RuntimeException('Certificate request script not found.');
        }

        error_log('Requesting certificates for domains: ' . implode(', ', $domains));
        $this->runWithMarketingEnv($envValue, static function () use ($script, $envValue): void {
            runBackgroundProcess($script, [$envValue], dirname(__DIR__, 2) . '/logs/ssl_provisioning.log');
        });
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
