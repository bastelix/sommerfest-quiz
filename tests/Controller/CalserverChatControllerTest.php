<?php

declare(strict_types=1);

namespace Tests\Controller;

use App\Application\Middleware\RateLimitMiddleware;
use App\Controller\Marketing\CalserverChatController;
use App\Service\RagChat\RagChatService;
use RuntimeException;
use Slim\Psr7\Factory\ResponseFactory;
use Tests\TestCase;

use function dirname;
use function file_put_contents;
use function json_decode;
use function json_encode;
use function is_dir;
use function mkdir;
use function register_shutdown_function;
use function rmdir;
use function scandir;
use function session_destroy;
use function session_id;
use function session_name;
use function session_start;
use function session_status;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class CalserverChatControllerTest extends TestCase
{
    public function testCalserverChatPrefersSlugOverHost(): void
    {
        RateLimitMiddleware::resetPersistentStorage();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        session_id('calserver-chat-slug');
        session_start();
        $_SESSION['csrf_token'] = 'token';
        $_COOKIE[session_name()] = session_id();

        $service = $this->createChatServiceFixture();

        $request = $this->createRequest(
            'POST',
            '/calserver/chat',
            [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'X-CSRF-Token' => 'token',
            ],
            [session_name() => session_id()]
        );
        $request->getBody()->write(json_encode(['question' => 'Legacy?'], JSON_THROW_ON_ERROR));
        $request->getBody()->rewind();
        $request = $request
            ->withUri($request->getUri()->withHost('legacy.example.com'))
            ->withAttribute('ragChatService', $service);

        $app = $this->getAppInstance();
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('Legacy?', $payload['question']);
        $this->assertSame(
            'Ich konnte keine passenden Informationen in der Dokumentation finden. Bitte formuliere deine Frage anders oder schränke das Thema ein.',
            $payload['answer']
        );
        $this->assertSame([], $payload['context']);
    }

    public function testCalserverChatFallsBackToHostWhenSlugMissing(): void
    {
        $service = $this->createChatServiceFixture();
        $controller = new CalserverChatController(null, $service);

        $request = $this->createRequest(
            'POST',
            '/calserver/chat',
            [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]
        );
        $request->getBody()->write(json_encode(['question' => 'Legacy?'], JSON_THROW_ON_ERROR));
        $request->getBody()->rewind();
        $request = $request->withUri($request->getUri()->withHost('legacy.example.com'));

        $responseFactory = new ResponseFactory();
        $response = $responseFactory->createResponse();

        $result = $controller($request, $response);

        $this->assertSame(200, $result->getStatusCode());
        $payload = json_decode((string) $result->getBody(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('Legacy?', $payload['question']);
        $this->assertNotSame([], $payload['context']);
        $this->assertSame('domain', $payload['context'][0]['metadata']['source']);
    }

    private function createChatServiceFixture(): RagChatService
    {
        $baseDir = sys_get_temp_dir() . '/calserver-chat-' . uniqid('', true);
        $domainDir = $baseDir . '/domains/legacy';

        $this->ensureDirectory($domainDir);

        $this->writeIndex($baseDir . '/index.json', 'global-chunk', 'global');
        $this->writeIndex($domainDir . '/index.json', 'domain-chunk', 'domain');

        register_shutdown_function(static function () use ($baseDir): void {
            self::cleanupDirectory($baseDir);
        });

        return new RagChatService($baseDir . '/index.json', $baseDir . '/domains', null, static fn (): array => []);
    }

    private function ensureDirectory(string $path): void
    {
        if (is_dir($path)) {
            return;
        }

        if (!mkdir($path, 0777, true) && !is_dir($path)) {
            throw new RuntimeException('Unable to create directory: ' . $path);
        }
    }

    private function writeIndex(string $path, string $chunkId, string $source): void
    {
        $directory = dirname($path);
        if (!is_dir($directory)) {
            $this->ensureDirectory($directory);
        }

        $payload = [
            'vocabulary' => ['legacy'],
            'idf' => [1.0],
            'chunks' => [[
                'id' => $chunkId,
                'text' => ucfirst($source) . ' answer',
                'metadata' => ['source' => $source],
                'vector' => [[0, 1.0]],
                'norm' => 1.0,
            ]],
        ];

        file_put_contents($path, json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private static function cleanupDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $target = $path . '/' . $item;
            if (is_dir($target)) {
                self::cleanupDirectory($target);
            } else {
                @unlink($target);
            }
        }

        @rmdir($path);
    }
}
