<?php

declare(strict_types=1);

namespace Tests\Service\RagChat;

use App\Service\RagChat\SemanticIndex;
use PHPUnit\Framework\TestCase;

final class SemanticIndexTest extends TestCase
{
    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        $this->files = [];
    }

    public function testSearchReturnsRankedResults(): void
    {
        $path = $this->createIndexFile();
        $index = SemanticIndex::load($path);

        $results = $index->search('calserver inventar');

        self::assertCount(2, $results);
        self::assertSame('chunk-1', $results[0]->getChunkId());
        self::assertSame('chunk-2', $results[1]->getChunkId());
        self::assertSame(1.0, $results[0]->getScore());
        self::assertGreaterThan(0.6, $results[1]->getScore());
    }

    public function testSearchReturnsEmptyForUnknownTerms(): void
    {
        $path = $this->createIndexFile();
        $index = SemanticIndex::load($path);

        $results = $index->search('unrelated topic');

        self::assertSame([], $results);
    }

    private function createIndexFile(): string
    {
        $payload = [
            'vocabulary' => ['calserver', 'inventar'],
            'idf' => [1.0, 1.0],
            'chunks' => [
                [
                    'id' => 'chunk-1',
                    'text' => 'calserver inventar verwalten',
                    'metadata' => [
                        'title' => 'Feature Overview',
                        'chunk_index' => 1,
                    ],
                    'vector' => [[0, 0.5], [1, 0.5]],
                    'norm' => 0.707107,
                ],
                [
                    'id' => 'chunk-2',
                    'text' => 'Inventarverwaltung für Labore',
                    'metadata' => [
                        'source' => 'docs/usage.md',
                    ],
                    'vector' => [[1, 1.0]],
                    'norm' => 1.0,
                ],
            ],
        ];

        $path = (string) tempnam(sys_get_temp_dir(), 'rag-index-');
        file_put_contents($path, json_encode($payload));
        $this->files[] = $path;

        return $path;
    }
}
