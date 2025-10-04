<?php

declare(strict_types=1);

namespace App\Service\RagChat;

/**
 * Context item returned to the frontend for rendering.
 */
final class RagChatContextItem
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        private readonly string $label,
        private readonly string $snippet,
        private readonly float $score,
        private readonly array $metadata
    ) {
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getSnippet(): string
    {
        return $this->snippet;
    }

    public function getScore(): float
    {
        return $this->score;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }
}
