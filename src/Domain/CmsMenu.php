<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;

final class CmsMenu
{
    private int $id;

    private string $namespace;

    private string $label;

    private string $locale;

    private bool $isActive;

    private ?DateTimeImmutable $updatedAt;

    public function __construct(
        int $id,
        string $namespace,
        string $label,
        string $locale,
        bool $isActive,
        ?DateTimeImmutable $updatedAt
    ) {
        $this->id = $id;
        $this->namespace = $namespace;
        $this->label = $label;
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

    public function getLabel(): string
    {
        return $this->label;
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
