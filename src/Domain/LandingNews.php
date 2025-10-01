<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;
use JsonSerializable;

/**
 * Represents a marketing news entry assigned to a landing page.
 */
class LandingNews implements JsonSerializable
{
    private int $id;

    private int $pageId;

    private string $pageSlug;

    private string $pageTitle;

    private string $slug;

    private string $title;

    private ?string $excerpt;

    private string $content;

    private ?DateTimeImmutable $publishedAt;

    private bool $isPublished;

    private DateTimeImmutable $createdAt;

    private DateTimeImmutable $updatedAt;

    public function __construct(
        int $id,
        int $pageId,
        string $pageSlug,
        string $pageTitle,
        string $slug,
        string $title,
        ?string $excerpt,
        string $content,
        ?DateTimeImmutable $publishedAt,
        bool $isPublished,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt
    ) {
        $this->id = $id;
        $this->pageId = $pageId;
        $this->pageSlug = $pageSlug;
        $this->pageTitle = $pageTitle;
        $this->slug = $slug;
        $this->title = $title;
        $this->excerpt = $excerpt;
        $this->content = $content;
        $this->publishedAt = $publishedAt;
        $this->isPublished = $isPublished;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getPageId(): int
    {
        return $this->pageId;
    }

    public function getPageSlug(): string
    {
        return $this->pageSlug;
    }

    public function getPageTitle(): string
    {
        return $this->pageTitle;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getExcerpt(): ?string
    {
        return $this->excerpt;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getPublishedAt(): ?DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function isPublished(): bool
    {
        return $this->isPublished;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[\ReturnTypeWillChange]
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'pageId' => $this->pageId,
            'pageSlug' => $this->pageSlug,
            'pageTitle' => $this->pageTitle,
            'slug' => $this->slug,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'content' => $this->content,
            'publishedAt' => $this->publishedAt?->format(DATE_ATOM),
            'isPublished' => $this->isPublished,
            'createdAt' => $this->createdAt->format(DATE_ATOM),
            'updatedAt' => $this->updatedAt->format(DATE_ATOM),
        ];
    }
}
