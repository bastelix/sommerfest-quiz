<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\RagChat\HttpChatResponder;
use App\Service\TeamNameAiClient;
use App\Service\TeamNameService;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;
use Tests\Stubs\FakeTeamNameAiClient;

use function array_column;
use function array_values;
use function json_encode;
use function preg_match;

use const JSON_THROW_ON_ERROR;

require_once __DIR__ . '/../Stubs/FakeTeamNameAiClient.php';

final class TeamNameServiceTest extends TestCase
{
    public function testReserveTraversesNamesFromRandomOffset(): void
    {
        $pdo = $this->createInMemoryDatabase();
        $lexiconPath = $this->createLexicon(['Alpha', 'Beta'], ['Lion', 'Tiger']);

        try {
            $service = new class ($pdo, $lexiconPath, [2, 0, 0, 0]) extends TeamNameService {
                /** @var array<int, int> */
                private array $offsets;

                /**
                 * @param array<int, int> $offsets
                 */
                public function __construct(PDO $pdo, string $lexiconPath, array $offsets)
                {
                    $this->offsets = $offsets;
                    parent::__construct($pdo, $lexiconPath, 120);
                }

                protected function randomStartIndex(int $total): int
                {
                    if ($total <= 1) {
                        return 0;
                    }

                    if ($this->offsets === []) {
                        return parent::randomStartIndex($total);
                    }

                    $offset = array_shift($this->offsets);
                    if ($offset < 0) {
                        return 0;
                    }

                    if ($offset >= $total) {
                        return $total - 1;
                    }

                    return $offset;
                }
            };

            $first = $service->reserve('event-42');
            self::assertSame('Beta Lion', $first['name']);

            $second = $service->reserve('event-42');
            self::assertSame('Alpha Lion', $second['name']);

            $third = $service->reserve('event-42');
            self::assertSame('Alpha Tiger', $third['name']);

            $fourth = $service->reserve('event-42');
            self::assertSame('Beta Tiger', $fourth['name']);
        } finally {
            @unlink($lexiconPath);
        }
    }

    public function testReserveUsesDomainAndToneFilters(): void
    {
        $pdo = $this->createInMemoryDatabase();
        $lexiconPath = $this->createLexicon(
            [
                'default' => ['Neutral'],
                'playful' => ['Spritzig'],
            ],
            [
                'default' => ['Standard'],
                'nature' => ['Eiche', 'Fluss'],
            ]
        );

        try {
            $service = new class ($pdo, $lexiconPath) extends TeamNameService {
                protected function randomStartIndex(int $total): int
                {
                    return 0;
                }
            };

            $first = $service->reserve('event-99', ['nature'], ['playful']);
            self::assertSame('Spritzig Eiche', $first['name']);
            self::assertSame(2, $first['total']);

            $second = $service->reserve('event-99', ['nature'], ['playful']);
            self::assertSame('Spritzig Fluss', $second['name']);
            self::assertSame(2, $second['total']);
        } finally {
            @unlink($lexiconPath);
        }
    }

    public function testReserveFallsBackToDefaultWhenFiltersUnknown(): void
    {
        $pdo = $this->createInMemoryDatabase();
        $lexiconPath = $this->createLexicon(['Alpha'], ['Lion']);

        try {
            $service = new class ($pdo, $lexiconPath) extends TeamNameService {
                protected function randomStartIndex(int $total): int
                {
                    return 0;
                }
            };

            $reservation = $service->reserve('event-77', ['unknown'], ['imaginary']);
            self::assertSame('Alpha Lion', $reservation['name']);
            self::assertSame(1, $reservation['total']);
            self::assertFalse($reservation['fallback']);
        } finally {
            @unlink($lexiconPath);
        }
    }

    public function testReserveBatchReturnsMultipleUniqueNames(): void
    {
        $pdo = $this->createInMemoryDatabase();
        $lexiconPath = $this->createLexicon(['Alpha', 'Beta'], ['Lion', 'Tiger']);

        try {
            $service = new class ($pdo, $lexiconPath) extends TeamNameService {
                protected function randomStartIndex(int $total): int
                {
                    return 0;
                }
            };

            $reservations = $service->reserveBatch('event-batch', 3);
            self::assertCount(3, $reservations);
            $names = array_column($reservations, 'name');
            self::assertSame($names, array_values(array_unique($names)));
            self::assertFalse($reservations[0]['fallback']);
        } finally {
            @unlink($lexiconPath);
        }
    }

