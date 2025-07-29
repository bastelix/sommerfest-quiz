<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Log\LoggerInterface;
use Slim\Interfaces\CallableResolverInterface;
use Slim\Middleware\ErrorMiddleware as SlimErrorMiddleware;
use Slim\Exception\HttpNotFoundException;
use Slim\Psr7\Response as SlimResponse;
use Throwable;

/**
 * Custom error middleware handling 404 responses.
 */
class ErrorMiddleware extends SlimErrorMiddleware
{
    public function __construct(
        CallableResolverInterface $callableResolver,
        ResponseFactoryInterface $responseFactory,
        bool $displayErrorDetails = false,
        bool $logErrors = false,
        bool $logErrorDetails = false,
        ?LoggerInterface $logger = null
    ) {
        parent::__construct(
            $callableResolver,
            $responseFactory,
            $displayErrorDetails,
            $logErrors,
            $logErrorDetails,
            $logger
        );

        $this->setErrorHandler(HttpNotFoundException::class, [$this, 'handleNotFound']);
    }

    /**
     * Produce a minimal 404 response and log request path and IP.
     */
    public function handleNotFound(
        Request $request,
        Throwable $exception,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails
    ): Response {
        $path = $request->getUri()->getPath();
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown';
        if ($logErrors) {
            $message = sprintf('404 %s from %s', $path, $ip);
            if ($this->logger instanceof LoggerInterface) {
                $this->logger->error($message);
            } else {
                error_log($message);
            }
        }

        $response = new SlimResponse(404);
        $response->getBody()->write('Not Found');

        return $response->withHeader('Content-Type', 'text/plain');
    }
}
