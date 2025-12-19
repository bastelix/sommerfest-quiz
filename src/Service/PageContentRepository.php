<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Page;

interface PageContentRepository
{
    public function supports(string $sourceType): bool;

    public function load(Page $page, ?string $sourceReference): ?string;
}
