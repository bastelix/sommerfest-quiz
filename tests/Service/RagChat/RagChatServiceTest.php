<?php

declare(strict_types=1);

namespace Tests\Service\RagChat;

use App\Service\RagChat\RagChatService;
use PHPUnit\Framework\TestCase;

final class RagChatServiceTest extends TestCase
{
    /** @var list<string> */
    private array $files = [];

    /**
     * @var list<string>
     */
    private array $directories = [];

    private string $domainBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->domainBase = sys_get_temp_dir() . '/rag-domains-' . bin2hex(random_bytes(4));
        if (!is_dir($this->domainBase)) {
            mkdir($this->domainBase, 0775, true);
        }
        $this->directories[] = $this->domainBase;
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        $this->files = [];

        foreach (array_reverse($this->directories) as $path) {
            $this->removeDirectory($path);
        }
        $this->directories = [];
    }

    public function testAnswerReturnsLocalizedSummary(): void
    {
        $path = $this->createIndexFile();
        $service = new RagChatService($path, $this->domainBase);

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
        $service = new RagChatService($path, $this->domainBase);

        $response = $service->answer('calserver inventar', 'en');

        self::assertStringContainsString('Based on our knowledge base', $response->getAnswer());
        self::assertStringContainsString('Section 1', $response->getContext()[0]->getLabel());
    }

    public function testAnswerReturnsFallbackWhenNoContextFound(): void
    {
        $path = $this->createIndexFile();
        $service = new RagChatService($path, $this->domainBase);

        $response = $service->answer('unrelated', 'de');

        self::assertStringContainsString('Ich konnte keine passenden Informationen', $response->getAnswer());
        self::assertSame([], $response->getContext());
    }

    public function testAnswerRejectsEmptyQuestion(): void
    {
        $service = new RagChatService($this->createIndexFile(), $this->domainBase);

        $this->expectExceptionMessage('Question must not be empty.');
        $service->answer('   ');
    }

    public function testAnswerPrefersDomainResults(): void
    {
        $path = $this->createIndexFile();
        $service = new RagChatService($path, $this->domainBase);

        $this->createDomainIndex('example.com');

        $response = $service->answer('calserver inventar', 'de', 'example.com');

        self::assertNotSame([], $response->getContext());
        self::assertSame('Domain Knowledge (Abschnitt 1)', $response->getContext()[0]->getLabel());
        self::assertSame('example.com', $response->getContext()[0]->getMetadata()['domain']);
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

    private function createDomainIndex(string $domain): void
    {
        $payload = [
            'vocabulary' => ['calserver', 'inventar'],
            'idf' => [1.0, 1.0],
            'chunks' => [
                [
                    'id' => 'domain-1',
                    'text' => 'Calserver Inventar domain docs',
                    'metadata' => [
                        'title' => 'Domain Knowledge',
                        'chunk_index' => 1,
                    ],
                    'vector' => [[0, 0.5], [1, 0.5]],
                    'norm' => 0.707107,
                ],
            ],
        ];

        $dir = $this->domainBase . DIRECTORY_SEPARATOR . $domain;
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $path = $dir . DIRECTORY_SEPARATOR . 'index.json';
        file_put_contents($path, json_encode($payload));
        $this->files[] = $path;
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $target = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($target)) {
                $this->removeDirectory($target);
            } elseif (is_file($target)) {
                unlink($target);
            }
        }
        rmdir($path);
    }
}
