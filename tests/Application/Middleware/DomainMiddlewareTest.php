<?php

declare(strict_types=1);

namespace Tests\Application\Middleware;

use App\Application\Middleware\DomainMiddleware;
use App\Infrastructure\Database;
use App\Service\MarketingDomainProvider;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Headers;
use Slim\Psr7\Request as SlimRequest;
use Slim\Psr7\Response as SlimResponse;
use Slim\Psr7\Uri;

final class DomainMiddlewareTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        Database::setFactory(null);
    }

    public function testMissingDomainReturnsNotFoundJsonWithContext(): void
    {
        Database::setFactory(static fn (): PDO => new PDO('sqlite::memory:'));
        $provider = new class ([]) extends MarketingDomainProvider {
            /** @param list<string> $domains */
            public function __construct(private array $domains)
            {
            }

            public function getMainDomain(): ?string
            {
                return 'example.com';
            }

            public function getMainDomainSource(): ?string
            {
                return 'test';
            }

            public function getMarketingDomains(bool $stripAdmin = true): array
            {
                return $this->domains;
            }
        };

        $middleware = new DomainMiddleware($provider);
        $request = $this->createRequest('GET', 'https://calserver.com/', ['Accept' => ['application/json']]);

        $handler = new class implements RequestHandler {
            public bool $handled = false;

            public function handle(Request $request): ResponseInterface
            {
                $this->handled = true;

                return new SlimResponse();
            }
        };

        $response = $middleware->process($request, $handler);

        self::assertFalse($handler->handled);
        self::assertSame(404, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertJsonStringEqualsJsonString(
            json_encode([
                'error' => 'Requested domain "calserver.com" is not registered.',
                'host' => 'calserver.com',
                'mainDomain' => 'example.com',
            ]),
            (string) $response->getBody()
        );
    }

    public function testLightweightHealthCheckIsReturnedEarly(): void
    {
        Database::setFactory(static fn (): PDO => new PDO('sqlite::memory:'));
        $provider = new class ([]) extends MarketingDomainProvider {
            /** @param list<string> $domains */
            public function __construct(private array $domains)
            {
            }

            public function getMainDomain(): ?string
            {
                return null;
            }

            public function getMainDomainSource(): ?string
            {
                return null;
            }

            public function getMarketingDomains(bool $stripAdmin = true): array
            {
                return $this->domains;
            }
        };

        $middleware = new DomainMiddleware($provider);
        $request = $this->createRequest('HEAD', 'https://unknown.test/healthz-lite');

        $handler = new class implements RequestHandler {
            public bool $handled = false;

            public function handle(Request $request): ResponseInterface
            {
                $this->handled = true;

                return new SlimResponse();
            }
        };

        $response = $middleware->process($request, $handler);

        self::assertFalse($handler->handled);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame('', (string) $response->getBody());
    }

    private function createRequest(string $method, string $uri, array $headers = []): Request
    {
        $uriObject = new Uri($uri);
        $h = new Headers(array_merge(['Host' => [$uriObject->getHost()]], $headers));
        $streamFactory = new StreamFactory();
        $body = $streamFactory->createStream('');

        return new SlimRequest($method, $uriObject, $h, [], [], $body);
    }
}
