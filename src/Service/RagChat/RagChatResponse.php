<?php

declare(strict_types=1);

namespace App\Service\RagChat;

/**
 * Aggregated chat response for API consumption.
 */
final class RagChatResponse
{
    /** @param list<RagChatContextItem> $context */
    public function __construct(
        private readonly string $question,
        private readonly string $answer,
        private readonly array $context
    ) {
    }

    public function getQuestion(): string
    {
        return $this->question;
    }

    public function getAnswer(): string
    {
        return $this->answer;
    }

    /**
     * @return list<RagChatContextItem>
     */
    public function getContext(): array
    {
        return $this->context;
    }
}
