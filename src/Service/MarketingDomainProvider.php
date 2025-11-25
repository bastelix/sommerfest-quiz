<?php

declare(strict_types=1);

namespace App\Service;

use App\Support\DomainNameHelper;
use Closure;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * Provides cached access to marketing domain configuration.
 */
class MarketingDomainProvider
{
    private const DEFAULT_CACHE_TTL = 300;

    /** @var Closure():PDO */
    private Closure $connectionFactory;

    private int $cacheTtl;

    /** @var list<array{host:string,normalized:string}>|null */
    private ?array $marketingCache = null;

    private ?int $marketingLoadedAt = null;

    private ?string $mainDomainCache = null;

    private ?int $mainDomainLoadedAt = null;

    /**
     * @param Closure():PDO $connectionFactory
     */
    public function __construct(Closure $connectionFactory, int $cacheTtl = self::DEFAULT_CACHE_TTL)
    {
        $this->connectionFactory = $connectionFactory;
        $this->cacheTtl = max(0, $cacheTtl);
    }

    /**
     * Resolve the configured main domain.
     */
    public function getMainDomain(): ?string
    {
        $now = time();
        if ($this->mainDomainCache !== null && $this->isFresh($this->mainDomainLoadedAt, $now)) {
            return $this->mainDomainCache;
        }

        $value = null;

        try {
            $pdo = $this->resolveConnection();
            $stmt = $pdo->prepare('SELECT value FROM settings WHERE key = ?');
            $stmt->execute(['main_domain']);
            $fetched = $stmt->fetchColumn();
            if ($fetched !== false && $fetched !== null) {
                $candidate = strtolower(trim((string) $fetched));
                if ($candidate !== '') {
                    $value = $candidate;
                }
            }
        } catch (Throwable $exception) {
            if ($this->mainDomainCache !== null) {
                return $this->mainDomainCache;
            }
        }

        if ($value === null) {
            $env = getenv('MAIN_DOMAIN');
            if ($env !== false) {
                $candidate = strtolower(trim((string) $env));
                if ($candidate !== '') {
                    $value = $candidate;
                }
            }
        }

        $this->mainDomainCache = $value;
        $this->mainDomainLoadedAt = $now;

        return $this->mainDomainCache;
    }

    /**
     * Retrieve marketing domains from the persistent store.
     *
     * @return list<string>
     */
    public function getMarketingDomains(bool $stripAdmin = true): array
    {
        $entries = $this->getMarketingDomainEntries();
        if ($entries === []) {
            return [];
        }

        $list = [];
        foreach ($entries as $entry) {
            $value = $stripAdmin ? $entry['normalized'] : $entry['host'];
            if ($value === '') {
                continue;
            }
            $list[$value] = true;
        }

        return array_keys($list);
    }

    /**
     * Reset cached data.
     */
    public function clearCache(): void
    {
        $this->marketingCache = null;
        $this->marketingLoadedAt = null;
        $this->mainDomainCache = null;
        $this->mainDomainLoadedAt = null;
    }

    /**
     * @return list<array{host:string,normalized:string}>
     */
    private function getMarketingDomainEntries(): array
    {
        $now = time();
        if ($this->marketingCache !== null && $this->isFresh($this->marketingLoadedAt, $now)) {
            return $this->marketingCache;
        }

        try {
            $domains = $this->loadMarketingDomainsFromDatabase();
        } catch (Throwable $exception) {
            if ($this->marketingCache !== null) {
                return $this->marketingCache;
            }

            $domains = $this->loadMarketingDomainsFromEnv();
        }

        if ($domains === []) {
            $domains = $this->loadMarketingDomainsFromEnv();
        } else {
            $domains = $this->deduplicateMarketingEntries(array_merge(
                $domains,
                $this->loadMarketingDomainsFromEnv()
            ));
        }

        $this->marketingCache = $domains;
        $this->marketingLoadedAt = $now;

        return $this->marketingCache;
    }

    /**
     * @return list<array{host:string,normalized:string}>
     */
    private function loadMarketingDomainsFromDatabase(): array
    {
        $pdo = $this->resolveConnection();

        try {
            $stmt = $pdo->query('SELECT host, normalized_host FROM marketing_domains');
        } catch (PDOException $exception) {
            throw $exception;
        }

        $rows = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        return $this->normalizeMarketingDomainRows($rows);
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return list<array{host:string,normalized:string}>
     */
    private function normalizeMarketingDomainRows(array $rows): array
    {
        $entries = [];

        foreach ($rows as $row) {
            $host = isset($row['host']) ? strtolower(trim((string) $row['host'])) : '';
            $normalized = isset($row['normalized_host'])
                ? strtolower(trim((string) $row['normalized_host']))
                : '';

            if ($normalized === '' && $host === '') {
                continue;
            }

            if ($normalized === '') {
                $normalized = DomainNameHelper::normalize($host);
            } else {
                $normalized = DomainNameHelper::normalize($normalized);
            }

            if ($normalized === '') {
                continue;
            }

            if ($host === '') {
                $host = DomainNameHelper::normalize($normalized, stripAdmin: false);
            } else {
                $host = DomainNameHelper::normalize($host, stripAdmin: false);
            }

            if ($host === '') {
                $host = $normalized;
            }

            $entries[] = [
                'host' => $host,
                'normalized' => $normalized,
            ];
        }

        return $this->deduplicateMarketingEntries($entries);
    }

    /**
     * @return list<array{host:string,normalized:string}>
     */
    private function deduplicateMarketingEntries(array $entries): array
    {
        $seen = [];
        $unique = [];

        foreach ($entries as $entry) {
            $key = $entry['normalized'];
            if ($key === '' || isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $entry;
        }

        return $unique;
    }

    /**
     * @return list<array{host:string,normalized:string}>
     */
    private function loadMarketingDomainsFromEnv(): array
    {
        $env = getenv('MARKETING_DOMAINS');
        if ($env === false || trim((string) $env) === '') {
            return [];
        }

        $entries = preg_split('/[\s,]+/', (string) $env) ?: [];
        $domains = [];

        foreach ($entries as $entry) {
            $entry = strtolower(trim((string) $entry));
            if ($entry === '') {
                continue;
            }

            $normalized = DomainNameHelper::normalize($entry);
            if ($normalized === '') {
                continue;
            }

            $host = DomainNameHelper::normalize($entry, stripAdmin: false);
            if ($host === '') {
                $host = $normalized;
            }

            $domains[] = [
                'host' => $host,
                'normalized' => $normalized,
            ];
        }

        return $this->deduplicateMarketingEntries($domains);
    }

    private function isFresh(?int $timestamp, int $now): bool
    {
        if ($timestamp === null) {
            return false;
        }

        if ($this->cacheTtl === 0) {
            return false;
        }

        return ($now - $timestamp) < $this->cacheTtl;
    }

    private function resolveConnection(): PDO
    {
        $connection = ($this->connectionFactory)();

        return $connection;
    }
}
