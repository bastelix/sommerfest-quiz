<?php

declare(strict_types=1);

namespace App\Service;

use App\Support\DomainNameHelper;
use InvalidArgumentException;
use PDO;
use PDOException;

/**
 * Provides persistence for mapping domains to start pages.
 */
class DomainStartPageService
{
    private const CORE_START_PAGES = ['help', 'events'];

    private const EXCLUDED_LEGAL_SLUGS = ['impressum', 'datenschutz', 'faq', 'lizenz'];

    public const SECRET_PLACEHOLDER = '__SECRET_KEEP__';

    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Retrieve all marketing domains stored in the database.
     *
     * @return list<array{id:int,host:string,normalized_host:string,label:?string}>
     */
    public function listMarketingDomains(): array {
        try {
            $stmt = $this->pdo->query(
                'SELECT id, host, normalized_host, label FROM marketing_domains ORDER BY host ASC'
            );
        } catch (PDOException $exception) {
            if ($this->isMissingMarketingDomainsTable($exception)) {
                return [];
            }

            throw $exception;
        }

        $rows = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        $domains = [];

        foreach ($rows ?: [] as $row) {
            $domain = $this->hydrateMarketingDomain($row);
            if ($domain !== null) {
                $domains[] = $domain;
            }
        }

        return $domains;
    }

    /**
     * Persist or update a marketing domain entry.
     *
     * @return array{id:int,host:string,normalized_host:string,label:?string}
     */
    public function createMarketingDomain(string $host, ?string $label = null): array {
        [$displayHost, $normalizedHost] = $this->prepareMarketingHost($host);
        $labelValue = $this->normalizeMarketingLabel($label);

        $stmt = $this->pdo->prepare(
            'INSERT INTO marketing_domains (host, normalized_host, label) VALUES (?, ?, ?)
            ON CONFLICT(normalized_host) DO UPDATE SET host = EXCLUDED.host, label = EXCLUDED.label'
        );
        $stmt->execute([$displayHost, $normalizedHost, $labelValue]);

        $domain = $this->getMarketingDomainByNormalized($normalizedHost);
        if ($domain === null) {
            throw new PDOException('Failed to persist marketing domain.');
        }

        return $domain;
    }

    /**
     * Update an existing marketing domain entry by its identifier.
     *
     * @return array{id:int,host:string,normalized_host:string,label:?string}|null
     */
    public function updateMarketingDomain(int $id, string $host, ?string $label = null): ?array {
        [$displayHost, $normalizedHost] = $this->prepareMarketingHost($host);
        $labelValue = $this->normalizeMarketingLabel($label);

        $stmt = $this->pdo->prepare(
            'UPDATE marketing_domains SET host = ?, normalized_host = ?, label = ? WHERE id = ?'
        );
        $stmt->execute([$displayHost, $normalizedHost, $labelValue, $id]);

        return $this->getMarketingDomainById($id);
    }

    /**
     * Delete a marketing domain entry by its identifier.
     */
    public function deleteMarketingDomain(int $id): void {
        $stmt = $this->pdo->prepare('DELETE FROM marketing_domains WHERE id = ?');
        $stmt->execute([$id]);
    }

    /**
     * Provision certificates for marketing domains that were added outside the admin controller.
     *
     * @return array{provisioned:list<string>,resolved_marketing_domains:list<string>}
     */
    public function reconcileMarketingDomains(
        MarketingDomainProvider $marketingDomainProvider,
        CertificateProvisioningService $certificateProvisioningService
    ): array {
        $known = [];
        foreach ($marketingDomainProvider->getMarketingDomains(stripAdmin: false) as $existing) {
            $normalized = DomainNameHelper::normalize($existing, stripAdmin: false);
            if ($normalized !== '') {
                $known[$normalized] = true;
            }
        }

        $provisioned = [];
        foreach ($this->listMarketingDomains() as $domain) {
            $host = $domain['host'] !== '' ? $domain['host'] : $domain['normalized_host'];
            $normalized = DomainNameHelper::normalize($host, stripAdmin: false);
            if ($normalized === '' || isset($known[$normalized])) {
                continue;
            }

            $certificateProvisioningService->provisionMarketingDomain($host);
            $provisioned[] = $host;
            $known[$normalized] = true;
        }

        $marketingDomainProvider->clearCache();
        $resolvedDomains = $marketingDomainProvider->getMarketingDomains(stripAdmin: false);

        return [
            'provisioned' => $provisioned,
            'resolved_marketing_domains' => $resolvedDomains,
        ];
    }

