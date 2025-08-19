<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

/**
 * Simple session-based rate limiter.
 */
class RateLimitMiddleware implements MiddlewareInterface
{
    private int $maxRequests;
    private int $windowSeconds;

    public function __construct(int $maxRequests = 5, int $windowSeconds = 60)
    {
        $this->maxRequests = $maxRequests;
        $this->windowSeconds = $windowSeconds;
    }

    /**
     * {@inheritDoc}
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        $key = 'rate:' . $request->getUri()->getPath();
        $entry = $_SESSION[$key] ?? ['count' => 0, 'start' => time()];

        if (time() - $entry['start'] > $this->windowSeconds) {
            $entry = ['count' => 0, 'start' => time()];
        }

        $entry['count']++;
        $_SESSION[$key] = $entry;

        if ($entry['count'] > $this->maxRequests) {
            return (new SlimResponse())->withStatus(429);
        }

        return $handler->handle($request);
    }
}
