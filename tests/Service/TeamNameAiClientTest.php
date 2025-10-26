<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\RagChat\HttpChatResponder;
use App\Service\TeamNameAiClient;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use const JSON_THROW_ON_ERROR;
use function json_encode;
use function mb_strtolower;
use function mb_substr;
use function preg_replace;
use function preg_split;
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

        $messages = $responder->capturedMessages;
        self::assertCount(2, $messages);

        $userPrompt = $messages[1]['content'] ?? '';
        self::assertStringContainsString('Erfinde 2 einzigartige, familienfreundliche Spielernamen zum Thema nature und science (Stimmung: Playful)', $userPrompt);
        self::assertStringContainsString('Stil: humorvoll, cleveres Wortspiel, kurze Alliteration ok.', $userPrompt);
        self::assertStringContainsString('Sprache: Deutsch.', $userPrompt);
        self::assertStringContainsString('Formate: nur JSON-Array aus Strings, keine Erklärungen.', $userPrompt);
        self::assertStringContainsString('Optional: Beziehe folgende Sportarten/Begriffe ein: nature und science.', $userPrompt);
        self::assertStringContainsString('Nutze ausschließlich die Sprache "fr".', $userPrompt);
        self::assertStringContainsString('Beispiele für den gewünschten Ton (nicht wiederverwenden):', $userPrompt);

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

    public function testFetchSuggestionsRecordsErrorState(): void
    {
        $responder = new class () extends HttpChatResponder {
            public function __construct()
            {
            }

            public function respond(array $messages, array $context): string
            {
                throw new RuntimeException('Gateway timeout');
            }
        };

        $client = new TeamNameAiClient($responder);

        $result = $client->fetchSuggestions(3, [], [], 'de');

        self::assertSame([], $result);
        self::assertNotNull($client->getLastResponseAt());
        self::assertNull($client->getLastSuccessAt());
        self::assertSame('Gateway timeout', $client->getLastError());
    }

    public function testFetchSuggestionsMixesSimilarPrefixes(): void
    {
        $responder = new class () extends HttpChatResponder {
            /**
             * @var list<array{role:string,content:string}>
             */
            public array $capturedMessages = [];

            public function __construct()
            {
            }

            public function respond(array $messages, array $context): string
            {
                $this->capturedMessages = $messages;

                return json_encode([
                    'names' => [
                        'Berg Bären',
                        'Berg Bande',
                        'Berg Blitz',
                        'Meer Magie',
                        'Meer Mond',
                    ],
                ], JSON_THROW_ON_ERROR);
            }
        };

        $client = new TeamNameAiClient($responder);

        $result = $client->fetchSuggestions(5, [], [], 'de');

        self::assertCount(5, $result);
        for ($index = 1; $index < count($result); $index++) {
            $previous = self::normalizePrefix($result[$index - 1]);
            $current = self::normalizePrefix($result[$index]);
            self::assertTrue($previous === null || $current === null || $previous !== $current, 'Similar prefixes should be separated.');
        }
    }

    public function testFetchSuggestionsHandlesJsonCodeFence(): void
    {
        $responder = new class () extends HttpChatResponder {
            public function __construct()
            {
            }

            public function respond(array $messages, array $context): string
            {
                return "```json\n[\"Kreativ-Kojoten\",\n \"Sommer-Sirenen\"]\n```";
            }
        };

        $client = new TeamNameAiClient($responder);

        $result = $client->fetchSuggestions(2, [], [], 'de');

        self::assertSame(['Kreativ-Kojoten', 'Sommer-Sirenen'], $result);
    }

    public function testFetchSuggestionsHandlesProseBeforeJsonCodeFence(): void
    {
        $responder = new class () extends HttpChatResponder {
            public function __construct()
            {
            }

            public function respond(array $messages, array $context): string
            {
                return "Hier sind ein paar Vorschläge:\n```json\n[\"Sommer-Sprinter\",\n \"Fest-Falken\"]\n```";
            }
        };

        $client = new TeamNameAiClient($responder);

        $result = $client->fetchSuggestions(2, [], [], 'de');

        self::assertSame(['Sommer-Sprinter', 'Fest-Falken'], $result);
    }

    public function testFetchSuggestionsParsesNumberedFallbackList(): void
    {
        $responder = new class () extends HttpChatResponder {
            public function __construct()
            {
            }

            public function respond(array $messages, array $context): string
            {
                return "1. Fun Füchse\n2) `Turbo Trolle`\n3)  Pixel-Pandas";
            }
        };

        $client = new TeamNameAiClient($responder);

        $result = $client->fetchSuggestions(3, [], [], 'de');

        self::assertSame(['Fun Füchse', 'Turbo Trolle', 'Pixel-Pandas'], $result);
    }

    private const STOP_WORDS = [
        'der',
        'die',
        'das',
        'den',
        'dem',
        'des',
        'ein',
        'eine',
        'einer',
        'einem',
        'einen',
        'eines',
        'the',
        'team',
    ];

    private static function normalizePrefix(string $name): ?string
    {
        $normalized = trim(mb_strtolower(preg_replace('/[^\p{L}\s\-]/u', '', $name) ?? ''));
        if ($normalized === '') {
            return null;
        }

        $parts = preg_split('/[\s\-]+/u', $normalized) ?: [];
        foreach ($parts as $part) {
            if ($part === '' || in_array($part, self::STOP_WORDS, true)) {
                continue;
            }

            return mb_substr($part, 0, 3);
        }

        return null;
    }
}
