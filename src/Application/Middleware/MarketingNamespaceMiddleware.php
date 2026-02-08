<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Routing\RouteContext;

/**
 * Resolve namespace from legacy marketing URL slugs.
 *
 * For paths starting with /m/ or /landing/, the middleware extracts the slug
 * from the matched route and sets it as the active namespace on the request.
 * Already resolved namespaces (e.g. from domain resolution) take precedence.
 */
final class MarketingNamespaceMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $existingDomainNamespace = $request->getAttribute('domainNamespace');
        $existingPageNamespace = $request->getAttribute('pageNamespace');
        if (
            (is_string($existingDomainNamespace) && $existingDomainNamespace !== '')
            || (is_string($existingPageNamespace) && $existingPageNamespace !== '')
        ) {
            return $handler->handle($request);
        }

        $path = $request->getUri()->getPath();
        $isLegacyMarketingPath = str_starts_with($path, '/m/')
            || str_starts_with($path, '/landing/');
        if (!$isLegacyMarketingPath) {
            return $handler->handle($request);
        }

        $route = RouteContext::fromRequest($request)->getRoute();
        $marketingSlug = $route?->getArgument('marketingSlug')
            ?? $route?->getArgument('landingSlug')
            ?? $route?->getArgument('slug');

        if (is_string($marketingSlug) && $marketingSlug !== '') {
            $request = $request
                ->withAttribute('namespace', $marketingSlug)
                ->withAttribute('pageNamespace', $marketingSlug)
                ->withAttribute('domainNamespace', $marketingSlug)
                ->withAttribute('eventNamespace', $marketingSlug);
        }

        return $handler->handle($request);
    }
}
