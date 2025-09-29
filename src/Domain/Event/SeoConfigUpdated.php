<?php

declare(strict_types=1);

namespace App\Domain\Event;

use App\Domain\PageSeoConfig;

/**
 * Event triggered when an existing SEO configuration is updated.
 */
class SeoConfigUpdated
{
    private PageSeoConfig $config;

    public function __construct(PageSeoConfig $config) {
        $this->config = $config;
    }

    public function getConfig(): PageSeoConfig {
        return $this->config;
    }
}
