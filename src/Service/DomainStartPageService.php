<?php

declare(strict_types=1);

namespace App\Service;

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

            $title = trim($page->getTitle());
            $options[$slug] = $title !== '' ? $title : $this->buildLabelFromSlug($slug);
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
        if ($marketingHost !== '' && $marketingHost !== $normalizedHost) {
            $candidates[] = $marketingHost;
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
                smtp_pass = CASE WHEN excluded.smtp_pass = ? THEN domain_start_pages.smtp_pass ELSE excluded.smtp_pass END,
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
     * @return array<string,array{start_page:string,email:?string,smtp_host:?string,smtp_user:?string,smtp_port:?int,smtp_encryption:?string,smtp_dsn:?string,has_smtp_pass:bool}> Associative array of domain => config
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

            $mappings[$domain] = [
                'start_page' => $page,
                'email' => $email,
                'smtp_host' => isset($row['smtp_host']) && $row['smtp_host'] !== null ? (string) $row['smtp_host'] : null,
                'smtp_user' => isset($row['smtp_user']) && $row['smtp_user'] !== null ? (string) $row['smtp_user'] : null,
                'smtp_port' => $smtpPort,
                'smtp_encryption' => isset($row['smtp_encryption']) && $row['smtp_encryption'] !== null
                    ? (string) $row['smtp_encryption']
                    : null,
                'smtp_dsn' => isset($row['smtp_dsn']) && $row['smtp_dsn'] !== null ? (string) $row['smtp_dsn'] : null,
                'has_smtp_pass' => ((int) ($row['has_smtp_pass'] ?? 0)) === 1,
            ];
        }

        return $mappings;
    }

    /**
     * Fetch the stored configuration for a domain.
     *
     * @return array{domain:string,start_page:string,email:?string,smtp_host:?string,smtp_user:?string,smtp_port:?int,smtp_encryption:?string,smtp_dsn:?string,has_smtp_pass:bool,smtp_pass:?string}|null
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

        return [
            'domain' => $normalized,
            'start_page' => $startPage,
            'email' => $email,
            'smtp_host' => isset($row['smtp_host']) && $row['smtp_host'] !== null ? (string) $row['smtp_host'] : null,
            'smtp_user' => isset($row['smtp_user']) && $row['smtp_user'] !== null ? (string) $row['smtp_user'] : null,
            'smtp_port' => $smtpPort,
            'smtp_encryption' => isset($row['smtp_encryption']) && $row['smtp_encryption'] !== null
                ? (string) $row['smtp_encryption']
                : null,
            'smtp_dsn' => isset($row['smtp_dsn']) && $row['smtp_dsn'] !== null ? (string) $row['smtp_dsn'] : null,
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
    public function normalizeDomain(string $domain, bool $stripAdmin = true): string {
        $domain = strtolower(trim($domain));
        if ($domain === '') {
            return '';
        }

        $pattern = $stripAdmin ? '/^(www|admin)\./' : '/^www\./';
        $normalized = (string) preg_replace($pattern, '', $domain);

        return $normalized;
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
     * @return array{smtp_host:?string,smtp_user:?string,smtp_pass:?string,smtp_port:?int,smtp_encryption:?string,smtp_dsn:?string,update_pass:bool}
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
}
