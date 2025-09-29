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
    public function process(Request $request, RequestHandler $handler): Response {
        $token = $_SESSION['csrf_token'] ?? null;

        if ($request->getMethod() === 'POST') {
            $header = $request->getHeaderLine('X-CSRF-Token');
            $bodyToken = '';
            $data = $request->getParsedBody();
            if (is_array($data)) {
                $bodyToken = (string) ($data['_token'] ?? $data['csrf_token'] ?? '');
            }
            if ($token === null || ($header !== $token && $bodyToken !== $token)) {
                $accept = $request->getHeaderLine('Accept');
                $xhr = $request->getHeaderLine('X-Requested-With');
                $path = $request->getUri()->getPath();
                $isApi = str_starts_with($path, '/api/')
                    || str_contains($accept, 'application/json')
                    || $xhr === 'fetch';
                if ($isApi) {
                    $resp = new SlimResponse(419);
                    $resp->getBody()->write(json_encode(['error' => 'csrf']));

                    return $resp->withHeader('Content-Type', 'application/json');
                }

                return (new SlimResponse())->withStatus(403);
            }
        }

        if ($token === null) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
        }

        return $handler->handle($request);
    }
}
