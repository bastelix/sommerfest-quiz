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

    private string $namespace;

    private string $slug;

    private string $title;

    private string $content;

    private ?string $type;

    private ?int $parentId;

    private int $sortOrder;

    private ?string $status;

    private ?string $language;

    private ?string $contentSource;

    public function __construct(
        int $id,
        string $namespace,
        string $slug,
        string $title,
        string $content,
        ?string $type,
        ?int $parentId,
        int $sortOrder,
        ?string $status,
        ?string $language,
        ?string $contentSource
    ) {
        $this->id = $id;
        $this->namespace = $namespace;
        $this->slug = $slug;
        $this->title = $title;
        $this->content = $content;
        $this->type = $type;
        $this->parentId = $parentId;
        $this->sortOrder = $sortOrder;
        $this->status = $status;
        $this->language = $language;
        $this->contentSource = $contentSource;
    }

    public function getId(): int {
        return $this->id;
    }

    public function getNamespace(): string {
        return $this->namespace;
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

    public function getType(): ?string {
        return $this->type;
    }

    public function getParentId(): ?int {
        return $this->parentId;
    }

    public function getSortOrder(): int {
        return $this->sortOrder;
    }

    public function getStatus(): ?string {
        return $this->status;
    }

    public function getLanguage(): ?string {
        return $this->language;
    }

    public function getContentSource(): ?string {
        return $this->contentSource;
    }

    #[\ReturnTypeWillChange]
    public function jsonSerialize(): array {
        return [
            'id' => $this->id,
            'namespace' => $this->namespace,
            'slug' => $this->slug,
            'title' => $this->title,
            'content' => $this->content,
            'type' => $this->type,
            'parent_id' => $this->parentId,
            'sort_order' => $this->sortOrder,
            'status' => $this->status,
            'language' => $this->language,
            'content_source' => $this->contentSource,
        ];
    }
}
