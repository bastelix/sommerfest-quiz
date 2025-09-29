<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

use App\Domain\PageSeoConfig;

/**
 * Simple in-memory cache for SEO metadata.
 */
class PageSeoCache
{
    /** @var array<int, PageSeoConfig> */
    private array $cache = [];

    public function get(int $pageId): ?PageSeoConfig {
        return $this->cache[$pageId] ?? null;
    }

    public function set(PageSeoConfig $config): void {
        $this->cache[$config->getPageId()] = $config;
    }

    public function invalidate(int $pageId): void {
        unset($this->cache[$pageId]);
    }
}
