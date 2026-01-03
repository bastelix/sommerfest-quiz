<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;
use JsonSerializable;

final class CmsPageWikiArticle implements JsonSerializable
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ARCHIVED = 'archived';

    private int $id;

    private int $pageId;

    private string $slug;

    private string $locale;

    private string $title;

    private ?string $excerpt;

    /** @var array<string,mixed>|null */
    private ?array $editorState;

    private string $contentMarkdown;

    private string $contentHtml;

    private string $status;

    private int $sortIndex;

    private bool $isStartDocument;

    private ?DateTimeImmutable $publishedAt;

    private ?DateTimeImmutable $updatedAt;

    /**
     * @param array<string,mixed>|null $editorState
     */
    public function __construct(
        int $id,
        int $pageId,
        string $slug,
        string $locale,
        string $title,
        ?string $excerpt,
        ?array $editorState,
        string $contentMarkdown,
        string $contentHtml,
        string $status,
        int $sortIndex,
        bool $isStartDocument,
        ?DateTimeImmutable $publishedAt,
        ?DateTimeImmutable $updatedAt
    ) {
        $this->id = $id;
        $this->pageId = $pageId;
        $this->slug = $slug;
        $this->locale = $locale;
        $this->title = $title;
        $this->excerpt = $excerpt !== null && $excerpt !== '' ? $excerpt : null;
        $this->editorState = $editorState;
        $this->contentMarkdown = $contentMarkdown;
        $this->contentHtml = $contentHtml;
        $this->status = $status;
        $this->sortIndex = $sortIndex;
        $this->isStartDocument = $isStartDocument;
        $this->publishedAt = $publishedAt;
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

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getExcerpt(): ?string
    {
        return $this->excerpt;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function getEditorState(): ?array
    {
        return $this->editorState;
    }

    public function getContentMarkdown(): string
    {
        return $this->contentMarkdown;
    }

    public function getContentHtml(): string
    {
        return $this->contentHtml;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getSortIndex(): int
    {
        return $this->sortIndex;
    }

    public function isStartDocument(): bool
    {
        return $this->isStartDocument;
    }

    public function getPublishedAt(): ?DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function withStatus(string $status, ?DateTimeImmutable $publishedAt): self
    {
        return new self(
            $this->id,
            $this->pageId,
            $this->slug,
            $this->locale,
            $this->title,
            $this->excerpt,
            $this->editorState,
            $this->contentMarkdown,
            $this->contentHtml,
            $status,
            $this->sortIndex,
            $this->isStartDocument,
            $publishedAt,
            $this->updatedAt
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'pageId' => $this->pageId,
            'slug' => $this->slug,
            'locale' => $this->locale,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'editorState' => $this->editorState,
            'contentMarkdown' => $this->contentMarkdown,
            'contentHtml' => $this->contentHtml,
            'status' => $this->status,
            'sortIndex' => $this->sortIndex,
            'isStartDocument' => $this->isStartDocument,
            'publishedAt' => $this->publishedAt?->format(DateTimeImmutable::ATOM),
            'updatedAt' => $this->updatedAt?->format(DateTimeImmutable::ATOM),
        ];
    }
}
