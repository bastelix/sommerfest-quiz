<?php

declare(strict_types=1);

namespace Tests\Stubs;

use App\Service\RagChat\ChatResponderInterface;

final class StaticChatResponder implements ChatResponderInterface
{
    public function __construct(private string $response)
    {
    }

    public function respond(array $messages, array $context = []): string
    {
        return $this->response;
    }
}
