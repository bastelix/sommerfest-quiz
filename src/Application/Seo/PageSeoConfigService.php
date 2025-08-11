<?php

declare(strict_types=1);

namespace App\Application\Seo;

use App\Application\Routing\RedirectManager;
use App\Domain\PageSeoConfig;

/**
 * Handles loading, saving and validating SEO configuration for pages.
 */
class PageSeoConfigService
{
    private string $file;
    private RedirectManager $redirects;

    public function __construct(?string $file = null, ?RedirectManager $redirects = null)
    {
        $this->file = $file ?? dirname(__DIR__, 3) . '/data/page-seo.json';
        $this->redirects = $redirects ?? new RedirectManager();
    }

    public function load(int $pageId): ?PageSeoConfig
    {
        if (!is_file($this->file)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($this->file), true);
        if (!is_array($data) || !isset($data[$pageId]) || !is_array($data[$pageId])) {
            return null;
        }
        $cfg = $data[$pageId];
        return new PageSeoConfig(
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
    }

    /**
     * Validate input data and return array of errors keyed by field.
     *
     * @param array<string,mixed> $data
     * @return array<string,string>
     */
    public function validate(array $data): array
    {
        $errors = [];
        $slug = (string) ($data['slug'] ?? '');
        if ($slug === '') {
            $errors['slug'] = 'Slug is required';
        } elseif (!preg_match('/^[a-z0-9\-]+$/', $slug)) {
            $errors['slug'] = 'Slug must contain lowercase letters, numbers and dashes only';
        }
        $canonical = $data['canonicalUrl'] ?? null;
        if ($canonical !== null && $canonical !== '' && filter_var($canonical, FILTER_VALIDATE_URL) === false) {
            $errors['canonicalUrl'] = 'Invalid URL';
        }
        return $errors;
    }
}
