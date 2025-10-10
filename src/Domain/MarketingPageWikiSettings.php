<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;

final class MarketingPageWikiSettings
{
    private int $pageId;

    private bool $active;

    private ?string $menuLabel;

    private ?DateTimeImmutable $updatedAt;

    public function __construct(int $pageId, bool $active, ?string $menuLabel, ?DateTimeImmutable $updatedAt)
    {
        $this->pageId = $pageId;
        $this->active = $active;
        $this->menuLabel = $menuLabel !== null && $menuLabel !== '' ? $menuLabel : null;
        $this->updatedAt = $updatedAt;
    }

    public function getPageId(): int
    {
        return $this->pageId;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getMenuLabel(): ?string
    {
        return $this->menuLabel;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function withStatus(bool $active, ?string $menuLabel): self
    {
        return new self($this->pageId, $active, $menuLabel, $this->updatedAt);
    }
}
