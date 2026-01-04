<?php

declare(strict_types=1);

namespace App\Service;

use App\Infrastructure\Database;
use PDO;

/**
 * Provide namespace-scoped CMS menu data.
 */
class CmsMenuService
{
    private CmsPageMenuService $cmsPageMenu;

    public function __construct(?PDO $pdo = null, ?CmsPageMenuService $cmsPageMenu = null)
    {
        $pdo = $pdo ?? Database::connectFromEnv();
        $this->cmsPageMenu = $cmsPageMenu ?? new CmsPageMenuService($pdo);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getMenuForNamespace(string $namespace, ?string $locale = null): array
    {
        $startpageSlug = $this->cmsPageMenu->resolveStartpageSlug($namespace, $locale);
        if ($startpageSlug === null) {
            return [];
        }

        return $this->cmsPageMenu->getMenuTreeForSlug($namespace, $startpageSlug, $locale, true);
    }
}
