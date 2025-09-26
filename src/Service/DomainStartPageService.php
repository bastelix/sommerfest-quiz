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
    private const CORE_START_PAGES = ['help', 'events'];

    private const EXCLUDED_LEGAL_SLUGS = ['impressum', 'datenschutz', 'faq', 'lizenz'];

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Build the available start page options combining core pages and marketing pages.
     *
     * @return array<string,string> Map of slug => label
     */
    public function getStartPageOptions(PageService $pageService): array
    {
        $options = [];

        foreach (self::CORE_START_PAGES as $slug) {
            $options[$slug] = $this->buildLabelFromSlug($slug);
        }

        foreach ($pageService->getAll() as $page) {
            $slug = $page->getSlug();
            if ($slug === '' || in_array($slug, self::EXCLUDED_LEGAL_SLUGS, true)) {
                continue;
            }

            $title = trim($page->getTitle());
            $options[$slug] = $title !== '' ? $title : $this->buildLabelFromSlug($slug);
        }

        return $options;
    }

    private function buildLabelFromSlug(string $slug): string
    {
        $parts = array_filter(explode('-', $slug), static fn ($part): bool => $part !== '');
        if ($parts === []) {
            return ucfirst($slug);
        }

        $parts = array_map(
            static fn (string $part): string => ucfirst($part),
            $parts
        );

        return implode(' ', $parts);
    }

    /**
     * Determine the configured start page for the given host.
     */
    public function getStartPage(string $host): ?string
    {
        $config = $this->getConfigForHost($host);

        if ($config === null) {
            return null;
        }

        $startPage = (string) ($config['start_page'] ?? '');

        return $startPage === '' ? null : $startPage;
    }

    /**
     * Determine the stored domain configuration for the given host.
     *
     * @return array{domain:string,start_page:string,email:?string}|null
     */
    public function getConfigForHost(string $host): ?array
    {
        $host = strtolower(trim($host));
        if ($host === '') {
            return null;
        }

        $candidates = [];
        $normalizedHost = $this->normalizeDomain($host);
        if ($normalizedHost !== '') {
            $candidates[] = $normalizedHost;
        }

        $marketingHost = $this->normalizeDomain($host, stripAdmin: false);
        if ($marketingHost !== '' && $marketingHost !== $normalizedHost) {
            $candidates[] = $marketingHost;
        }

        foreach ($candidates as $candidate) {
            $config = $this->getDomainConfig($candidate);
            if ($config !== null) {
                return $config;
            }
        }

        return null;
    }

    /**
     * Persist or update the start page for a given domain.
     */
    public function saveStartPage(string $domain, string $startPage): void
    {
        $this->saveDomainConfig($domain, $startPage, null);
    }

    /**
     * Persist or update the configuration for a given domain.
     */
    public function saveDomainConfig(string $domain, string $startPage, ?string $email = null): void
    {
        $normalized = $this->normalizeDomain($domain);
        if ($normalized === '') {
            throw new PDOException('Invalid domain supplied');
        }

        $emailValue = $email;
        if ($emailValue !== null) {
            $emailValue = trim($emailValue);
            if ($emailValue === '') {
                $emailValue = null;
            }
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO domain_start_pages(domain, start_page, email) VALUES(?, ?, ?)
            ON CONFLICT(domain) DO UPDATE SET start_page = excluded.start_page, email = excluded.email'
        );
        $stmt->execute([$normalized, $startPage, $emailValue]);
    }

    /**
     * Fetch all configured domain mappings.
     *
     * @return array<string,array{start_page:string,email:?string}> Associative array of domain => config
     */
    public function getAllMappings(): array
    {
        $stmt = $this->pdo->query('SELECT domain, start_page, email FROM domain_start_pages');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $mappings = [];
        foreach ($rows as $row) {
            $domain = isset($row['domain']) ? (string) $row['domain'] : '';
            $page = isset($row['start_page']) ? (string) $row['start_page'] : '';
            if ($domain === '' || $page === '') {
                continue;
            }
            $email = null;
            if (array_key_exists('email', $row) && $row['email'] !== null) {
                $email = (string) $row['email'];
            }
            $mappings[$domain] = [
                'start_page' => $page,
                'email' => $email,
            ];
        }

        return $mappings;
    }

    /**
     * Fetch the stored configuration for a domain.
     *
     * @return array{domain:string,start_page:string,email:?string}|null
     */
    public function getDomainConfig(string $domain): ?array
    {
        $normalized = $this->normalizeDomain($domain);
        if ($normalized === '') {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT domain, start_page, email FROM domain_start_pages WHERE domain = ?');
        $stmt->execute([$normalized]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        $startPage = isset($row['start_page']) ? (string) $row['start_page'] : '';
        if ($startPage === '') {
            return null;
        }

        $email = null;
        if (array_key_exists('email', $row) && $row['email'] !== null) {
            $email = (string) $row['email'];
        }

        return [
            'domain' => $normalized,
            'start_page' => $startPage,
            'email' => $email,
        ];
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
