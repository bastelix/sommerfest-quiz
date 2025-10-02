<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\StreamFactory;

final class HeadRequestMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (strtoupper($request->getMethod()) !== 'HEAD') {
            return $handler->handle($request);
        }

        $response = $handler->handle($request->withMethod('GET'));
        $contentLength = $response->getHeaderLine('Content-Length');

        $streamFactory = new StreamFactory();
        $response = $response->withBody($streamFactory->createStream(''));

        if ($contentLength === '') {
            return $response->withHeader('Content-Length', '0');
        }

        return $response->withHeader('Content-Length', $contentLength);
    }
}
