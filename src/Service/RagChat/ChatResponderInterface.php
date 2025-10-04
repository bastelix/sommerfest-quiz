<?php

declare(strict_types=1);

namespace App\Service\RagChat;

/**
 * Provides answers for the marketing RAG chatbot.
 */
interface ChatResponderInterface
{
    /**
     * @param list<array{role:string,content:string}> $messages
     * @param list<array<string,mixed>|mixed> $context
     */
    public function respond(array $messages, array $context): string;
}
