<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

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

        if (
            $mainDomain === ''
            || ($domainType === 'main' && $host !== $mainDomain)
        ) {
            $message = 'Invalid main domain configuration.';
            error_log(sprintf(
                'MAIN_DOMAIN misconfiguration: "%s" (request host: "%s")',
                $mainDomain,
                $host
            ));

            $accept = $request->getHeaderLine('Accept');
            $path = $request->getUri()->getPath();
            $xhr = $request->getHeaderLine('X-Requested-With');

            $isApi = str_starts_with($path, '/api/')
                || str_contains($accept, 'application/json')
                || $xhr === 'fetch';

            $response = new SlimResponse(403);

            if ($isApi) {
                $response->getBody()->write(json_encode(['error' => $message]));

                return $response->withHeader('Content-Type', 'application/json');
            }

            $response->getBody()->write($message);

            return $response->withHeader('Content-Type', 'text/html');
        }

        $request = $request->withAttribute('domainType', $domainType);

        return $handler->handle($request);
    }
}
