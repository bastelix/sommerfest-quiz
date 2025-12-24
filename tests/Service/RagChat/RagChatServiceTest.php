<?php

declare(strict_types=1);

namespace Tests\Service\RagChat;

use App\Service\RagChat\ChatResponderInterface;
use App\Service\RagChat\RagChatService;
use PHPUnit\Framework\TestCase;

use function array_key_last;

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

    public function testAnswerUsesChatResponderForAnswer(): void
    {
        $path = $this->createIndexFile();
        $responder = new class ('Natürlich!') implements ChatResponderInterface {
            public array $lastMessages = [];
            public array $lastContext = [];

            public function __construct(private string $response)
            {
            }

            public function respond(array $messages, array $context): string
            {
                $this->lastMessages = $messages;
                $this->lastContext = $context;

                return $this->response;
            }
        };

        $service = new RagChatService($path, $this->domainBase, $responder);

        $response = $service->answer('calserver inventar', 'de');

        self::assertSame('calserver inventar', $response->getQuestion());
        self::assertSame('Natürlich!', $response->getAnswer());
        self::assertCount(2, $response->getContext());
        self::assertSame('Feature Overview (Abschnitt 1)', $response->getContext()[0]->getLabel());
        self::assertSame('docs/usage.md', $response->getContext()[1]->getMetadata()['source']);
        self::assertNotSame([], $responder->lastMessages);
        self::assertSame('system', $responder->lastMessages[0]['role']);
        self::assertSame('user', $responder->lastMessages[array_key_last($responder->lastMessages)]['role']);
        self::assertSame('calserver inventar', $responder->lastMessages[array_key_last($responder->lastMessages)]['content']);
        self::assertSame('chunk-1', $responder->lastContext[0]['id']);
    }

    public function testAnswerUsesEnglishMessages(): void
    {
        $path = $this->createIndexFile();
        $responder = new class ('Sounds good!') implements ChatResponderInterface {
            public array $lastMessages = [];

            public function __construct(private string $response)
            {
            }

            public function respond(array $messages, array $context): string
            {
                $this->lastMessages = $messages;

                return $this->response;
            }
        };

        $service = new RagChatService($path, $this->domainBase, $responder);

        $response = $service->answer('calserver inventar', 'en');

        self::assertSame('Sounds good!', $response->getAnswer());
        self::assertStringContainsString('Section 1', $response->getContext()[0]->getLabel());
        self::assertSame('system', $responder->lastMessages[0]['role']);
        self::assertStringContainsString('You are a helpful assistant', $responder->lastMessages[0]['content']);
    }

    public function testAnswerReturnsFallbackWhenNoContextFound(): void
    {
        $path = $this->createIndexFile();
        $responder = new class implements ChatResponderInterface {
            public bool $called = false;

            public function respond(array $messages, array $context): string
            {
                $this->called = true;

                return 'irrelevant';
            }
        };

        $service = new RagChatService($path, $this->domainBase, $responder);

        $response = $service->answer('unrelated', 'de');

        self::assertStringContainsString('Ich konnte keine passenden Informationen', $response->getAnswer());
        self::assertSame([], $response->getContext());
        self::assertFalse($responder->called);
    }

    public function testFallsBackToSummaryWhenResponderFails(): void
    {
        $path = $this->createIndexFile();
        $responder = new class implements ChatResponderInterface {
            public int $calls = 0;

            public function respond(array $messages, array $context): string
            {
                $this->calls++;
                throw new \RuntimeException('boom');
            }
        };

        $service = new RagChatService($path, $this->domainBase, $responder);

        $response = $service->answer('calserver inventar', 'de');

        self::assertStringContainsString('Basierend auf der Wissensbasis', $response->getAnswer());
        self::assertSame(1, $responder->calls);
    }

    public function testAnswerRejectsEmptyQuestion(): void
    {
        $service = new RagChatService($this->createIndexFile(), $this->domainBase, null, static fn (): array => []);

        $this->expectExceptionMessage('Question must not be empty.');
        $service->answer('   ');
    }

    public function testAnswerPrefersDomainResults(): void
    {
        $path = $this->createIndexFile();
        $responder = new class ('Natürlich!') implements ChatResponderInterface {
            public function respond(array $messages, array $context): string
            {
                return 'Natürlich!';
            }
        };

        $service = new RagChatService($path, $this->domainBase, $responder);

        $this->createDomainIndex('example.com');

        $response = $service->answer('calserver inventar', 'de', 'example.com');

        $context = $response->getContext();
        self::assertNotSame([], $context);
        self::assertSame('Domain Knowledge (Abschnitt 1)', $context[0]->getLabel());
        foreach ($context as $item) {
            $metadata = $item->getMetadata();
            self::assertArrayHasKey('domain', $metadata);
            self::assertSame('example.com', $metadata['domain']);
        }
    }

    public function testAnswerFallsBackToSubdomainAliasWhenHostIncludesParentDomain(): void
    {
        $path = $this->createIndexFile();
        $responder = new class ('Natürlich!') implements ChatResponderInterface {
            public function respond(array $messages, array $context): string
            {
                return 'Natürlich!';
            }
        };

        $service = new RagChatService($path, $this->domainBase, $responder);

        $this->createDomainIndex('calserver');

        $response = $service->answer('calserver inventar', 'de', 'calserver.quizrace.de');

        $context = $response->getContext();
        self::assertNotSame([], $context);
        self::assertSame('Domain Knowledge (Abschnitt 1)', $context[0]->getLabel());
        self::assertSame('calserver', $context[0]->getMetadata()['domain']);
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
