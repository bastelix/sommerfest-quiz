<?php

declare(strict_types=1);

namespace App\Domain;

use JsonSerializable;

/**
 * Represents a static page in the application.
 */
class Page implements JsonSerializable
{
    private int $id;

    private string $slug;

    private string $title;

    private string $content;

    public function __construct(int $id, string $slug, string $title, string $content) {
        $this->id = $id;
        $this->slug = $slug;
        $this->title = $title;
        $this->content = $content;
    }

    public function getId(): int {
        return $this->id;
    }

    public function getSlug(): string {
        return $this->slug;
    }

    public function getTitle(): string {
        return $this->title;
    }

    public function getContent(): string {
        return $this->content;
    }

    #[\ReturnTypeWillChange]
    public function jsonSerialize(): array {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'content' => $this->content,
        ];
    }
}
