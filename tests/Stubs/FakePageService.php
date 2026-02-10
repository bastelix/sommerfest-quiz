<?php

declare(strict_types=1);

namespace Tests\Stubs;

use App\Domain\Page;
use App\Service\PageService;

final class FakePageService extends PageService
{
    /** @var Page[] */
    private array $pages;

    /** @var list<array{namespace:string,slug:string,content:string}> */
    public array $savedContent = [];

    /** @param Page[] $pages */
    public function __construct(array $pages)
    {
        $this->pages = $pages;
    }

    /** @return Page[] */
    public function getAll(): array
    {
        return $this->pages;
    }

    public function save(string $namespace, string $slug, string $content): void
    {
        $this->savedContent[] = [
            'namespace' => $namespace,
            'slug' => $slug,
            'content' => $content,
        ];
    }
}
