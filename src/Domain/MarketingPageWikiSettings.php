<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;

final class MarketingPageWikiSettings
{
    private int $pageId;

    private bool $active;

    private ?string $menuLabel;

    /** @var array<string, string> */
    private array $menuLabels;

    private ?DateTimeImmutable $updatedAt;

    /**
     * @param array<string, string> $menuLabels
     */
    public function __construct(
        int $pageId,
        bool $active,
        ?string $menuLabel,
        array $menuLabels,
        ?DateTimeImmutable $updatedAt
    )
    {
        $this->pageId = $pageId;
        $this->active = $active;
        $this->menuLabel = $menuLabel !== null && $menuLabel !== '' ? $menuLabel : null;
        $this->menuLabels = $menuLabels;
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

    /**
     * @return array<string, string>
     */
    public function getMenuLabels(): array
    {
        return $this->menuLabels;
    }

    public function getMenuLabelForLocale(string $locale, string $fallbackLocale = 'de'): ?string
    {
        $normalized = strtolower(trim($locale));
        if ($normalized === '') {
            $normalized = $fallbackLocale;
        }
        if (str_contains($normalized, '-')) {
            $normalized = substr($normalized, 0, 2);
        }

        if (isset($this->menuLabels[$normalized])) {
            return $this->menuLabels[$normalized];
        }

        if (isset($this->menuLabels[$fallbackLocale])) {
            return $this->menuLabels[$fallbackLocale];
        }

        return $this->menuLabel;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @param array<string, string> $menuLabels
     */
    public function withStatus(bool $active, ?string $menuLabel, array $menuLabels): self
    {
        return new self($this->pageId, $active, $menuLabel, $menuLabels, $this->updatedAt);
    }
}
