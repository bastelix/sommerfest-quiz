<?php

declare(strict_types=1);

namespace App\Application\Seo;

use App\Application\Routing\RedirectManager;
use App\Domain\PageSeoConfig;
use App\Infrastructure\Cache\PageSeoCache;

/**
 * Handles loading, saving and validating SEO configuration for pages.
 */
class PageSeoConfigService
{
    private string $file;
    private RedirectManager $redirects;
    private SeoValidator $validator;
    private PageSeoCache $cache;

    public function __construct(
        ?string $file = null,
        ?RedirectManager $redirects = null,
        ?SeoValidator $validator = null,
        ?PageSeoCache $cache = null
    ) {
        $this->file = $file ?? dirname(__DIR__, 3) . '/data/page-seo.json';
        $this->redirects = $redirects ?? new RedirectManager();
        $this->validator = $validator ?? new SeoValidator();
        $this->cache = $cache ?? new PageSeoCache();
    }

    public function load(int $pageId): ?PageSeoConfig
    {
        $cached = $this->cache->get($pageId);
        if ($cached !== null) {
            return $cached;
        }
        if (!is_file($this->file)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($this->file), true);
        if (!is_array($data) || !isset($data[$pageId]) || !is_array($data[$pageId])) {
            return null;
        }
        $cfg = $data[$pageId];
        $config = new PageSeoConfig(
            $pageId,
            (string) ($cfg['slug'] ?? ''),
            $cfg['metaTitle'] ?? null,
            $cfg['metaDescription'] ?? null,
            $cfg['canonicalUrl'] ?? null,
            $cfg['robotsMeta'] ?? null,
            $cfg['ogTitle'] ?? null,
            $cfg['ogDescription'] ?? null,
            $cfg['ogImage'] ?? null,
            $cfg['schemaJson'] ?? null,
            $cfg['hreflang'] ?? null
        );
        $this->cache->set($config);
        return $config;
    }

    public function save(PageSeoConfig $config): void
    {
        $data = [];
        if (is_file($this->file)) {
            $json = json_decode((string) file_get_contents($this->file), true);
            if (is_array($json)) {
                $data = $json;
            }
        }
        $existing = $data[$config->getPageId()] ?? null;
        if (is_array($existing)) {
            $oldSlug = $existing['slug'] ?? null;
            $oldCanonical = $existing['canonicalUrl'] ?? null;
            if ($oldSlug && $oldSlug !== $config->getSlug()) {
                $this->redirects->register('/' . ltrim((string) $oldSlug, '/'), '/' . ltrim($config->getSlug(), '/'));
            }
            if ($oldCanonical && $config->getCanonicalUrl() && $oldCanonical !== $config->getCanonicalUrl()) {
                $this->redirects->register($oldCanonical, (string) $config->getCanonicalUrl());
            }
        }
        $data[$config->getPageId()] = $config->jsonSerialize();
        file_put_contents($this->file, json_encode($data, JSON_PRETTY_PRINT) . "\n");
        $this->cache->invalidate($config->getPageId());
    }

    /**
     * Validate input data and return array of errors keyed by field.
     *
     * @param array<string,mixed> $data
     * @return array<string,string>
     */
    public function validate(array $data): array
    {
        return $this->validator->validate($data);
    }
}
