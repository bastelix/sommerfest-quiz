<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Page;

use function str_starts_with;
use function strtolower;
use function substr;
use function trim;

class PageContentLoader
{
    public const SOURCE_DB = 'db';
    public const SOURCE_FILE = 'file';

    /** @var PageContentRepository[] */
    private array $repositories;

    /**
     * @param PageContentRepository[]|null $repositories
     */
    public function __construct(?array $repositories = null)
    {
        $this->repositories = $repositories ?? [
            new PageContentDatabaseRepository(),
            new PageContentFileRepository(),
        ];
    }

    public function load(Page $page): string
    {
        $source = $this->parseContentSource($page->getContentSource());
        foreach ($this->repositories as $repository) {
            if (!$repository->supports($source['type'])) {
                continue;
            }

            $content = $repository->load($page, $source['reference']);
            if ($content !== null) {
                return $content;
            }
        }

        return $page->getContent();
    }

    /**
     * @return array{type: string, reference: string|null}
     */
    private function parseContentSource(?string $contentSource): array
    {
        $raw = trim((string) $contentSource);
        if ($raw === '') {
            return ['type' => self::SOURCE_DB, 'reference' => null];
        }

        $normalized = strtolower($raw);
        if ($normalized === 'db' || $normalized === 'database') {
            return ['type' => self::SOURCE_DB, 'reference' => null];
        }

        if ($normalized === self::SOURCE_FILE) {
            return ['type' => self::SOURCE_FILE, 'reference' => null];
        }

        if (str_starts_with($normalized, self::SOURCE_FILE . ':')) {
            $reference = trim(substr($raw, 5));
            if ($reference === '') {
                $reference = null;
            }

            return ['type' => self::SOURCE_FILE, 'reference' => $reference];
        }

        return ['type' => self::SOURCE_DB, 'reference' => null];
    }
}
