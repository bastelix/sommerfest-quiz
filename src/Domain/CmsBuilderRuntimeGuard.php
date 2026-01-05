<?php

declare(strict_types=1);

namespace App\Domain;

use LogicException;

final class CmsBuilderRuntimeGuard
{
    public static function assert(Page $page, string $runtimeType, string $rawContent, bool $fallbackUsed): void
    {
        if ($runtimeType !== PageRuntimeType::CMS_BUILDER) {
            return;
        }

        if (self::containsLegacyHtml($rawContent)) {
            throw new LogicException('CmsBuilderPage must not contain legacy HTML content.');
        }

        if (self::containsMarketingPlaceholders($rawContent)) {
            throw new LogicException('CmsBuilderPage must not rely on marketing placeholder replacement.');
        }

        if ($fallbackUsed) {
            throw new LogicException('CmsBuilderPage must not use resolved fallback content.');
        }
    }

    private static function containsLegacyHtml(string $rawContent): bool
    {
        $lowered = strtolower($rawContent);

        return str_contains($lowered, '<html') || str_contains($lowered, '<body');
    }

    private static function containsMarketingPlaceholders(string $rawContent): bool
    {
        return str_contains($rawContent, '{{ basePath }}')
            || str_contains($rawContent, '{{ csrf_token }}')
            || str_contains($rawContent, '{{ turnstile_widget }}')
            || str_contains($rawContent, '__CALHELP_NEWS_SECTION__');
    }
}
