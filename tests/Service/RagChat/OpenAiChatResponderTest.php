<?php

declare(strict_types=1);

namespace Tests\Service\RagChat;

use App\Service\RagChat\HttpChatResponder;
use App\Service\RagChat\OpenAiChatResponder;
use App\Service\RagChat\RagChatService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;

use function array_key_last;
use function bin2hex;
use function file_put_contents;
use function is_dir;
use function is_file;
use function json_decode;
use function mkdir;
use function putenv;
use function random_bytes;
use function rmdir;
use function sys_get_temp_dir;
use function unlink;

use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_UNICODE;

final class OpenAiChatResponderTest extends TestCase
{
    private string $indexPath;

    private string $domainBase;

    /**
     * @var array<string>
     */
    private array $createdDirectories = [];

    protected function setUp(): void
    {
        parent::setUp();

        $base = sys_get_temp_dir() . '/rag-openai-' . bin2hex(random_bytes(4));
        $this->domainBase = $base . '/domains';
        $indexDir = $base . '/index';

        $this->createDirectory($base);

        $this->createDirectory($this->domainBase);
        $this->createDirectory($indexDir);

        $this->indexPath = $indexDir . '/index.json';
        file_put_contents($this->indexPath, $this->fixtureIndexPayload());

        putenv('RAG_CHAT_SERVICE_MODEL=gpt-4o-mini');
        putenv('RAG_CHAT_SERVICE_TEMPERATURE=0.2');
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        putenv('RAG_CHAT_SERVICE_MODEL');
        putenv('RAG_CHAT_SERVICE_TEMPERATURE');
        putenv('RAG_CHAT_SERVICE_URL');
        putenv('RAG_CHAT_SERVICE_FORCE_OPENAI');
        putenv('RAG_CHAT_SERVICE_DRIVER');

        if (isset($this->indexPath) && is_file($this->indexPath)) {
            unlink($this->indexPath);
        }

        foreach (array_reverse($this->createdDirectories) as $directory) {
            if (is_dir($directory)) {
                @rmdir($directory);
            }
        }
        $this->createdDirectories = [];
    }

