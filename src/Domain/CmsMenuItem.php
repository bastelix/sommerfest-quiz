<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;

final class CmsMenuItem
{
    private int $id;

    private int $menuId;

    private ?int $parentId;

    private string $namespace;

    private string $label;

    private string $href;

    private ?string $icon;

    private int $position;

    private bool $isExternal;

    private string $locale;

    private bool $isActive;

    private string $layout;

    private ?string $detailTitle;

    private ?string $detailText;

    private ?string $detailSubline;

    private bool $isStartpage;

    private ?DateTimeImmutable $updatedAt;

    public function __construct(
        int $id,
        int $menuId,
        ?int $parentId,
        string $namespace,
        string $label,
        string $href,
        ?string $icon,
        int $position,
        bool $isExternal,
        string $locale,
        bool $isActive,
        string $layout,
        ?string $detailTitle,
        ?string $detailText,
        ?string $detailSubline,
        bool $isStartpage,
        ?DateTimeImmutable $updatedAt
    ) {
        $this->id = $id;
        $this->menuId = $menuId;
        $this->parentId = $parentId;
        $this->namespace = $namespace;
        $this->label = $label;
        $this->href = $href;
        $this->icon = $icon !== '' ? $icon : null;
        $this->position = $position;
        $this->isExternal = $isExternal;
        $this->locale = $locale;
        $this->isActive = $isActive;
        $this->layout = $layout;
        $this->detailTitle = $detailTitle !== '' ? $detailTitle : null;
        $this->detailText = $detailText !== '' ? $detailText : null;
        $this->detailSubline = $detailSubline !== '' ? $detailSubline : null;
        $this->isStartpage = $isStartpage;
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

    public function getParentId(): ?int
    {
        return $this->parentId;
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getHref(): string
    {
        return $this->href;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function isExternal(): bool
    {
        return $this->isExternal;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getLayout(): string
    {
        return $this->layout;
    }

    public function getDetailTitle(): ?string
    {
        return $this->detailTitle;
    }

    public function getDetailText(): ?string
    {
        return $this->detailText;
    }

    public function getDetailSubline(): ?string
    {
        return $this->detailSubline;
    }

    public function isStartpage(): bool
    {
        return $this->isStartpage;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
