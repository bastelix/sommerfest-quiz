<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

/**
 * Validates the Origin header on MCP endpoint requests to prevent DNS rebinding attacks.
 *
 * Per MCP spec (2025-03-26): "Servers MUST validate the Origin header on all
 * incoming connections to prevent DNS rebinding attacks."
 */
final class McpOriginMiddleware implements MiddlewareInterface
{
    /** @var list<string> */
    private readonly array $allowedOrigins;

    /**
     * @param list<string> $allowedOrigins Allowed origin values. If empty, requests
     *                                     without an Origin header are allowed (e.g.
     *                                     server-to-server), but any Origin that is
     *                                     present must match the server's own host.
     */
    public function __construct(array $allowedOrigins = [])
    {
        $this->allowedOrigins = $allowedOrigins;
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $origin = $request->getHeaderLine('Origin');

        // No Origin header → typically server-to-server or non-browser client → allow
        if ($origin === '') {
            return $handler->handle($request);
        }

        if ($this->isAllowed($request, $origin)) {
            return $handler->handle($request);
        }

        $res = new SlimResponse(403);
        $res->getBody()->write((string) json_encode([
            'jsonrpc' => '2.0',
            'id' => null,
            'error' => ['code' => -32600, 'message' => 'Forbidden: invalid Origin header'],
        ]));
        return $res->withHeader('Content-Type', 'application/json');
    }

    private function isAllowed(Request $request, string $origin): bool
    {
        // Check against explicitly configured origins
        if ($this->allowedOrigins !== []) {
            return in_array($origin, $this->allowedOrigins, true);
        }

        // Default: match Origin against the request's own Host
        $host = $request->getUri()->getHost();
        $parsedOrigin = parse_url($origin, PHP_URL_HOST);

        return $parsedOrigin === $host;
    }
}
