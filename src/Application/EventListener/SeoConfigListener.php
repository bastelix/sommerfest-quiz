<?php

declare(strict_types=1);

namespace App\Application\EventListener;

use App\Domain\Event\SeoConfigSaved;
use App\Domain\Event\SeoConfigUpdated;
use App\Infrastructure\Cache\PageSeoCache;
use App\Infrastructure\Event\EventDispatcher;

/**
 * Handles actions that should occur after SEO configuration changes.
 */
class SeoConfigListener
{
    private PageSeoCache $cache;

    public function __construct(PageSeoCache $cache) {
        $this->cache = $cache;
    }

    public static function register(EventDispatcher $dispatcher, PageSeoCache $cache): void {
        $listener = new self($cache);
        $dispatcher->addListener(SeoConfigSaved::class, [$listener, 'onSaved']);
        $dispatcher->addListener(SeoConfigUpdated::class, [$listener, 'onUpdated']);
    }

    public function onSaved(SeoConfigSaved $event): void {
        // Invalidate cache and trigger any additional hooks such as search-console pings.
        $this->cache->invalidate($event->getConfig()->getPageId());
    }

    public function onUpdated(SeoConfigUpdated $event): void {
        // Invalidate cache and trigger any additional hooks such as search-console pings.
        $this->cache->invalidate($event->getConfig()->getPageId());
    }
}
