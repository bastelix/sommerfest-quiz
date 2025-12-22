<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Page;

use function dirname;
use function file_get_contents;
use function is_readable;
use function ltrim;
use function preg_match;
use function rtrim;
use function strtolower;
use function trim;

class PageContentFileRepository implements PageContentRepository
{
    private const FALLBACK_FILES = [
        'calhelp' => 'content/marketing/calhelp.html',
        'calserver' => 'content/marketing/calserver.html',
    ];

    private string $projectRoot;

    public function __construct(?string $projectRoot = null)
    {
        $this->projectRoot = $projectRoot ?? dirname(__DIR__, 2);
    }

    public function supports(string $sourceType): bool
    {
        return $sourceType === PageContentLoader::SOURCE_FILE;
    }

    public function load(Page $page, ?string $sourceReference): ?string
    {
        $path = $this->resolvePath($page, $sourceReference);
        if ($path === null || !is_readable($path)) {
            return null;
        }

        $content = file_get_contents($path);
        if ($content === false) {
            return null;
        }

        return $content;
    }

    public static function hasFallbackForSlug(string $slug): bool
    {
        $normalized = strtolower(trim($slug));
        if ($normalized === '') {
            return false;
        }

        return isset(self::FALLBACK_FILES[$normalized]);
    }

    private function resolvePath(Page $page, ?string $sourceReference): ?string
    {
        $reference = trim((string) $sourceReference);
        if ($reference !== '') {
            return $this->normalizePath($reference);
        }

        $slug = $page->getSlug();
        if (isset(self::FALLBACK_FILES[$slug])) {
            return $this->normalizePath(self::FALLBACK_FILES[$slug]);
        }

        return null;
    }

    private function normalizePath(string $path): ?string
    {
        $trimmed = trim($path);
        if ($trimmed === '') {
            return null;
        }

        $trimmed = ltrim($trimmed, '/');
        if (preg_match('/\.\.[\/\\\\]/', $trimmed) === 1) {
            return null;
        }

        return rtrim($this->projectRoot, '/') . '/' . $trimmed;
    }
}
