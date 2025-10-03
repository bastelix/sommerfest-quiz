<?php

declare(strict_types=1);

namespace Tests\Service\RagChat;

use App\Service\RagChat\RagChatService;
use PHPUnit\Framework\TestCase;

final class RagChatServiceTest extends TestCase
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

    public function testAnswerReturnsLocalizedSummary(): void
    {
        $path = $this->createIndexFile();
        $service = new RagChatService($path);

        $response = $service->answer('calserver inventar', 'de');

        self::assertSame('calserver inventar', $response->getQuestion());
        self::assertStringContainsString('Basierend auf der Wissensbasis', $response->getAnswer());
        self::assertCount(2, $response->getContext());
        self::assertSame('Feature Overview (Abschnitt 1)', $response->getContext()[0]->getLabel());
        self::assertSame('docs/usage.md', $response->getContext()[1]->getMetadata()['source']);
    }

    public function testAnswerUsesEnglishMessages(): void
    {
        $path = $this->createIndexFile();
        $service = new RagChatService($path);

        $response = $service->answer('calserver inventar', 'en');

        self::assertStringContainsString('Based on our knowledge base', $response->getAnswer());
        self::assertStringContainsString('Section 1', $response->getContext()[0]->getLabel());
    }

    public function testAnswerReturnsFallbackWhenNoContextFound(): void
    {
        $path = $this->createIndexFile();
        $service = new RagChatService($path);

        $response = $service->answer('unrelated', 'de');

        self::assertStringContainsString('Ich konnte keine passenden Informationen', $response->getAnswer());
        self::assertSame([], $response->getContext());
    }

    public function testAnswerRejectsEmptyQuestion(): void
    {
        $service = new RagChatService($this->createIndexFile());

        $this->expectExceptionMessage('Question must not be empty.');
        $service->answer('   ');
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