    public function testResponderConsumesOpenAiResponse(): void
    {
        $history = [];

        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'choices' => [
                    [
                        'message' => ['content' => 'Natürlich!'],
                    ],
                ],
            ])),
        ]);

        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));

        $client = new Client(['handler' => $stack]);

        $responder = new OpenAiChatResponder(
            'https://api.openai.com/v1/chat/completions',
            $client,
            'test-token'
        );

        $service = new RagChatService($this->indexPath, $this->domainBase, $responder);

        $response = $service->answer('calserver inventar', 'de');

        self::assertSame('calserver inventar', $response->getQuestion());
        self::assertSame('Natürlich!', $response->getAnswer());
        self::assertNotSame([], $response->getContext());
        self::assertNotSame([], $history);

        $request = $history[0]['request'];
        $body = (string) $request->getBody();
        $payload = json_decode($body, true);

        self::assertIsArray($payload);
        self::assertSame('gpt-4o-mini', $payload['model'] ?? null);
        self::assertSame(0.2, $payload['temperature'] ?? null);
        $messages = $payload['messages'] ?? [];
        self::assertIsArray($messages);
        self::assertGreaterThanOrEqual(2, count($messages));
        self::assertStringContainsString('Kontext aus der Wissensbasis', $messages[1]['content'] ?? '');
        $lastMessage = $messages[array_key_last($messages)] ?? [];
        self::assertSame('calserver inventar', $lastMessage['content'] ?? null);

        self::assertStringNotContainsString('Basierend auf der Wissensbasis', $response->getAnswer());
    }

    public function testResponderCombinesMultipartMessageContent(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'choices' => [
                    [
                        'message' => [
                            'content' => [
                                [
                                    'type' => 'output_text',
                                    'text' => '{"names":["AI Stern","',
                                ],
                                [
                                    'type' => 'output_text',
                                    'output' => [
                                        [
                                            'type' => 'text',
                                            'text' => 'AI Funk',
                                        ],
                                        [
                                            'type' => 'metadata',
                                            'value' => '"]}',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ])),
        ]);

        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $responder = new OpenAiChatResponder(
            'https://api.openai.com/v1/chat/completions',
            $client,
            'test-token'
        );

        $messages = [
            ['role' => 'user', 'content' => 'Kombiniere die Antwort.'],
        ];
        $context = [
            ['id' => 'chunk-1', 'text' => 'stub context', 'score' => 0.5, 'metadata' => []],
        ];

        $answer = $responder->respond($messages, $context);

        self::assertSame('{"names":["AI Stern","AI Funk"]}', $answer);
    }

    public function testServiceDetectsOpenAiResponderForProxyEndpoint(): void
    {
        putenv('RAG_CHAT_SERVICE_URL=https://openai-proxy.example/v1/chat/completions');

        try {
            $service = new RagChatService($this->indexPath, $this->domainBase, null, static fn (): array => []);

            $method = new ReflectionMethod(RagChatService::class, 'createDefaultResponder');
            $method->setAccessible(true);

            $responder = $method->invoke($service);

            self::assertInstanceOf(OpenAiChatResponder::class, $responder);
        } finally {
            putenv('RAG_CHAT_SERVICE_URL');
        }
    }

    public function testProxiedEndpointUsesOpenAiPayloadWithoutContext(): void
    {
        putenv('RAG_CHAT_SERVICE_URL=https://mein-proxy.example/v1/chat/completions');
        putenv('RAG_CHAT_SERVICE_DRIVER=openai');

        $history = [];

        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'choices' => [
                    [
                        'message' => ['content' => 'Natürlich!'],
                    ],
                ],
            ])),
        ]);

        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));

        $client = new Client(['handler' => $stack]);

        try {
            $service = new RagChatService($this->indexPath, $this->domainBase, null, static fn (): array => []);

            $method = new ReflectionMethod(RagChatService::class, 'createDefaultResponder');
            $method->setAccessible(true);
            $method->invoke($service);

            $responderProperty = new ReflectionProperty(RagChatService::class, 'chatResponder');
            $responderProperty->setAccessible(true);
            $responder = $responderProperty->getValue($service);

            self::assertInstanceOf(OpenAiChatResponder::class, $responder);

            $clientProperty = new ReflectionProperty(HttpChatResponder::class, 'httpClient');
            $clientProperty->setAccessible(true);
            $clientProperty->setValue($responder, $client);

            $response = $service->answer('calserver inventar', 'de');

            self::assertSame('Natürlich!', $response->getAnswer());
            self::assertNotSame([], $history);

            $request = $history[0]['request'];
            $payload = json_decode((string) $request->getBody(), true);

            self::assertIsArray($payload);
            self::assertArrayNotHasKey('context', $payload);
            self::assertSame('gpt-4o-mini', $payload['model'] ?? null);
        } finally {
            putenv('RAG_CHAT_SERVICE_URL');
            putenv('RAG_CHAT_SERVICE_DRIVER');
        }
    }

    public function testServiceUsesSettingsForHttpResponder(): void
    {
        $settings = [
            'rag_chat_service_url' => 'https://settings.example/api/chat',
            'rag_chat_service_driver' => 'http',
            'rag_chat_service_token' => 'settings-token',
        ];

        $service = new RagChatService(
            $this->indexPath,
            $this->domainBase,
            null,
            static fn (): array => $settings
        );

        $method = new ReflectionMethod(RagChatService::class, 'createDefaultResponder');
        $method->setAccessible(true);
        $responder = $method->invoke($service);

        self::assertInstanceOf(HttpChatResponder::class, $responder);

        $endpointProperty = new ReflectionProperty(HttpChatResponder::class, 'endpoint');
        $endpointProperty->setAccessible(true);
        self::assertSame('https://settings.example/api/chat', $endpointProperty->getValue($responder));

        $tokenProperty = new ReflectionProperty(HttpChatResponder::class, 'apiToken');
        $tokenProperty->setAccessible(true);
        self::assertSame('settings-token', $tokenProperty->getValue($responder));
    }

    private function createDirectory(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }

        $this->createdDirectories[] = $path;
    }

    private function fixtureIndexPayload(): string
    {
        return json_encode([
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
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