    public function testReserveBatchUsesFiltersAndFallsBackWhenExhausted(): void
    {
        $pdo = $this->createInMemoryDatabase();
        $lexiconPath = $this->createLexicon(
            [
                'default' => ['Neutral'],
                'playful' => ['Spritzig'],
            ],
            [
                'default' => ['Standard'],
                'nature' => ['Eiche', 'Fluss'],
            ]
        );

        try {
            $service = new class ($pdo, $lexiconPath) extends TeamNameService {
                protected function randomStartIndex(int $total): int
                {
                    return 0;
                }
            };

            $batch = $service->reserveBatch('event-filter', 5, ['nature'], ['playful']);
            self::assertCount(2, $batch);
            self::assertSame(['Spritzig Eiche', 'Spritzig Fluss'], array_column($batch, 'name'));

            $fallbackBatch = $service->reserveBatch('event-filter', 2, ['nature'], ['playful']);
            self::assertCount(1, $fallbackBatch);
            self::assertTrue($fallbackBatch[0]['fallback']);
        } finally {
            @unlink($lexiconPath);
        }
    }

    public function testReserveWithAiCachesSuggestions(): void
    {
        $pdo = $this->createInMemoryDatabase();
        $lexiconPath = $this->createLexicon(['Local'], ['Backup']);

        try {
            $aiClient = new FakeTeamNameAiClient([
                ['AI Alpha', 'AI Beta', 'AI Gamma'],
                ['AI Delta', 'AI Echo'],
            ]);

            $service = new TeamNameService($pdo, $lexiconPath, 120, $aiClient, true, null);

            $first = $service->reserveWithBuffer('event-ai', [], [], 2, null, 'ai');
            self::assertSame('AI Alpha', $first['name']);
            self::assertFalse($first['fallback']);

            $cacheProperty = new ReflectionProperty(TeamNameService::class, 'aiNameCache');
            $cacheProperty->setAccessible(true);
            /** @var array<string, array<int, string>> $cache */
            $cache = $cacheProperty->getValue($service);
            self::assertNotEmpty($cache);

            $second = $service->reserveWithBuffer('event-ai', [], [], 2, null, 'ai');
            self::assertSame('AI Beta', $second['name']);

            $calls = $aiClient->getCalls();
            self::assertCount(3, $calls);
            foreach ($calls as $call) {
                self::assertGreaterThanOrEqual(1, $call['count']);
                self::assertLessThanOrEqual(2, $call['count']);
                self::assertSame([], $call['domains']);
                self::assertSame([], $call['tones']);
                self::assertSame('de', $call['locale']);
            }
        } finally {
            @unlink($lexiconPath);
        }
    }

    public function testReserveWithAiFallsBackWhenSuggestionsExhausted(): void
    {
        $pdo = $this->createInMemoryDatabase();
        $lexiconPath = $this->createLexicon(['default' => []], ['default' => []]);

        try {
            $aiClient = new FakeTeamNameAiClient([
                ['Taken Crew'],
            ]);

            $service = new TeamNameService($pdo, $lexiconPath, 120, $aiClient, true, null);

            $stmt = $pdo->prepare('INSERT INTO team_names(event_id, name, lexicon_version, reservation_token) VALUES (?,?,?,?)');
            $stmt->execute(['event-fallback', 'Taken Crew', 2, 'existing-token']);

            $reservation = $service->reserveWithBuffer('event-fallback', [], [], 0, null, 'ai');
            self::assertTrue($reservation['fallback']);
            self::assertSame(0, $reservation['total']);
            self::assertSame(0, $reservation['remaining']);
            self::assertCount(1, $aiClient->getCalls());
            self::assertSame(1, preg_match('/^Gast-[A-Z0-9]{5}$/', $reservation['name']));
        } finally {
            @unlink($lexiconPath);
        }
    }

