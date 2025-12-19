<?php

declare(strict_types=1);

namespace App\Domain;

use JsonSerializable;

/**
 * Represents a page module attached to a marketing page.
 */
class PageModule implements JsonSerializable
{
    private int $id;

    private int $pageId;

    private string $type;

    /** @var array<string, mixed> */
    private array $config;

    private string $position;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(int $id, int $pageId, string $type, array $config, string $position) {
        $this->id = $id;
        $this->pageId = $pageId;
        $this->type = $type;
        $this->config = $config;
        $this->position = $position;
    }

    public function getId(): int {
        return $this->id;
    }

    public function getPageId(): int {
        return $this->pageId;
    }

    public function getType(): string {
        return $this->type;
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array {
        return $this->config;
    }

    public function getPosition(): string {
        return $this->position;
    }

    #[\ReturnTypeWillChange]
    public function jsonSerialize(): array {
        return [
            'id' => $this->id,
            'page_id' => $this->pageId,
            'type' => $this->type,
            'config' => $this->config,
            'position' => $this->position,
        ];
    }
}
