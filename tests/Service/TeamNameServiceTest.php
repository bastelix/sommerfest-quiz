<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\RagChat\HttpChatResponder;
use App\Service\TeamNameAiClient;
use App\Service\TeamNameService;
use DateTimeImmutable;
use DateTimeZone;
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
            self::assertSame('BetaLion', $first['name']);

            $second = $service->reserve('event-42');
            self::assertSame('AlphaLion', $second['name']);

            $third = $service->reserve('event-42');
            self::assertSame('AlphaTiger', $third['name']);

            $fourth = $service->reserve('event-42');
            self::assertSame('BetaTiger', $fourth['name']);
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
            self::assertSame('SpritzigEiche', $first['name']);
            self::assertSame(2, $first['total']);

            $second = $service->reserve('event-99', ['nature'], ['playful']);
            self::assertSame('SpritzigFluss', $second['name']);
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
            self::assertSame('AlphaLion', $reservation['name']);
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
            self::assertSame(['SpritzigEiche', 'SpritzigFluss'], array_column($batch, 'name'));

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

    public function testGetAiDiagnosticsReportsLastStatus(): void
    {
        $pdo = $this->createInMemoryDatabase();
        $lexiconPath = $this->createLexicon(['Local'], ['Backup']);

        try {
            $aiClient = new FakeTeamNameAiClient([
                ['AI Nimbus'],
                [],
            ]);

            $service = new TeamNameService($pdo, $lexiconPath, 120, $aiClient, true, null);

            $initial = $service->getAiDiagnostics();
            self::assertTrue($initial['enabled']);
            self::assertFalse($initial['available']);
            self::assertNull($initial['last_attempt_at']);

            $service->reserveWithBuffer('event-diag', [], [], 0, null, 'ai');
            $afterSuccess = $service->getAiDiagnostics();
            self::assertTrue($afterSuccess['available']);
            self::assertNotNull($afterSuccess['last_success_at']);
            self::assertNull($afterSuccess['last_error']);

            $service->reserveWithBuffer('event-diag', [], [], 0, null, 'ai');
            $afterFailure = $service->getAiDiagnostics();
            self::assertFalse($afterFailure['available']);
            self::assertNotNull($afterFailure['last_attempt_at']);
            self::assertSame('Fake AI client returned no results.', $afterFailure['last_error']);
            self::assertSame('Fake AI client returned no results.', $afterFailure['client_last_error']);
        } finally {
            @unlink($lexiconPath);
        }
    }

    public function testListNamesForEventReturnsHistoryWithStatuses(): void
    {
        $pdo = $this->createInMemoryDatabase();
        $lexiconPath = $this->createLexicon(['Alpha'], ['Beta']);

        try {
            $service = new TeamNameService($pdo, $lexiconPath, 300, null, false, null);

            $insert = $pdo->prepare(
                'INSERT INTO team_names(event_id, name, lexicon_version, reservation_token, reserved_at, assigned_at, released_at, fallback) '
                . 'VALUES (?,?,?,?,?,?,?,?)'
            );
            $utc = new DateTimeZone('UTC');
            $now = new DateTimeImmutable('now', $utc);
            $insert->execute([
                'ev-history',
                'Team Reserviert',
                1,
                'tok-reserved',
                $now->modify('-2 minutes')->format(DATE_ATOM),
                null,
                null,
                0,
            ]);
            $insert->execute([
                'ev-history',
                'Team Zugewiesen',
                1,
                'tok-assigned',
                $now->modify('-1 minute')->format(DATE_ATOM),
                $now->modify('-30 seconds')->format(DATE_ATOM),
                null,
                0,
            ]);
            $insert->execute([
                'ev-history',
                'Team Freigegeben',
                1,
                'tok-released',
                $now->format(DATE_ATOM),
                $now->modify('+30 seconds')->format(DATE_ATOM),
                $now->modify('+2 minutes')->format(DATE_ATOM),
                1,
            ]);

            $history = $service->listNamesForEvent('ev-history');
            self::assertCount(3, $history);
            self::assertSame('Team Freigegeben', $history[0]['name']);
            self::assertSame('released', $history[0]['status']);
            self::assertSame('Team Zugewiesen', $history[1]['name']);
            self::assertSame('assigned', $history[1]['status']);
            self::assertSame('Team Reserviert', $history[2]['name']);
            self::assertSame('reserved', $history[2]['status']);
            self::assertNotNull($history[2]['reserved_at']);
            self::assertNull($history[2]['assigned_at']);
            self::assertNotNull($history[0]['released_at']);
            self::assertTrue($history[0]['fallback']);

            $limited = $service->listNamesForEvent('ev-history', 2);
            self::assertCount(2, $limited);
            self::assertSame('Team Freigegeben', $limited[0]['name']);
            self::assertSame('Team Zugewiesen', $limited[1]['name']);
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
