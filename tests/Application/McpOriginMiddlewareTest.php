<?php

declare(strict_types=1);

namespace Tests\Application;

use App\Application\Middleware\McpOriginMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Headers;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Slim\Psr7\Uri;

class McpOriginMiddlewareTest extends TestCase
{
    private function createRequest(string $host = 'example.com', string $origin = ''): Request
    {
        $uri = new Uri('https', $host, 443, '/mcp');
        $stream = (new StreamFactory())->createStream('');
        $headers = new Headers();
        if ($origin !== '') {
            $headers->addHeader('Origin', $origin);
        }

        return new Request('POST', $uri, $headers, [], [], $stream);
    }

    private function createPassthroughHandler(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            public bool $called = false;
            public function handle(ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                $this->called = true;
                return new Response();
            }
        };
    }

    public function testRequestWithoutOriginIsAllowed(): void
    {
        $middleware = new McpOriginMiddleware();
        $handler = $this->createPassthroughHandler();

        $response = $middleware->process($this->createRequest(), $handler);

        $this->assertTrue($handler->called);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testMatchingOriginIsAllowed(): void
    {
        $middleware = new McpOriginMiddleware();
        $handler = $this->createPassthroughHandler();

        $response = $middleware->process(
            $this->createRequest('example.com', 'https://example.com'),
            $handler
        );

        $this->assertTrue($handler->called);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testMismatchedOriginIsRejected(): void
    {
        $middleware = new McpOriginMiddleware();
        $handler = $this->createPassthroughHandler();

        $response = $middleware->process(
            $this->createRequest('example.com', 'https://evil.com'),
            $handler
        );

        $this->assertFalse($handler->called);
        $this->assertSame(403, $response->getStatusCode());
    }

    public function testExplicitAllowedOriginsAcceptsListed(): void
    {
        $middleware = new McpOriginMiddleware(['https://trusted.com', 'https://also-ok.com']);
        $handler = $this->createPassthroughHandler();

        $response = $middleware->process(
            $this->createRequest('example.com', 'https://trusted.com'),
            $handler
        );

        $this->assertTrue($handler->called);
    }

    public function testExplicitAllowedOriginsRejectsUnlisted(): void
    {
        $middleware = new McpOriginMiddleware(['https://trusted.com']);
        $handler = $this->createPassthroughHandler();

        $response = $middleware->process(
            $this->createRequest('example.com', 'https://not-trusted.com'),
            $handler
        );

        $this->assertFalse($handler->called);
        $this->assertSame(403, $response->getStatusCode());
    }

    public function testRejectionReturnsJsonRpcError(): void
    {
        $middleware = new McpOriginMiddleware();
        $handler = $this->createPassthroughHandler();

        $response = $middleware->process(
            $this->createRequest('example.com', 'https://evil.com'),
            $handler
        );

        $response->getBody()->rewind();
        $data = json_decode((string) $response->getBody(), true);
        $this->assertSame('2.0', $data['jsonrpc']);
        $this->assertSame(-32600, $data['error']['code']);
        $this->assertStringContainsString('Origin', $data['error']['message']);
    }
}
