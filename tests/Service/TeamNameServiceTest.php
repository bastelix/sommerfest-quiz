<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\TeamNameService;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

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
