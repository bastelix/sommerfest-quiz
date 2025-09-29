<?php

declare(strict_types=1);

namespace App\Controller\Marketing;

use App\Application\Seo\PageSeoConfigService;
use App\Service\PageService;

class LandingController extends MarketingPageController
{
    public function __construct(?PageService $pages = null, ?PageSeoConfigService $seo = null) {
        parent::__construct('landing', $pages, $seo);
    }
}
