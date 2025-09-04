<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

/**
 * Determines domain type based on request host.
 */
class DomainMiddleware implements MiddlewareInterface
{
    /**
     * {@inheritDoc}
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        $host = strtolower($request->getUri()->getHost());
        $host = (string) preg_replace('/^(www|admin)\./', '', $host);

        $mainDomain = strtolower((string) getenv('MAIN_DOMAIN'));
        $mainDomain = (string) preg_replace('/^(www|admin)\./', '', $mainDomain);

        $domainType = 'main';
        if (
            $mainDomain !== ''
            && $host !== $mainDomain
            && str_ends_with($host, '.' . $mainDomain)
        ) {
            $domainType = 'tenant';
        }

        $request = $request->withAttribute('domainType', $domainType);

        return $handler->handle($request);
    }
}
