<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Propagate a namespace query parameter into request attributes.
 *
 * When a request contains ?namespace=<value>, this middleware copies the value
 * into the four namespace-related request attributes so that downstream handlers
 * receive a consistent namespace context regardless of the resolution source.
 */
final class NamespaceQueryMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $params = $request->getQueryParams();
        $namespace = $params['namespace'] ?? null;
        if (is_string($namespace) && $namespace !== '') {
            $request = $request->withAttribute('namespace', $namespace);
            $request = $request->withAttribute('pageNamespace', $namespace);
            $request = $request->withAttribute('domainNamespace', $namespace);
            $request = $request->withAttribute('eventNamespace', $namespace);
        }

        return $handler->handle($request);
    }
}
