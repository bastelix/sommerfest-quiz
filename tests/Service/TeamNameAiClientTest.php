<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\RagChat\HttpChatResponder;
use App\Service\TeamNameAiClient;
use PHPUnit\Framework\TestCase;

use const JSON_THROW_ON_ERROR;
use function json_encode;
use function trim;

final class TeamNameAiClientTest extends TestCase
{
    public function testFetchSuggestionsProvidesContextSummary(): void
    {
        $responder = new class () extends HttpChatResponder {
            /**
             * @var list<array{role:string,content:string}>
             */
            public array $capturedMessages = [];

            /**
             * @var list<array<string, mixed>>
             */
            public array $capturedContext = [];

            public function __construct()
            {
            }

            public function respond(array $messages, array $context): string
            {
                $this->capturedMessages = $messages;
                $this->capturedContext = $context;

                return json_encode(['names' => ['AI Lumi', 'AI Nova']], JSON_THROW_ON_ERROR);
            }
        };

        $client = new TeamNameAiClient($responder);

        $result = $client->fetchSuggestions(2, [' nature ', 'science'], ['Playful ', ''], 'fr');

        self::assertSame(['AI Lumi', 'AI Nova'], $result);

        $context = $responder->capturedContext;
        self::assertNotEmpty($context, 'Context payload should not be empty.');

        $summary = $context[0]['text'] ?? '';
        self::assertNotSame('', trim((string) $summary));
        self::assertStringContainsString('Team name request: 2 suggestions for locale "fr".', $summary);
        self::assertStringContainsString('Domains: nature, science.', $summary);
        self::assertStringContainsString('Tones: Playful.', $summary);

        $metadata = $context[0]['metadata'] ?? [];
        self::assertSame(2, $metadata['count']);
        self::assertSame('fr', $metadata['locale']);
        self::assertSame(['nature', 'science'], $metadata['domains']);
        self::assertSame(['Playful'], $metadata['tones']);
    }
}
