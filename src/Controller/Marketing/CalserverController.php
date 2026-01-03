<?php

declare(strict_types=1);

namespace App\Controller\Marketing;

use App\Application\Seo\PageSeoConfigService;
use App\Service\PageService;

class CalserverController extends CmsPageController
{
    public function __construct(?PageService $pages = null, ?PageSeoConfigService $seo = null) {
        parent::__construct('calserver', $pages, $seo);
    }
}
