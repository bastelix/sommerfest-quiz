<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

/**
 * Adjust request URI based on X-Forwarded-* headers from reverse proxy.
 */
class ProxyMiddleware implements MiddlewareInterface
{
    public function process(Request $request, RequestHandler $handler): Response {
        $uri = $request->getUri();

        $proto = $request->getHeaderLine('X-Forwarded-Proto');
        if ($proto !== '') {
            $uri = $uri->withScheme($proto);
        }

        $host = $request->getHeaderLine('X-Forwarded-Host');
        if ($host !== '') {
            $uri = $uri->withHost(explode(',', $host)[0]);
        }

        $port = $request->getHeaderLine('X-Forwarded-Port');
        if ($port !== '') {
            $portInt = (int) $port;
            if ($portInt === 80 || $portInt === 443) {
                $uri = $uri->withPort(null);
            } else {
                $uri = $uri->withPort($portInt);
            }
        } else {
            $uri = $uri->withPort(null);
        }

        return $handler->handle($request->withUri($uri));
    }
}
