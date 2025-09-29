<?php

declare(strict_types=1);

namespace Tests;

use App\Application\Middleware\DomainMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

class DomainMiddlewareTest extends TestCase
{
    public function testWwwHostTreatedAsMain(): void {
        $old = getenv('MAIN_DOMAIN');
        putenv('MAIN_DOMAIN=main-domain.tld');
        $oldMarketing = getenv('MARKETING_DOMAINS');
        putenv('MARKETING_DOMAINS');

        $middleware = new DomainMiddleware();
        $factory = new ServerRequestFactory();
        $request = $factory->createServerRequest('GET', 'https://www.main-domain.tld/');

        $handler = new class implements RequestHandlerInterface {
            public ?Request $request = null;

            public function handle(Request $request): ResponseInterface {
                $this->request = $request;
                return new Response();
            }
        };

        $middleware->process($request, $handler);
        $this->assertSame('main', $handler->request->getAttribute('domainType'));

        $this->restoreEnv('MAIN_DOMAIN', $old);
        $this->restoreEnv('MARKETING_DOMAINS', $oldMarketing);
    }

    public function testAdminHostTreatedAsMain(): void {
        $old = getenv('MAIN_DOMAIN');
        putenv('MAIN_DOMAIN=main-domain.tld');
        $oldMarketing = getenv('MARKETING_DOMAINS');
        putenv('MARKETING_DOMAINS');

        $middleware = new DomainMiddleware();
        $factory = new ServerRequestFactory();
        $request = $factory->createServerRequest('GET', 'https://admin.main-domain.tld/');

        $handler = new class implements RequestHandlerInterface {
            public ?Request $request = null;

            public function handle(Request $request): ResponseInterface {
                $this->request = $request;
                return new Response();
            }
        };

        $middleware->process($request, $handler);
        $this->assertSame('main', $handler->request->getAttribute('domainType'));

        $this->restoreEnv('MAIN_DOMAIN', $old);
        $this->restoreEnv('MARKETING_DOMAINS', $oldMarketing);
    }

    public function testMissingMainDomainReturnsError(): void {
        $old = getenv('MAIN_DOMAIN');
        putenv('MAIN_DOMAIN');
        $oldMarketing = getenv('MARKETING_DOMAINS');
        putenv('MARKETING_DOMAINS');

        $middleware = new DomainMiddleware();
        $factory = new ServerRequestFactory();
        $request = $factory->createServerRequest('GET', 'https://foo.test/');
        $request = $request->withHeader('Accept', 'application/json');

        $handler = new class implements RequestHandlerInterface {
            public bool $handled = false;

            public function handle(Request $request): ResponseInterface {
                $this->handled = true;
                return new Response();
            }
        };

        $response = $middleware->process($request, $handler);

        $this->assertFalse($handler->handled);
        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame(
            json_encode(['error' => 'Invalid main domain configuration.']),
            (string) $response->getBody()
        );

        $this->restoreEnv('MAIN_DOMAIN', $old);
        $this->restoreEnv('MARKETING_DOMAINS', $oldMarketing);
    }

    public function testInvalidMainDomainReturnsError(): void {
        $old = getenv('MAIN_DOMAIN');
        putenv('MAIN_DOMAIN=main-domain.tld');
        $oldMarketing = getenv('MARKETING_DOMAINS');
        putenv('MARKETING_DOMAINS');

        $middleware = new DomainMiddleware();
        $factory = new ServerRequestFactory();
        $request = $factory->createServerRequest('GET', 'https://wrong.tld/');
        $request = $request->withHeader('Accept', 'application/json');

        $handler = new class implements RequestHandlerInterface {
            public bool $handled = false;

            public function handle(Request $request): ResponseInterface {
                $this->handled = true;
                return new Response();
            }
        };

        $response = $middleware->process($request, $handler);

        $this->assertFalse($handler->handled);
        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame(
            json_encode(['error' => 'Invalid main domain configuration.']),
            (string) $response->getBody()
        );

        $this->restoreEnv('MAIN_DOMAIN', $old);
        $this->restoreEnv('MARKETING_DOMAINS', $oldMarketing);
    }

    public function testInvalidMainDomainReturnsHtmlError(): void {
        $old = getenv('MAIN_DOMAIN');
        putenv('MAIN_DOMAIN=main-domain.tld');
        $oldMarketing = getenv('MARKETING_DOMAINS');
        putenv('MARKETING_DOMAINS');

        $middleware = new DomainMiddleware();
        $factory = new ServerRequestFactory();
        $request = $factory->createServerRequest('GET', 'https://wrong.tld/');

        $handler = new class implements RequestHandlerInterface {
            public bool $handled = false;

            public function handle(Request $request): ResponseInterface {
                $this->handled = true;
                return new Response();
            }
        };

        $response = $middleware->process($request, $handler);

        $this->assertFalse($handler->handled);
        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertSame('Invalid main domain configuration.', (string) $response->getBody());

        $this->restoreEnv('MAIN_DOMAIN', $old);
        $this->restoreEnv('MARKETING_DOMAINS', $oldMarketing);
    }

    public function testMarketingDomainAllowed(): void {
        $oldMain = getenv('MAIN_DOMAIN');
        putenv('MAIN_DOMAIN=main-domain.tld');
        $oldMarketing = getenv('MARKETING_DOMAINS');
        putenv('MARKETING_DOMAINS=marketing-domain.tld');

        $middleware = new DomainMiddleware();
        $factory = new ServerRequestFactory();
        $request = $factory->createServerRequest('GET', 'https://marketing-domain.tld/');

        $handler = new class implements RequestHandlerInterface {
            public ?Request $request = null;

            public function handle(Request $request): ResponseInterface {
                $this->request = $request;
                return new Response();
            }
        };

        $middleware->process($request, $handler);

        $this->assertSame('marketing', $handler->request->getAttribute('domainType'));

        $this->restoreEnv('MAIN_DOMAIN', $oldMain);
        $this->restoreEnv('MARKETING_DOMAINS', $oldMarketing);
    }

    public function testMarketingDomainWithWwwAllowed(): void {
        $oldMain = getenv('MAIN_DOMAIN');
        putenv('MAIN_DOMAIN=main-domain.tld');
        $oldMarketing = getenv('MARKETING_DOMAINS');
        putenv('MARKETING_DOMAINS=marketing-domain.tld');

        $middleware = new DomainMiddleware();
        $factory = new ServerRequestFactory();
        $request = $factory->createServerRequest('GET', 'https://www.marketing-domain.tld/');

        $handler = new class implements RequestHandlerInterface {
            public ?Request $request = null;

            public function handle(Request $request): ResponseInterface {
                $this->request = $request;
                return new Response();
            }
        };

        $middleware->process($request, $handler);

        $this->assertSame('marketing', $handler->request->getAttribute('domainType'));

        $this->restoreEnv('MAIN_DOMAIN', $oldMain);
        $this->restoreEnv('MARKETING_DOMAINS', $oldMarketing);
    }

    private function restoreEnv(string $variable, mixed $value): void {
        if ($value === false || $value === null) {
            putenv($variable);
            return;
        }

        putenv(sprintf('%s=%s', $variable, $value));
    }
}
