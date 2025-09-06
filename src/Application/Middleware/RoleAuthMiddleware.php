<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;
use Slim\Routing\RouteContext;

/**
 * Middleware ensuring the user has one of the allowed roles.
 */
class RoleAuthMiddleware implements MiddlewareInterface
{
    /**
     * @var list<string>
     */
    private array $roles;

    public function __construct(string ...$roles)
    {
        $this->roles = $roles;
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        if ($this->roles === []) {
            return $handler->handle($request);
        }
        $role = $_SESSION['user']['role'] ?? null;
        if ($role === null || !in_array($role, $this->roles, true)) {
            $accept = $request->getHeaderLine('Accept');
            $xhr = $request->getHeaderLine('X-Requested-With');
            $path = $request->getUri()->getPath();
            $base = RouteContext::fromRequest($request)->getBasePath();
            $isApi = str_starts_with($path, $base . '/api/')
                || str_contains($accept, 'application/json')
                || $xhr === 'fetch';

            if ($isApi) {
                $response = new SlimResponse(401);
                $response->getBody()->write(json_encode(['error' => 'unauthorized']));

                return $response->withHeader('Content-Type', 'application/json');
            }

            $response = new SlimResponse();

            return $response->withHeader('Location', $base . '/login')->withStatus(302);
        }

        return $handler->handle($request);
    }
}
