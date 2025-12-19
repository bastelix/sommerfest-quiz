<?php

declare(strict_types=1);

namespace App\Service\RagChat;

interface RagChatServiceInterface
{
    public function answer(string $question, string $locale = 'de', ?string $domain = null): RagChatResponse;
}
