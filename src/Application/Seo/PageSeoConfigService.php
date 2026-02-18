<?php

declare(strict_types=1);

namespace App\Application\Seo;

use App\Application\EventListener\SeoConfigListener;
use App\Application\Routing\RedirectManager;
use App\Domain\Event\SeoConfigSaved;
use App\Domain\Event\SeoConfigUpdated;
use App\Domain\PageSeoConfig;
use App\Infrastructure\Cache\PageSeoCache;
use App\Infrastructure\Database;
use App\Infrastructure\Event\EventDispatcher;
use App\Service\PageService;
use App\Support\DomainNameHelper;
use PDO;

/**
 * Handles loading, saving and validating SEO configuration for pages.
 */
class PageSeoConfigService
{
    private PDO $pdo;
    private RedirectManager $redirects;
    private SeoValidator $validator;
    private PageSeoCache $cache;
    private EventDispatcher $dispatcher;
    private PageService $pages;

    public function __construct(
        ?PDO $pdo = null,
        ?RedirectManager $redirects = null,
        ?SeoValidator $validator = null,
        ?PageSeoCache $cache = null,
        ?EventDispatcher $dispatcher = null,
        ?PageService $pages = null
    ) {
        $this->pdo = $pdo ?? Database::connectFromEnv();
        $this->redirects = $redirects ?? new RedirectManager();
        $this->validator = $validator ?? new SeoValidator();
        $this->cache = $cache ?? new PageSeoCache();
        $this->dispatcher = $dispatcher ?? new EventDispatcher();
        $this->pages = $pages ?? new PageService($this->pdo);
        SeoConfigListener::register($this->dispatcher, $this->cache);
    }

    public function load(int $pageId): ?PageSeoConfig {
        $config = $this->loadForPageId($pageId);
        if ($config !== null) {
            return $config;
        }

        $fallbackPageId = $this->resolveFallbackPageId($pageId);
        if ($fallbackPageId === null) {
            return null;
        }

        return $this->loadForPageId($fallbackPageId);
    }

