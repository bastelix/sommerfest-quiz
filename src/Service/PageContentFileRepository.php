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
use function trim;

class PageContentFileRepository implements PageContentRepository
{
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

    private function resolvePath(Page $page, ?string $sourceReference): ?string
    {
        $reference = trim((string) $sourceReference);
        if ($reference !== '') {
            return $this->normalizePath($reference);
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
