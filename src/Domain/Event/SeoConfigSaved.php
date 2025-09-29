<?php

declare(strict_types=1);

namespace App\Domain\Event;

use App\Domain\PageSeoConfig;

/**
 * Event triggered when a SEO configuration is initially saved.
 */
class SeoConfigSaved
{
    private PageSeoConfig $config;

    public function __construct(PageSeoConfig $config) {
        $this->config = $config;
    }

    public function getConfig(): PageSeoConfig {
        return $this->config;
    }
}
