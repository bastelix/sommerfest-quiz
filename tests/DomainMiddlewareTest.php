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
    public function testWwwHostTreatedAsMain(): void
    {
        $old = getenv('MAIN_DOMAIN');
        putenv('MAIN_DOMAIN=main-domain.tld');

        $middleware = new DomainMiddleware();
        $factory = new ServerRequestFactory();
        $request = $factory->createServerRequest('GET', 'https://www.main-domain.tld/');

        $handler = new class implements RequestHandlerInterface {
            public ?Request $request = null;

            public function handle(Request $request): ResponseInterface
            {
                $this->request = $request;
                return new Response();
            }
        };

        $middleware->process($request, $handler);
        $this->assertSame('main', $handler->request->getAttribute('domainType'));

        if ($old === false) {
            putenv('MAIN_DOMAIN');
        } else {
            putenv('MAIN_DOMAIN=' . $old);
        }
    }

    public function testAdminHostTreatedAsMain(): void
    {
        $old = getenv('MAIN_DOMAIN');
        putenv('MAIN_DOMAIN=main-domain.tld');

        $middleware = new DomainMiddleware();
        $factory = new ServerRequestFactory();
        $request = $factory->createServerRequest('GET', 'https://admin.main-domain.tld/');

        $handler = new class implements RequestHandlerInterface {
            public ?Request $request = null;

            public function handle(Request $request): ResponseInterface
            {
                $this->request = $request;
                return new Response();
            }
        };

        $middleware->process($request, $handler);
        $this->assertSame('main', $handler->request->getAttribute('domainType'));

        if ($old === false) {
            putenv('MAIN_DOMAIN');
        } else {
            putenv('MAIN_DOMAIN=' . $old);
        }
    }

    public function testMissingMainDomainDefaultsToMain(): void
    {
        $old = getenv('MAIN_DOMAIN');
        putenv('MAIN_DOMAIN');

        $middleware = new DomainMiddleware();
        $factory = new ServerRequestFactory();
        $request = $factory->createServerRequest('GET', 'https://foo.test/');

        $handler = new class implements RequestHandlerInterface {
            public ?Request $request = null;

            public function handle(Request $request): ResponseInterface
            {
                $this->request = $request;
                return new Response();
            }
        };

        $middleware->process($request, $handler);
        $this->assertSame('main', $handler->request->getAttribute('domainType'));

        if ($old === false) {
            putenv('MAIN_DOMAIN');
        } else {
            putenv('MAIN_DOMAIN=' . $old);
        }
    }
}
