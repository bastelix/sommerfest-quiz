<?php

declare(strict_types=1);

namespace Tests\Application\Middleware;

use App\Application\Middleware\RateLimitMiddleware;
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
        RateLimitMiddleware::resetPersistentStorage();
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
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
}
