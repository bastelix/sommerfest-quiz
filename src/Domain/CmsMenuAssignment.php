<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;

final class CmsMenuAssignment
{
    private int $id;

    private int $menuId;

    private ?int $pageId;

    private string $namespace;

    private string $slot;

    private string $locale;

    private bool $isActive;

    private ?DateTimeImmutable $updatedAt;

    public function __construct(
        int $id,
        int $menuId,
        ?int $pageId,
        string $namespace,
        string $slot,
        string $locale,
        bool $isActive,
        ?DateTimeImmutable $updatedAt
    ) {
        $this->id = $id;
        $this->menuId = $menuId;
        $this->pageId = $pageId;
        $this->namespace = $namespace;
        $this->slot = $slot;
        $this->locale = $locale;
        $this->isActive = $isActive;
        $this->updatedAt = $updatedAt;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getMenuId(): int
    {
        return $this->menuId;
    }

    public function getPageId(): ?int
    {
        return $this->pageId;
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function getSlot(): string
    {
        return $this->slot;
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
