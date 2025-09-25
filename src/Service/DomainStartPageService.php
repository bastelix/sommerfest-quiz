<?php

declare(strict_types=1);

namespace App\Service;

use PDO;
use PDOException;

/**
 * Provides persistence for mapping domains to start pages.
 */
class DomainStartPageService
{
    /**
     * Allowed start page identifiers that can be assigned to a domain.
     */
    public const START_PAGE_OPTIONS = ['help', 'events', 'landing', 'calserver'];

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Determine the configured start page for the given host.
     */
    public function getStartPage(string $host): ?string
    {
        $normalized = $this->normalizeDomain($host);
        if ($normalized === '') {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT start_page FROM domain_start_pages WHERE domain = ?');
        $stmt->execute([$normalized]);
        $value = $stmt->fetchColumn();
        if ($value === false || $value === null) {
            return null;
        }

        return (string) $value;
    }

    /**
     * Persist or update the start page for a given domain.
     */
    public function saveStartPage(string $domain, string $startPage): void
    {
        $normalized = $this->normalizeDomain($domain);
        if ($normalized === '') {
            throw new PDOException('Invalid domain supplied');
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO domain_start_pages(domain, start_page) VALUES(?, ?)
            ON CONFLICT(domain) DO UPDATE SET start_page = excluded.start_page'
        );
        $stmt->execute([$normalized, $startPage]);
    }

    /**
     * Fetch all configured domain mappings.
     *
     * @return array<string,string> Associative array of domain => start_page
     */
    public function getAllMappings(): array
    {
        $stmt = $this->pdo->query('SELECT domain, start_page FROM domain_start_pages');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $mappings = [];
        foreach ($rows as $row) {
            $domain = isset($row['domain']) ? (string) $row['domain'] : '';
            $page = isset($row['start_page']) ? (string) $row['start_page'] : '';
            if ($domain === '' || $page === '') {
                continue;
            }
            $mappings[$domain] = $page;
        }

        return $mappings;
    }

    /**
     * Build a list of domains that can be configured.
     *
     * @param string|null $mainDomain      Primary domain of the application
     * @param string      $marketingConfig Raw MARKETING_DOMAINS value
     * @param string      $currentHost     Hostname of the incoming request
     *
     * @return array<int,array{domain:string,normalized:string,type:string}>
     */
    public function determineDomains(?string $mainDomain, string $marketingConfig, string $currentHost = ''): array
    {
        $domains = [];

        $normalizedMain = $this->normalizeDomain((string) $mainDomain);
        if ($normalizedMain !== '') {
            $domains[$normalizedMain] = [
                'domain' => $normalizedMain,
                'normalized' => $normalizedMain,
                'type' => 'main',
            ];
        }

        $marketingDomains = preg_split('/[\s,]+/', $marketingConfig) ?: [];
        foreach ($marketingDomains as $domain) {
            $domain = trim((string) $domain);
            if ($domain === '') {
                continue;
            }
            $normalized = $this->normalizeDomain($domain);
            if ($normalized === '') {
                continue;
            }
            if (!isset($domains[$normalized])) {
                $domains[$normalized] = [
                    'domain' => $normalized,
                    'normalized' => $normalized,
                    'type' => 'marketing',
                ];
            }
        }

        $current = $this->normalizeDomain($currentHost);
        if ($current !== '' && !isset($domains[$current])) {
            $domains[$current] = [
                'domain' => $current,
                'normalized' => $current,
                'type' => 'custom',
            ];
        }

        ksort($domains);
        if ($normalizedMain !== '' && isset($domains[$normalizedMain])) {
            $main = $domains[$normalizedMain];
            unset($domains[$normalizedMain]);
            $domains = [$normalizedMain => $main] + $domains;
        }

        return array_values($domains);
    }

    /**
     * Normalize a hostname by trimming and removing known prefixes.
     */
    public function normalizeDomain(string $domain, bool $stripAdmin = true): string
    {
        $domain = strtolower(trim($domain));
        if ($domain === '') {
            return '';
        }

        $pattern = $stripAdmin ? '/^(www|admin)\./' : '/^www\./';
        $normalized = (string) preg_replace($pattern, '', $domain);

        return $normalized;
    }
}
