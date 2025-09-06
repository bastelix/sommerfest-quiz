<?php

declare(strict_types=1);

namespace App\Application\Security;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

/**
 * Middleware ensuring a user has a specific role.
 */
class AuthorizationMiddleware implements MiddlewareInterface
{
    private string $requiredRole;

    public function __construct(string $requiredRole)
    {
        $this->requiredRole = $requiredRole;
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        if ($this->requiredRole === '') {
            return $handler->handle($request);
        }
        $role = $_SESSION['user']['role'] ?? null;
        if ($role !== $this->requiredRole) {
            $response = new SlimResponse();
            $base = $request->getUri()->getBasePath();
            return $response->withHeader('Location', $base . '/login')->withStatus(302);
        }
        return $handler->handle($request);
    }
}