    /**
     * Build the available start page options combining core pages and marketing pages.
     *
     * @return array<string,string> Map of slug => label
     */
    public function getStartPageOptions(PageService $pageService): array {
        $options = [];

        foreach (self::CORE_START_PAGES as $slug) {
            $options[$slug] = $this->buildLabelFromSlug($slug);
        }

        foreach ($pageService->getAll() as $page) {
            $slug = $page->getSlug();
            if ($slug === '' || in_array($slug, self::EXCLUDED_LEGAL_SLUGS, true)) {
                continue;
            }

            $baseSlug = MarketingSlugResolver::resolveBaseSlug($slug);
            $title = trim($page->getTitle());
            $label = $title !== '' ? $title : $this->buildLabelFromSlug($baseSlug);

            if (!isset($options[$baseSlug]) || $slug === $baseSlug) {
                $options[$baseSlug] = $label;
            }
        }

        return $options;
    }

    private function buildLabelFromSlug(string $slug): string {
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
    public function getStartPage(string $host): ?string {
        $config = $this->getConfigForHost($host);

        if ($config === null) {
            return null;
        }

        $startPage = trim($config['start_page']);

        return $startPage === '' ? null : $startPage;
    }

    /**
     * Determine the stored domain configuration for the given host.
     *
     * @return array{domain:string,start_page:string,email:?string}|null
     */
    public function getConfigForHost(string $host, bool $includeSensitive = false): ?array {
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
        if ($marketingHost !== '' && !in_array($marketingHost, $candidates, true)) {
            $candidates[] = $marketingHost;
        }

        $canonicalHost = DomainNameHelper::canonicalizeSlug($host);
        if ($canonicalHost !== '' && !in_array($canonicalHost, $candidates, true)) {
            $candidates[] = $canonicalHost;
        }

        foreach ($candidates as $candidate) {
            $config = $this->getDomainConfig($candidate, $includeSensitive);
            if ($config !== null) {
                return $config;
            }
        }

        return null;
    }

    /**
     * Persist or update the start page for a given domain.
     */
    public function saveStartPage(string $domain, string $startPage): void {
        $this->saveDomainConfig($domain, $startPage, null);
    }

    /**
     * Persist or update the configuration for a given domain.
     */
    public function saveDomainConfig(
        string $domain,
        string $startPage,
        ?string $email = null,
        array $smtpConfig = []
    ): void {
        $normalized = $this->normalizeDomain($domain);
        if ($normalized === '') {
            throw new PDOException('Invalid domain supplied');
        }

        $emailValue = $this->normalizeEmail($email);
        $existing = $this->getDomainConfig($normalized, includeSensitive: true);
        $smtp = $this->normalizeSmtpConfig($smtpConfig, $existing);

        $smtpPassValue = $smtp['update_pass']
            ? $smtp['smtp_pass']
            : ($existing !== null ? self::SECRET_PLACEHOLDER : null);

        $stmt = $this->pdo->prepare(
            'INSERT INTO domain_start_pages(
                domain, start_page, email, smtp_host, smtp_user, smtp_pass, smtp_port, smtp_encryption, smtp_dsn
            ) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON CONFLICT(domain) DO UPDATE SET
                start_page = excluded.start_page,
                email = excluded.email,
                smtp_host = excluded.smtp_host,
                smtp_user = excluded.smtp_user,
                smtp_pass = CASE WHEN excluded.smtp_pass = ? THEN domain_start_pages.smtp_pass ' .
                "\n" .
                '                ELSE excluded.smtp_pass END,
                smtp_port = excluded.smtp_port,
                smtp_encryption = excluded.smtp_encryption,
                smtp_dsn = excluded.smtp_dsn'
        );

        $stmt->execute([
            $normalized,
            $startPage,
            $emailValue,
            $smtp['smtp_host'],
            $smtp['smtp_user'],
            $smtpPassValue,
            $smtp['smtp_port'],
            $smtp['smtp_encryption'],
            $smtp['smtp_dsn'],
            self::SECRET_PLACEHOLDER,
        ]);
    }

