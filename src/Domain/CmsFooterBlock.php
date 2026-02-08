<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;

final class CmsFooterBlock
{
    private int $id;
    private string $namespace;
    private string $slot;
    private string $type;
    /** @var array<string, mixed> */
    private array $content;
    private int $position;
    private string $locale;
    private bool $isActive;
    private ?DateTimeImmutable $updatedAt;

    /**
     * @param array<string, mixed> $content
     */
    public function __construct(
        int $id,
        string $namespace,
        string $slot,
        string $type,
        array $content,
        int $position,
        string $locale,
        bool $isActive,
        ?DateTimeImmutable $updatedAt
    ) {
        $this->id = $id;
        $this->namespace = $namespace;
        $this->slot = $slot;
        $this->type = $type;
        $this->content = $content;
        $this->position = $position;
        $this->locale = $locale;
        $this->isActive = $isActive;
        $this->updatedAt = $updatedAt;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function getSlot(): string
    {
        return $this->slot;
    }

    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContent(): array
    {
        return $this->content;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
