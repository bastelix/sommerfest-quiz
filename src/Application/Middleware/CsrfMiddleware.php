<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

/**
 * Very basic CSRF protection middleware.
 */
class CsrfMiddleware implements MiddlewareInterface
{
    /**
     * {@inheritDoc}
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $token = $_SESSION['csrf_token'] ?? null;

        if ($request->getMethod() === 'POST') {
            $header = $request->getHeaderLine('X-CSRF-Token');
            if ($token === null || $header !== $token) {
                return (new SlimResponse())->withStatus(403);
            }
        }

        if ($token === null) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
        }

        return $handler->handle($request);
    }
}