    /**
     * Fetch all configured domain mappings.
     *
     * @return array<string,array{
     *     start_page:string,
     *     email:?string,
     *     smtp_host:?string,
     *     smtp_user:?string,
     *     smtp_port:?int,
     *     smtp_encryption:?string,
     *     smtp_dsn:?string,
     *     has_smtp_pass:bool
     * }> Associative array of domain => config
     */
    public function getAllMappings(): array {
        $stmt = $this->pdo->query(
            "SELECT domain, start_page, email, smtp_host, smtp_user, smtp_port, smtp_encryption, smtp_dsn,
                CASE WHEN smtp_pass IS NOT NULL AND smtp_pass <> '' THEN 1 ELSE 0 END AS has_smtp_pass
            FROM domain_start_pages"
        );
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
            $smtpPort = null;
            if (array_key_exists('smtp_port', $row) && $row['smtp_port'] !== null) {
                $smtpPort = (int) $row['smtp_port'];
            }

            $smtpHost = null;
            if (array_key_exists('smtp_host', $row) && $row['smtp_host'] !== null) {
                $smtpHost = (string) $row['smtp_host'];
            }

            $smtpUser = null;
            if (array_key_exists('smtp_user', $row) && $row['smtp_user'] !== null) {
                $smtpUser = (string) $row['smtp_user'];
            }

            $smtpEncryption = null;
            if (array_key_exists('smtp_encryption', $row) && $row['smtp_encryption'] !== null) {
                $smtpEncryption = (string) $row['smtp_encryption'];
            }

            $smtpDsn = null;
            if (array_key_exists('smtp_dsn', $row) && $row['smtp_dsn'] !== null) {
                $smtpDsn = (string) $row['smtp_dsn'];
            }

            $mappings[$domain] = [
                'start_page' => $page,
                'email' => $email,
                'smtp_host' => $smtpHost,
                'smtp_user' => $smtpUser,
                'smtp_port' => $smtpPort,
                'smtp_encryption' => $smtpEncryption,
                'smtp_dsn' => $smtpDsn,
                'has_smtp_pass' => ((int) ($row['has_smtp_pass'] ?? 0)) === 1,
            ];
        }

