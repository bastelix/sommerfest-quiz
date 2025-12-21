<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;

final class MarketingPageMenuItem
{
    private int $id;

    private int $pageId;

    private string $namespace;

    private string $label;

    private string $href;

    private ?string $icon;

    private int $position;

    private bool $isExternal;

    private string $locale;

    private bool $isActive;

    private ?DateTimeImmutable $updatedAt;

    public function __construct(
        int $id,
        int $pageId,
        string $namespace,
        string $label,
        string $href,
        ?string $icon,
        int $position,
        bool $isExternal,
        string $locale,
        bool $isActive,
        ?DateTimeImmutable $updatedAt
    ) {
        $this->id = $id;
        $this->pageId = $pageId;
        $this->namespace = $namespace;
        $this->label = $label;
        $this->href = $href;
        $this->icon = $icon !== '' ? $icon : null;
        $this->position = $position;
        $this->isExternal = $isExternal;
        $this->locale = $locale;
        $this->isActive = $isActive;
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

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
