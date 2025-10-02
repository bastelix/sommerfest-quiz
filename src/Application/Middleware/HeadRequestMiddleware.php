<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Psr7\Factory\StreamFactory;

final class HeadRequestMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (strtoupper($request->getMethod()) !== 'HEAD') {
            return $handler->handle($request);
        }

        $converted = $request->withMethod('GET');

        try {
            $response = $handler->handle($converted);
        } catch (HttpMethodNotAllowedException $exception) {
            // When upstream middleware restores the original HEAD method the router can
            // still report "GET" as the only allowed verb. Retry with the converted
            // request so we consistently mirror the GET handler for HEAD requests.
            if (!in_array('GET', $exception->getAllowedMethods(), true)) {
                throw $exception;
            }

            $response = $handler->handle($converted);
        }
        $contentLength = $response->getHeaderLine('Content-Length');

        $streamFactory = new StreamFactory();
        $response = $response->withBody($streamFactory->createStream(''));

        if ($contentLength === '') {
            return $response->withHeader('Content-Length', '0');
        }

        return $response->withHeader('Content-Length', $contentLength);
    }
}