    private function loadForPageId(int $pageId): ?PageSeoConfig {
        $cached = $this->cache->get($pageId);
        if ($cached !== null) {
            return $cached;
        }

        $stmt = $this->pdo->prepare(
            'SELECT slug, domain, meta_title, meta_description, canonical_url, robots_meta, '
            . 'og_title, og_description, og_image, favicon_path, schema_json, hreflang '
            . 'FROM page_seo_config WHERE page_id = ?'
        );
        $stmt->execute([$pageId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row !== false) {
            $config = new PageSeoConfig(
                $pageId,
                (string) ($row['slug'] ?? ''),
                $row['meta_title'] !== null ? (string) $row['meta_title'] : null,
                $row['meta_description'] !== null ? (string) $row['meta_description'] : null,
                $row['canonical_url'] !== null ? (string) $row['canonical_url'] : null,
                $row['robots_meta'] !== null ? (string) $row['robots_meta'] : null,
                $row['og_title'] !== null ? (string) $row['og_title'] : null,
                $row['og_description'] !== null ? (string) $row['og_description'] : null,
                $row['og_image'] !== null ? (string) $row['og_image'] : null,
                $row['schema_json'] !== null ? (string) $row['schema_json'] : null,
                $row['hreflang'] !== null ? (string) $row['hreflang'] : null,
                $row['domain'] !== null ? (string) $row['domain'] : null,
                $row['favicon_path'] !== null ? (string) $row['favicon_path'] : null
            );
            $this->cache->set($config);
            return $config;
        }

        return null;
    }

    private function resolveFallbackPageId(int $pageId): ?int {
        $page = $this->pages->findById($pageId);
        if ($page === null) {
            return null;
        }

        if ($page->getNamespace() === PageService::DEFAULT_NAMESPACE) {
            return null;
        }

        $fallbackPage = $this->pages->findByKey(PageService::DEFAULT_NAMESPACE, $page->getSlug());
        if ($fallbackPage === null) {
            return null;
        }

        return $fallbackPage->getId();
    }

    public function save(PageSeoConfig $config): void {
        $stmt = $this->pdo->prepare('SELECT slug, canonical_url, domain FROM page_seo_config WHERE page_id = ?');
        $stmt->execute([$config->getPageId()]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (is_array($existing)) {
            $oldSlug = $existing['slug'] ?? null;
            $oldCanonical = $existing['canonical_url'] ?? null;
            $oldDomain = isset($existing['domain']) ? (string) $existing['domain'] : null;
            $normalizedDomain = $this->normalizeDomain($config->getDomain());
            if ($oldSlug && $oldSlug !== $config->getSlug() && $oldDomain === $normalizedDomain) {
                $this->redirects->register('/' . ltrim((string) $oldSlug, '/'), '/' . ltrim($config->getSlug(), '/'));
            }
            if ($oldCanonical && $config->getCanonicalUrl() && $oldCanonical !== $config->getCanonicalUrl()) {
                $this->redirects->register($oldCanonical, (string) $config->getCanonicalUrl());
            }
        }

        $normalizedDomain = $this->normalizeDomain($config->getDomain());
        $normalizedFavicon = $this->normalizeFaviconPath($config->getFaviconPath());

        $params = [
            $config->getPageId(),
            $normalizedDomain,
            $config->getMetaTitle(),
            $config->getMetaDescription(),
            $config->getSlug(),
            $config->getCanonicalUrl(),
            $config->getRobotsMeta(),
            $config->getOgTitle(),
            $config->getOgDescription(),
            $config->getOgImage(),
            $normalizedFavicon,
            $this->normalizeSchemaJson($config->getSchemaJson()),
            $config->getHreflang(),
        ];

        $upsert = $this->pdo->prepare(
            'INSERT INTO page_seo_config('
            . 'page_id, domain, meta_title, meta_description, slug, canonical_url, robots_meta, '
            . 'og_title, og_description, og_image, favicon_path, schema_json, hreflang) '
            . 'VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?) '
            . 'ON CONFLICT(page_id) DO UPDATE SET domain=excluded.domain, meta_title=excluded.meta_title, '
            . 'meta_description=excluded.meta_description, slug=excluded.slug, '
            . 'canonical_url=excluded.canonical_url, robots_meta=excluded.robots_meta, '
            . 'og_title=excluded.og_title, og_description=excluded.og_description, '
            . 'og_image=excluded.og_image, favicon_path=excluded.favicon_path, schema_json=excluded.schema_json, '
            . 'hreflang=excluded.hreflang, updated_at=CURRENT_TIMESTAMP'
        );
        $upsert->execute($params);

        $history = $this->pdo->prepare(
            'INSERT INTO page_seo_config_history('
            . 'page_id, domain, meta_title, meta_description, slug, canonical_url, robots_meta, '
            . 'og_title, og_description, og_image, favicon_path, schema_json, hreflang) '
            . 'VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $history->execute($params);

        $event = is_array($existing)
            ? new SeoConfigUpdated($config)
            : new SeoConfigSaved($config);
        $this->dispatcher->dispatch($event);
    }

    /**
     * Build a default configuration array for the given page.
     *
     * @return array<string,mixed>
     */
    public function defaultConfig(int $pageId): array {
        return [
            'pageId' => $pageId,
            'slug' => '',
            'domain' => null,
            'metaTitle' => null,
            'metaDescription' => null,
            'canonicalUrl' => null,
            'robotsMeta' => null,
            'ogTitle' => null,
            'ogDescription' => null,
            'ogImage' => null,
            'faviconPath' => null,
            'schemaJson' => null,
            'hreflang' => null,
        ];
    }

    private function normalizeSchemaJson(?string $json): ?string {
        if ($json === null) {
            return null;
        }
        $trimmed = trim($json);
        if ($trimmed === '') {
            return null;
        }
        json_decode($trimmed);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }
        return $trimmed;
    }

    /**
     * Validate input data and return array of errors keyed by field.
     *
     * @param array<string,mixed> $data
     * @return array<string,string>
     */
    public function validate(array $data): array {
        $data['domain'] = $this->normalizeDomain(isset($data['domain']) ? (string) $data['domain'] : null);

        $rawFavicon = isset($data['faviconPath']) ? (string) $data['faviconPath'] : '';
        $normalizedFavicon = $this->normalizeFaviconPath($rawFavicon);
        if ($rawFavicon === '' || $normalizedFavicon !== null) {
            $data['faviconPath'] = $normalizedFavicon;
        }

        $errors = $this->validator->validate($data);

        if ($rawFavicon !== '' && $normalizedFavicon === null) {
            $errors['faviconPath'] = 'Favicon-Pfad: Ungültiger Pfad – nur relative Pfade oder HTTPS-URLs erlaubt.';
        }

        $slug = isset($data['slug']) ? (string) $data['slug'] : '';
        $pageId = isset($data['pageId']) ? (int) $data['pageId'] : 0;
        $domainKey = $data['domain'] ?? '';

        if ($slug !== '' && $pageId > 0) {
            $stmt = $this->pdo->prepare(
                'SELECT 1 FROM page_seo_config '
                . 'WHERE slug = ? AND COALESCE(domain, \'\') = COALESCE(?, \'\') AND page_id <> ?'
            );
            $stmt->execute([$slug, $domainKey, $pageId]);
            if ($stmt->fetchColumn() !== false) {
                $errors['slug'] = 'Slug: Dieser Slug ist bereits für diese Domain vergeben.';
            }
        }

        return $errors;
    }

    private function normalizeDomain(?string $domain): ?string {
        if ($domain === null) {
            return null;
        }

        $normalized = DomainNameHelper::normalize($domain);

        return $normalized === '' ? null : $normalized;
    }

    public function normalizeFaviconPath(?string $path): ?string {
        if ($path === null) {
            return null;
        }

        $trimmed = trim($path);
        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/\s/', $trimmed)) {
            return null;
        }

        if (str_contains($trimmed, '..')) {
            return null;
        }

        if (str_starts_with($trimmed, 'https://')) {
            return filter_var($trimmed, FILTER_VALIDATE_URL) ? $trimmed : null;
        }

        if ($trimmed[0] !== '/') {
            return null;
        }

        return $trimmed;
    }
}