        return $mappings;
    }

    /**
     * Fetch the stored configuration for a domain.
     *
     * @return array{
     *     domain:string,
     *     start_page:string,
     *     email:?string,
     *     smtp_host:?string,
     *     smtp_user:?string,
     *     smtp_port:?int,
     *     smtp_encryption:?string,
     *     smtp_dsn:?string,
     *     has_smtp_pass:bool,
     *     smtp_pass:?string
     * }|null
     */
    public function getDomainConfig(string $domain, bool $includeSensitive = false): ?array {
        $normalized = $this->normalizeDomain($domain);
        if ($normalized === '') {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT domain, start_page, email, smtp_host, smtp_user, smtp_pass, smtp_port, smtp_encryption, smtp_dsn
            FROM domain_start_pages WHERE domain = ?'
        );
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

        $smtpPort = null;
        if (array_key_exists('smtp_port', $row) && $row['smtp_port'] !== null) {
            $smtpPort = (int) $row['smtp_port'];
        }

        $smtpPass = null;
        if (array_key_exists('smtp_pass', $row) && $row['smtp_pass'] !== null) {
            $smtpPass = (string) $row['smtp_pass'];
        }

        $hasPass = $smtpPass !== null && $smtpPass !== '';

        $smtpHost = null;
        if (array_key_exists('smtp_host', $row) && $row['smtp_host'] !== null) {
            $smtpHost = (string) $row['smtp_host'];
        }

        $smtpUser = null;
        if (array_key_exists('smtp_user', $row) && $row['smtp_user'] !== null) {
            $smtpUser = (string) $row['smtp_user'];
        }

        $smtpEncryption = null;
        if (array_key_exists('smtp_encryption', $row) && $row['smtp_encryption'] !== null) {
            $smtpEncryption = (string) $row['smtp_encryption'];
        }

        $smtpDsn = null;
        if (array_key_exists('smtp_dsn', $row) && $row['smtp_dsn'] !== null) {
            $smtpDsn = (string) $row['smtp_dsn'];
        }

        return [
            'domain' => $normalized,
            'start_page' => $startPage,
            'email' => $email,
            'smtp_host' => $smtpHost,
            'smtp_user' => $smtpUser,
            'smtp_port' => $smtpPort,
            'smtp_encryption' => $smtpEncryption,
            'smtp_dsn' => $smtpDsn,
            'has_smtp_pass' => $hasPass,
            'smtp_pass' => $includeSensitive ? $smtpPass : null,
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
    public function determineDomains(?string $mainDomain, string $marketingConfig, string $currentHost = ''): array {
        $domains = [];

        $normalizedMain = $this->normalizeDomain((string) $mainDomain);
        if ($normalizedMain !== '') {
            $domains[$normalizedMain] = [
                'domain' => $normalizedMain,
                'normalized' => $normalizedMain,
                'type' => 'main',
            ];
        }

        foreach ($this->listMarketingDomains() as $marketingDomain) {
            $domain = $marketingDomain['host'] !== ''
                ? $marketingDomain['host']
                : $marketingDomain['normalized_host'];
            $domain = $this->normalizeDomain($domain, stripAdmin: false);
            if ($domain === '') {
                continue;
            }

            $canonical = DomainNameHelper::canonicalizeSlug($domain);
            if ($canonical === '') {
                $canonical = $this->normalizeDomain($domain);
            }
            if ($canonical === '') {
                continue;
            }

            if (!isset($domains[$canonical])) {
                $domains[$canonical] = [
                    'domain' => $domain,
                    'normalized' => $canonical,
                    'type' => 'marketing',
                ];
            }
        }

        $marketingDomains = preg_split('/[\s,]+/', $marketingConfig) ?: [];
        foreach ($marketingDomains as $domain) {
            $domain = trim((string) $domain);
            if ($domain === '') {
                continue;
            }
            $display = $this->normalizeDomain($domain);
            if ($display === '') {
                continue;
            }

            $canonical = DomainNameHelper::canonicalizeSlug($display);
            if ($canonical === '') {
                $canonical = $display;
            }
            if (!isset($domains[$canonical])) {
                $domains[$canonical] = [
                    'domain' => $display,
                    'normalized' => $canonical,
                    'type' => 'marketing',
                ];
            }
        }

        $currentDisplay = $this->normalizeDomain($currentHost, stripAdmin: false);
        $current = DomainNameHelper::canonicalizeSlug($currentHost);
        if ($current !== '' && !isset($domains[$current])) {
            $domains[$current] = [
                'domain' => $currentDisplay !== '' ? $currentDisplay : $current,
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
    public function normalizeDomain(string $domain, bool $stripAdmin = true): string {
        return DomainNameHelper::normalize($domain, $stripAdmin);
    }

    private function normalizeEmail(?string $email): ?string {
        if ($email === null) {
            return null;
        }

        $trimmed = trim($email);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @param array<string,mixed> $smtpConfig
     * @param array<string,mixed>|null $existing
     *
     * @return array{
     *     smtp_host:?string,
     *     smtp_user:?string,
     *     smtp_pass:?string,
     *     smtp_port:?int,
     *     smtp_encryption:?string,
     *     smtp_dsn:?string,
     *     update_pass:bool
     * }
     */
    private function normalizeSmtpConfig(array $smtpConfig, ?array $existing): array {
        $host = $this->readExistingString($existing, 'smtp_host');
        $user = $this->readExistingString($existing, 'smtp_user');
        $port = $this->readExistingInt($existing, 'smtp_port');
        $encryption = $this->readExistingString($existing, 'smtp_encryption');
        $dsn = $this->readExistingString($existing, 'smtp_dsn');
        $pass = $this->readExistingString($existing, 'smtp_pass');

        if (array_key_exists('smtp_host', $smtpConfig)) {
            $value = trim((string) $smtpConfig['smtp_host']);
            if ($value === '') {
                $host = null;
            } else {
                if (
                    filter_var($value, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false
                    && filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6) === false
                ) {
                    throw new InvalidArgumentException('Invalid SMTP host provided.');
                }
                $host = $value;
            }
        }

        if (array_key_exists('smtp_user', $smtpConfig)) {
            $value = trim((string) $smtpConfig['smtp_user']);
            if ($value === '') {
                $user = null;
            } else {
                if (preg_match('/\s/', $value) === 1) {
                    throw new InvalidArgumentException('Invalid SMTP username provided.');
                }
                $user = $value;
            }
        }

        if (array_key_exists('smtp_port', $smtpConfig)) {
            $value = $smtpConfig['smtp_port'];
            if ($value === null || $value === '' || (is_string($value) && trim($value) === '')) {
                $port = null;
            } else {
                if (is_string($value)) {
                    if (!ctype_digit($value)) {
                        throw new InvalidArgumentException('Invalid SMTP port provided.');
                    }
                    $value = (int) $value;
                }
                if (!is_int($value) || $value < 1 || $value > 65535) {
                    throw new InvalidArgumentException('Invalid SMTP port provided.');
                }
                $port = $value;
            }
        }

        if (array_key_exists('smtp_encryption', $smtpConfig)) {
            $value = strtolower(trim((string) $smtpConfig['smtp_encryption']));
            if ($value === '' || $value === 'default') {
                $encryption = null;
            } else {
                $allowed = ['none', 'tls', 'ssl', 'starttls'];
                if (!in_array($value, $allowed, true)) {
                    throw new InvalidArgumentException('Invalid SMTP encryption provided.');
                }
                $encryption = $value === 'none' ? 'none' : $value;
            }
        }

        if (array_key_exists('smtp_dsn', $smtpConfig)) {
            $value = trim((string) $smtpConfig['smtp_dsn']);
            if ($value === '') {
                $dsn = null;
            } else {
                if (!str_contains($value, '://')) {
                    throw new InvalidArgumentException('Invalid SMTP DSN provided.');
                }
                $dsn = $value;
            }
        }

        $updatePass = false;
        if (array_key_exists('smtp_pass', $smtpConfig)) {
            $raw = $smtpConfig['smtp_pass'];
            if ($raw === null || $raw === self::SECRET_PLACEHOLDER) {
                $updatePass = false;
            } else {
                $value = trim((string) $raw);
                $pass = $value === '' ? null : $value;
                $updatePass = true;
            }
        }

        return [
            'smtp_host' => $host,
            'smtp_user' => $user,
            'smtp_pass' => $pass,
            'smtp_port' => $port,
            'smtp_encryption' => $encryption,
            'smtp_dsn' => $dsn,
            'update_pass' => $updatePass,
        ];
    }

    /**
     * @param array<string,mixed>|null $existing
     */
    private function readExistingString(?array $existing, string $key): ?string {
        if ($existing === null || !array_key_exists($key, $existing)) {
            return null;
        }

        $value = $existing[$key];

        return $value === null ? null : (string) $value;
    }

    /**
     * @param array<string,mixed>|null $existing
     */
    private function readExistingInt(?array $existing, string $key): ?int {
        if ($existing === null || !array_key_exists($key, $existing)) {
            return null;
        }

        $value = $existing[$key];

        return $value === null ? null : (int) $value;
    }

    /**
     * @param array<string,mixed> $row
     * @return array{id:int,host:string,normalized_host:string,label:?string}|null
     */
    private function hydrateMarketingDomain(array $row): ?array {
        if (!isset($row['id'], $row['normalized_host'])) {
            return null;
        }

        $id = (int) $row['id'];
        $normalized = (string) $row['normalized_host'];
        if ($normalized === '') {
            return null;
        }

        $host = isset($row['host']) ? (string) $row['host'] : '';
        if ($host === '') {
            $host = $normalized;
        }

        $label = null;
        if (array_key_exists('label', $row) && $row['label'] !== null) {
            $label = trim((string) $row['label']);
            if ($label === '') {
                $label = null;
            }
        }

        return [
            'id' => $id,
            'host' => $host,
            'normalized_host' => $normalized,
            'label' => $label,
        ];
    }

    private function getMarketingDomainByNormalized(string $normalizedHost): ?array {
        $stmt = $this->pdo->prepare(
            'SELECT id, host, normalized_host, label FROM marketing_domains WHERE normalized_host = ?'
        );
        $stmt->execute([$normalizedHost]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrateMarketingDomain($row);
    }

    private function getMarketingDomainById(int $id): ?array {
        $stmt = $this->pdo->prepare(
            'SELECT id, host, normalized_host, label FROM marketing_domains WHERE id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrateMarketingDomain($row);
    }

    /**
     * @return array{0:string,1:string}
     */
    private function prepareMarketingHost(string $host): array {
        $displayHost = $this->normalizeDomain($host, stripAdmin: false);
        if ($displayHost === '') {
            throw new InvalidArgumentException('Invalid marketing domain supplied.');
        }

        $normalizedHost = $this->normalizeDomain($displayHost);
        if ($normalizedHost === '') {
            throw new InvalidArgumentException('Unable to normalize marketing domain.');
        }

        return [$displayHost, $normalizedHost];
    }

    private function normalizeMarketingLabel(?string $label): ?string {
        if ($label === null) {
            return null;
        }

        $trimmed = trim($label);

        return $trimmed === '' ? null : $trimmed;
    }

    private function isMissingMarketingDomainsTable(PDOException $exception): bool {
        $code = (string) $exception->getCode();
        if ($code === '42P01') {
            return true;
        }

        $message = strtolower($exception->getMessage());

        return str_contains($message, 'no such table')
            || str_contains($message, 'relation "marketing_domains" does not exist');
    }
}
