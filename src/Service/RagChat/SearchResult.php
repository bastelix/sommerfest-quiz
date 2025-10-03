<?php

declare(strict_types=1);

namespace App\Service\RagChat;

/**
 * Value object representing a relevant chunk from the semantic index.
 */
final class SearchResult
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        private readonly string $chunkId,
        private readonly float $score,
        private readonly string $text,
        private readonly array $metadata
    ) {
    }

    public function getChunkId(): string
    {
        return $this->chunkId;
    }

    public function getScore(): float
    {
        return $this->score;
    }

    public function getText(): string
    {
        return $this->text;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }
}
