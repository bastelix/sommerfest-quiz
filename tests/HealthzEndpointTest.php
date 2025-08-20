<?php

declare(strict_types=1);

namespace Tests;

use Tests\TestCase;
use Slim\Factory\AppFactory;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use App\Service\VersionService;

class HealthzEndpointTest extends TestCase
{
    protected function getAppInstance(): \Slim\App
    {
        $app = AppFactory::create();
        $app->get('/healthz', function (Request $request, Response $response) {
            $version = getenv('APP_VERSION');
            if ($version === false || $version === '') {
                $version = (new VersionService())->getCurrentVersion();
            }
            $payload = [
                'status'  => 'ok',
                'app'     => 'quizrace',
                'version' => $version,
                'time'    => gmdate('c'),
            ];
            $response->getBody()->write(json_encode($payload));

            return $response->withHeader('Content-Type', 'application/json');
        });

        return $app;
    }

    public function testHealthzEndpointReturnsOkJson(): void
    {
        $app = $this->getAppInstance();
        $res = $app->handle($this->createRequest('GET', '/healthz'));
        $this->assertSame(200, $res->getStatusCode());
        $this->assertSame('application/json', $res->getHeaderLine('Content-Type'));
        $data = json_decode((string) $res->getBody(), true);
        $this->assertSame('ok', $data['status'] ?? null);
        $this->assertSame('quizrace', $data['app'] ?? null);
        $time = $data['time'] ?? '';
        $dt = \DateTimeImmutable::createFromFormat(DATE_ATOM, $time);
        $this->assertNotFalse($dt);
        $this->assertSame($time, $dt->format(DATE_ATOM));
    }

    public function testHealthzEndpointUsesAppVersionWhenSet(): void
    {
        putenv('APP_VERSION=1.2.3');
        $_ENV['APP_VERSION'] = '1.2.3';

        $app = $this->getAppInstance();
        $res = $app->handle($this->createRequest('GET', '/healthz'));
        $data = json_decode((string) $res->getBody(), true);

        $this->assertSame('1.2.3', $data['version'] ?? null);

        putenv('APP_VERSION');
        unset($_ENV['APP_VERSION']);
    }

    public function testHealthzEndpointUsesVersionServiceWhenEnvMissing(): void
    {
        putenv('APP_VERSION');
        unset($_ENV['APP_VERSION']);

        $expected = (new VersionService())->getCurrentVersion();
        $app = $this->getAppInstance();
        $res = $app->handle($this->createRequest('GET', '/healthz'));
        $data = json_decode((string) $res->getBody(), true);

        $this->assertSame($expected, $data['version'] ?? null);
    }

    public function testHealthzEndpointReturnsOkForTenantHost(): void
    {
        $old = getenv('MAIN_DOMAIN');
        putenv('MAIN_DOMAIN=example.com');

        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/healthz');
        $uri = $request->getUri()->withHost('foo.example.com');
        $res = $app->handle($request->withUri($uri));

        $this->assertSame(200, $res->getStatusCode());

        if ($old === false) {
            putenv('MAIN_DOMAIN');
        } else {
            putenv('MAIN_DOMAIN=' . $old);
        }
    }
}
