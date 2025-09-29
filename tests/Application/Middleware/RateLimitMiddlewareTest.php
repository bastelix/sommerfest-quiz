<?php

declare(strict_types=1);

namespace Tests\Application\Middleware;

use App\Application\Middleware\RateLimitMiddleware;
use App\Application\RateLimiting\RateLimitStoreInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

class RateLimitMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        RateLimitMiddleware::setPersistentStore(null);
        RateLimitMiddleware::resetPersistentStorage();
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        RateLimitMiddleware::setPersistentStore(null);
        RateLimitMiddleware::resetPersistentStorage();
        $_SESSION = [];
        parent::tearDown();
    }

    public function testPersistentLimitBlocksAcrossSessions(): void
    {
        $middleware = new RateLimitMiddleware(2, 3600);
        $factory = new ServerRequestFactory();
        $request = $factory->createServerRequest(
            'POST',
            'https://example.com/landing/contact',
            [
                'REMOTE_ADDR' => '203.0.113.5',
                'HTTP_USER_AGENT' => 'phpunit-bot',
            ]
        );

        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $factory = new ResponseFactory();

                return $factory->createResponse(204);
            }
        };

        for ($i = 0; $i < 2; $i++) {
            $_SESSION = [];
            $response = $middleware->process($request, $handler);
            $this->assertSame(204, $response->getStatusCode());
        }

        $_SESSION = [];
        $response = $middleware->process($request, $handler);
        $this->assertSame(429, $response->getStatusCode());
        $this->assertSame('3600', $response->getHeaderLine('Retry-After'));
    }

    public function testCustomPersistentStoreIsRespectedAcrossInstances(): void
    {
        $store = new class implements RateLimitStoreInterface {
            /** @var array<string, array{count:int,start:int}> */
            private array $entries = [];

            public function increment(string $key, int $windowSeconds): int
            {
                $now = time();
                $entry = $this->entries[$key] ?? ['count' => 0, 'start' => $now];
                if (($now - $entry['start']) > $windowSeconds) {
                    $entry = ['count' => 0, 'start' => $now];
                }
                $entry['count']++;
                $this->entries[$key] = $entry;

                return $entry['count'];
            }

            public function reset(): void
            {
                $this->entries = [];
            }
        };

        RateLimitMiddleware::setPersistentStore($store);
        RateLimitMiddleware::resetPersistentStorage();

        $factory = new ServerRequestFactory();
        $request = $factory->createServerRequest(
            'POST',
            'https://example.com/landing/contact',
            [
                'REMOTE_ADDR' => '198.51.100.7',
                'HTTP_USER_AGENT' => 'phpunit-custom-store',
            ]
        );

        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $factory = new ResponseFactory();

                return $factory->createResponse(204);
            }
        };

        $first = new RateLimitMiddleware(1, 3600);
        $_SESSION = [];
        $responseOne = $first->process($request, $handler);
        $this->assertSame(204, $responseOne->getStatusCode());

        $second = new RateLimitMiddleware(1, 3600);
        $_SESSION = [];
        $responseTwo = $second->process($request, $handler);
        $this->assertSame(429, $responseTwo->getStatusCode());
    }
}
