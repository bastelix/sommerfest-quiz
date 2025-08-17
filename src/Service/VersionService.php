<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Provides the current application version.
 */
class VersionService
{
    /**
     * Return the latest version string from the changelog.
     */
    public function getCurrentVersion(): string
    {
        $path = dirname(__DIR__, 2) . '/CHANGELOG.md';
        if (is_readable($path)) {
            $content = file_get_contents($path);
            if ($content !== false && preg_match('/^## \[(\d+\.\d+\.\d+)\]/m', $content, $m)) {
                return $m[1];
            }
        }
        return 'dev';
    }
}
