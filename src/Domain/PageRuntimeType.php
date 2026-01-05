<?php

declare(strict_types=1);

namespace App\Domain;

final class PageRuntimeType
{
    public const LEGACY_MARKETING = 'legacy_marketing';
    public const CMS_BUILDER = 'cms_builder';
    public const SYSTEM = 'system';
}
