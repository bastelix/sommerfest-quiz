<?php

declare(strict_types=1);

namespace Tests\Controller;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Tests\TestCase;

class NginxReloadRouteTest extends TestCase
{
    public function testRequestSentWhenUrlConfigured(): void {
        $old = getenv('NGINX_RELOADER_URL');
        putenv('NGINX_RELOAD_TOKEN=changeme');
        putenv('NGINX_RELOADER_URL=http://localhost/reload');
        $_ENV['NGINX_RELOAD_TOKEN'] = 'changeme';
        $_ENV['NGINX_RELOADER_URL'] = 'http://localhost/reload';

        $history = [];
        $mock = new MockHandler([
            new GuzzleResponse(200, [], json_encode(['status' => 'ok'])),
        ]);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));
        $client = new Client(['handler' => $stack]);

        $app = $this->getAppInstance();
        $req = $this->createRequest('POST', '/nginx-reload', ['X-Token' => 'changeme']);
        $req = $req->withAttribute('httpClient', $client);
        $res = $app->handle($req);

        $this->assertSame(200, $res->getStatusCode());
        $this->assertCount(1, $history);
        $this->assertSame('POST', $history[0]['request']->getMethod());
        $this->assertSame('changeme', $history[0]['request']->getHeaderLine('X-Token'));

        if ($old === false) {
            putenv('NGINX_RELOADER_URL');
            unset($_ENV['NGINX_RELOADER_URL']);
        } else {
            putenv('NGINX_RELOADER_URL=' . $old);
            $_ENV['NGINX_RELOADER_URL'] = $old;
        }
    }
}