    public function testReserveBatchWithAiUsesProvidedFilters(): void
    {
        $pdo = $this->createInMemoryDatabase();
        $lexiconPath = $this->createLexicon(['Local'], ['Backup']);

        try {
            $aiClient = new FakeTeamNameAiClient([
                ['AI One', 'AI Two', 'AI Three'],
                ['AI Four', 'AI Five'],
            ]);

            $service = new TeamNameService($pdo, $lexiconPath, 120, $aiClient, true, null);

            $batch = $service->reserveBatchWithBuffer('event-batch-ai', 3, ['nature'], ['playful'], 2, 'fr', 'ai');
            self::assertCount(3, $batch);
            self::assertSame(['AI One', 'AI Two', 'AI Three'], array_column($batch, 'name'));
            foreach ($batch as $reservation) {
                self::assertFalse($reservation['fallback']);
            }

            $calls = $aiClient->getCalls();
            self::assertCount(2, $calls);
            self::assertSame(3, $calls[0]['count']);
            self::assertSame(['nature'], $calls[0]['domains']);
            self::assertSame(['playful'], $calls[0]['tones']);
            self::assertSame('fr', $calls[0]['locale']);
            self::assertSame(2, $calls[1]['count']);
        } finally {
            @unlink($lexiconPath);
        }
    }

    public function testReserveWithAiPassesContextThroughResponder(): void
    {
        $pdo = $this->createInMemoryDatabase();
        $lexiconPath = $this->createLexicon(['Local'], ['Backup']);

        try {
            $responder = new class () extends HttpChatResponder {
                /**
                 * @var list<array<string, mixed>>
                 */
                public array $capturedContext = [];

                public function __construct()
                {
                }

                public function respond(array $messages, array $context): string
                {
                    $this->capturedContext = $context;

                    return json_encode(['names' => ['Solar Echo', 'Neon Pulse']], JSON_THROW_ON_ERROR);
                }
            };

            $aiClient = new TeamNameAiClient($responder);
            $service = new TeamNameService($pdo, $lexiconPath, 120, $aiClient, true, null);

            $reservation = $service->reserveWithBuffer('event-ai-context', ['nature'], ['playful'], 0, 'fr', 'ai');

            self::assertSame('Solar Echo', $reservation['name']);
            self::assertFalse($reservation['fallback']);

            $context = $responder->capturedContext;
            self::assertNotEmpty($context);
            $summary = $context[0]['text'] ?? '';
            self::assertStringContainsString('Team name request: 1 suggestions for locale "fr".', (string) $summary);
            self::assertStringContainsString('Domains: nature.', (string) $summary);
            self::assertStringContainsString('Tones: playful.', (string) $summary);

            $metadata = $context[0]['metadata'] ?? [];
            self::assertSame(1, $metadata['count']);
            self::assertSame('fr', $metadata['locale']);
            self::assertSame(['nature'], $metadata['domains']);
            self::assertSame(['playful'], $metadata['tones']);
        } finally {
            @unlink($lexiconPath);
        }
    }

    private function createInMemoryDatabase(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE team_names (' .
            'id INTEGER PRIMARY KEY AUTOINCREMENT, ' .
            'event_id TEXT NOT NULL, ' .
            'name TEXT NOT NULL, ' .
            'lexicon_version INTEGER NOT NULL DEFAULT 1, ' .
            'reservation_token TEXT NOT NULL, ' .
            'reserved_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, ' .
            'assigned_at TEXT NULL, ' .
            'released_at TEXT NULL, ' .
            'fallback INTEGER NOT NULL DEFAULT 0' .
            ')'
        );
        $pdo->exec('CREATE UNIQUE INDEX ux_team_names_active ON team_names(event_id, name)');
        $pdo->exec('CREATE UNIQUE INDEX ux_team_names_token ON team_names(reservation_token)');

        return $pdo;
    }

    /**
     * @param array<int, string> $adjectives
     * @param array<int, string> $nouns
     */
    private function createLexicon(array $adjectives, array $nouns): string
    {
        $path = tempnam(sys_get_temp_dir(), 'lexicon');
        if ($path === false) {
            throw new RuntimeException('Failed to allocate temporary lexicon file.');
        }

        $payload = [
            'version' => 2,
            'adjectives' => $this->normalizeSection($adjectives),
            'nouns' => $this->normalizeSection($nouns),
        ];

        $bytesWritten = file_put_contents($path, json_encode($payload, JSON_THROW_ON_ERROR));
        if ($bytesWritten === false) {
            throw new RuntimeException('Failed to write lexicon file.');
        }

        return $path;
    }

    /**
     * @param array<int|string, mixed> $section
     * @return array<int|string, mixed>
     */
    private function normalizeSection(array $section): array
    {
        if ($section === []) {
            return ['default' => []];
        }

        $isAssoc = array_keys($section) !== range(0, count($section) - 1);

        return $isAssoc ? $section : ['default' => $section];
    }
}
