<?php

declare(strict_types=1);

namespace App\Support;

final class BasePathHelper
{
    private function __construct()
    {
    }

    public static function normalize(?string $basePath): string
    {
        if ($basePath === null) {
            return '';
        }

        $basePath = trim($basePath);
        if ($basePath === '' || $basePath === '/') {
            return '';
        }

        $normalized = '/' . trim($basePath, '/');
        $normalized = preg_replace('#/+#', '/', $normalized) ?: '/';

        if ($normalized === '/' || $normalized === '') {
            return '';
        }

        return rtrim($normalized, '/');
    }
}
