<?php

declare(strict_types=1);

namespace Tests;

use App\Application\Middleware\AdminAuthMiddleware;
use App\Application\Middleware\CsrfMiddleware;
use App\Application\Middleware\RateLimitMiddleware;
use App\Application\Middleware\RateLimitStoreInterface;
use App\Application\Middleware\RoleAuthMiddleware;
use App\Application\Middleware\SessionMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

class SessionDependentMiddlewareTest extends TestCase
{
    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
        }
        parent::tearDown();
    }

    public function testCsrfMiddlewareSetsAndValidatesToken(): void
    {
        $app = AppFactory::create();
        $app->add(new SessionMiddleware());
        $app->map(['GET', 'POST'], '/csrf', fn (Request $request, Response $response): Response => $response)
            ->add(new CsrfMiddleware());

        $factory = new ServerRequestFactory();
        $get = $factory->createServerRequest('GET', '/csrf');
        $app->handle($get);
        $this->assertNotEmpty($_SESSION['csrf_token']);
        $token = $_SESSION['csrf_token'];
        $sid = session_id();
        session_write_close();

        $post = $factory->createServerRequest('POST', '/csrf')
            ->withHeader('X-CSRF-Token', $token)
            ->withCookieParams([session_name() => $sid]);
        $response = $app->handle($post);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testCsrfMiddlewareReturnsJsonForApiPath(): void
    {
        $app = AppFactory::create();
        $app->add(new SessionMiddleware());
        $app->post('/api/test', fn (Request $request, Response $response): Response => $response)
            ->add(new CsrfMiddleware());

        $factory = new ServerRequestFactory();
        $req = $factory->createServerRequest('POST', '/api/test');
        $res = $app->handle($req);
        $this->assertSame(419, $res->getStatusCode());
        $this->assertSame('application/json', $res->getHeaderLine('Content-Type'));
    }

    public function testRateLimitMiddlewareUsesSession(): void
    {
        $app = AppFactory::create();
        $app->add(new SessionMiddleware());
        $app->get('/limited', fn (Request $request, Response $response): Response => $response)
            ->add(new RateLimitMiddleware(1, 60));

        $factory = new ServerRequestFactory();
        $req1 = $factory->createServerRequest('GET', '/limited');
        $res1 = $app->handle($req1);
        $this->assertSame(200, $res1->getStatusCode());
        $sid = session_id();
        session_write_close();

        $req2 = $factory->createServerRequest('GET', '/limited')
            ->withCookieParams([session_name() => $sid]);
        $res2 = $app->handle($req2);
        $this->assertSame(429, $res2->getStatusCode());
    }

    public function testRateLimitMiddlewarePersistsAcrossSessions(): void
    {
        $store = new class implements RateLimitStoreInterface {
            public array $store = [];

            public function increment(string $key, int $ttlSeconds): int
            {
                $now = time();
                $entry = $this->store[$key] ?? ['count' => 0, 'expires' => $now + $ttlSeconds];
                if ((int) $entry['expires'] < $now) {
                    $entry = ['count' => 0, 'expires' => $now + $ttlSeconds];
                }

                $entry['count']++;
                $this->store[$key] = $entry;

                return $entry['count'];
            }
        };

        $app = AppFactory::create();
        $app->add(new SessionMiddleware());
        $app->get('/persistent', fn (Request $request, Response $response): Response => $response)
            ->add(new RateLimitMiddleware(5, 60, $store, 1));

        $factory = new ServerRequestFactory();
        $serverParams = [
            'REMOTE_ADDR' => '203.0.113.42',
            'HTTP_USER_AGENT' => 'PersistentBot/1.0',
        ];

        $req1 = $factory->createServerRequest('GET', '/persistent', $serverParams);
        $res1 = $app->handle($req1);
        $this->assertSame(200, $res1->getStatusCode());
        $this->assertNotEmpty($store->store);

        session_write_close();
        session_destroy();

        $req2 = $factory->createServerRequest('GET', '/persistent', $serverParams);
        $res2 = $app->handle($req2);
        $this->assertSame(429, $res2->getStatusCode());
    }

    public function testRoleAuthMiddlewareRedirectsWithoutRole(): void
    {
        $app = AppFactory::create();
        $app->add(new SessionMiddleware());
        $app->get('/protected', fn (Request $request, Response $response): Response => $response)
            ->add(new RoleAuthMiddleware('admin'));

        $factory = new ServerRequestFactory();
        $req = $factory->createServerRequest('GET', '/protected');
        $res = $app->handle($req);
        $this->assertSame(302, $res->getStatusCode());
        $this->assertSame('/login', $res->getHeaderLine('Location'));
    }

    public function testRoleAuthMiddlewareReturnsJsonForApiRequests(): void
    {
        $app = AppFactory::create();
        $app->add(new SessionMiddleware());
        $app->get('/protected', fn (Request $request, Response $response): Response => $response)
            ->add(new RoleAuthMiddleware('admin'));

        $factory = new ServerRequestFactory();
        $req = $factory->createServerRequest('GET', '/protected')
            ->withHeader('Accept', 'application/json')
            ->withHeader('X-Requested-With', 'fetch');
        $res = $app->handle($req);
        $this->assertSame(401, $res->getStatusCode());
        $this->assertSame('application/json', $res->getHeaderLine('Content-Type'));
    }

    public function testRoleAuthMiddlewareUsesApiPath(): void
    {
        $app = AppFactory::create();
        $app->add(new SessionMiddleware());
        $app->get('/api/protected', fn (Request $request, Response $response): Response => $response)
            ->add(new RoleAuthMiddleware('admin'));

        $factory = new ServerRequestFactory();
        $req = $factory->createServerRequest('GET', '/api/protected');
        $res = $app->handle($req);
        $this->assertSame(401, $res->getStatusCode());
        $this->assertSame('application/json', $res->getHeaderLine('Content-Type'));
    }

    public function testAdminAuthMiddlewareReturnsJsonForApiRequests(): void
    {
        $app = AppFactory::create();
        $app->add(new SessionMiddleware());
        $app->get('/api/admin', fn (Request $request, Response $response): Response => $response)
            ->add(new AdminAuthMiddleware());

        $factory = new ServerRequestFactory();
        $req = $factory->createServerRequest('GET', '/api/admin');
        $res = $app->handle($req);
        $this->assertSame(401, $res->getStatusCode());
        $this->assertSame('application/json', $res->getHeaderLine('Content-Type'));
    }

    public function testAdminAuthMiddlewareAllowsAdmin(): void
    {
        $app = AppFactory::create();
        $app->add(new SessionMiddleware());
        $app->get('/set', function (Request $request, Response $response): Response {
            $_SESSION['user'] = ['role' => 'admin'];
            return $response;
        });
        $app->get('/admin', fn (Request $request, Response $response): Response => $response)
            ->add(new AdminAuthMiddleware());

        $factory = new ServerRequestFactory();
        $app->handle($factory->createServerRequest('GET', '/set'));
        $sid = session_id();
        session_write_close();

        $req = $factory->createServerRequest('GET', '/admin')
            ->withCookieParams([session_name() => $sid]);
        $res = $app->handle($req);
        $this->assertSame(200, $res->getStatusCode());
    }
}
