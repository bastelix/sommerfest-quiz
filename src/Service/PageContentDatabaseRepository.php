<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Page;

class PageContentDatabaseRepository implements PageContentRepository
{
    public function supports(string $sourceType): bool
    {
        return $sourceType === PageContentLoader::SOURCE_DB;
    }

    public function load(Page $page, ?string $sourceReference): ?string
    {
        return $page->getContent();
    }
}
