<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;

final class CmsPageWikiVersion
{
    private int $id;

    private int $articleId;

    /** @var array<string,mixed>|null */
    private ?array $editorState;

    private string $contentMarkdown;

    private DateTimeImmutable $createdAt;

    private ?string $createdBy;

    /**
     * @param array<string,mixed>|null $editorState
     */
    public function __construct(
        int $id,
        int $articleId,
        ?array $editorState,
        string $contentMarkdown,
        DateTimeImmutable $createdAt,
        ?string $createdBy
    ) {
        $this->id = $id;
        $this->articleId = $articleId;
        $this->editorState = $editorState;
        $this->contentMarkdown = $contentMarkdown;
        $this->createdAt = $createdAt;
        $this->createdBy = $createdBy;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getArticleId(): int
    {
        return $this->articleId;
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

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getCreatedBy(): ?string
    {
        return $this->createdBy;
    }
}
