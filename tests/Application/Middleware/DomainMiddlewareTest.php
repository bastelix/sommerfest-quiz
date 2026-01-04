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

    public function testDerivesNamespaceForMarketingDomainWhenNoDatabaseRecordExists(): void
    {
        Database::setFactory(static fn (): PDO => new PDO('sqlite::memory:'));
        $provider = new class([]) extends MarketingDomainProvider {
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
        $request = $this->createRequest('GET', 'https://calserver.com/');

        $handler = new class implements RequestHandler {
            public ?Request $captured = null;

            public function handle(Request $request): ResponseInterface
            {
                $this->captured = $request;

                return new SlimResponse();
            }
        };

        $middleware->process($request, $handler);

        self::assertNotNull($handler->captured);
        self::assertSame('marketing', $handler->captured->getAttribute('domainType'));
        self::assertSame('calserver', $handler->captured->getAttribute('domainNamespace'));
        self::assertSame('calserver', $handler->captured->getAttribute('namespace'));
        self::assertSame('calserver', $handler->captured->getAttribute('pageNamespace'));
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
