<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Provides the current application version.
 */
class VersionService
{
    /**
     * Return the current application version.
     */
    public function getCurrentVersion(): string {
        $root = dirname(__DIR__, 2);

        $versionFile = $root . '/VERSION';
        if (is_readable($versionFile)) {
            $version = trim(file_get_contents($versionFile));
            if ($version !== '') {
                return $version;
            }
        }

        $path = $root . '/CHANGELOG.md';
        if (is_readable($path)) {
            $content = file_get_contents($path);
            if ($content !== false && preg_match('/^## \[(\d+\.\d+\.\d+)\]/m', $content, $m)) {
                return $m[1];
            }
        }

        return 'dev';
    }
}
