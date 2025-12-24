<?php

declare(strict_types=1);

namespace App\Support;

final class FeatureFlags
{
    public const FEATURE_WIKI = 'FEATURE_WIKI_ENABLED';
    public const FEATURE_MARKETING_NAV_TREE = 'FEATURE_MARKETING_NAV_TREE_ENABLED';

    public static function wikiEnabled(): bool
    {
        $value = getenv(self::FEATURE_WIKI);
        if ($value === false) {
            return true;
        }

        $normalized = strtolower(trim((string) $value));
        if ($normalized === '') {
            return true;
        }

        $truthy = ['1', 'true', 'on', 'yes'];

        if (in_array($normalized, $truthy, true)) {
            return true;
        }

        $falsy = ['0', 'false', 'off', 'no'];

        if (in_array($normalized, $falsy, true)) {
            return false;
        }

        $parsed = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        return $parsed ?? true;
    }

    public static function marketingNavigationTreeEnabled(): bool
    {
        $value = getenv(self::FEATURE_MARKETING_NAV_TREE);
        if ($value === false) {
            return true;
        }

        $normalized = strtolower(trim((string) $value));
        if ($normalized === '') {
            return true;
        }

        $truthy = ['1', 'true', 'on', 'yes'];
        if (in_array($normalized, $truthy, true)) {
            return true;
        }

        $falsy = ['0', 'false', 'off', 'no'];
        if (in_array($normalized, $falsy, true)) {
            return false;
        }

        $parsed = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        return $parsed ?? true;
    }
}
