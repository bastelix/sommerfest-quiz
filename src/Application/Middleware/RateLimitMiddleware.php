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
    private ?RateLimitStoreInterface $persistentStore;
    private int $persistentMaxRequests;

    public function __construct(
        int $maxRequests = 5,
        int $windowSeconds = 60,
        ?RateLimitStoreInterface $persistentStore = null,
        ?int $persistentMaxRequests = null
    ) {
        $this->maxRequests = $maxRequests;
        $this->windowSeconds = $windowSeconds;
        $this->persistentStore = $persistentStore ?? ApcuRateLimitStore::createIfAvailable();
        $this->persistentMaxRequests = $persistentMaxRequests ?? $maxRequests;
    }

    /**
     * {@inheritDoc}
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        if ($this->persistentStore !== null) {
            $serverParams = $request->getServerParams();
            $ip = trim((string) ($serverParams['REMOTE_ADDR'] ?? ''));
            $ua = trim((string) ($serverParams['HTTP_USER_AGENT'] ?? ''));
            $path = $request->getUri()->getPath();
            $hashBase = $ip === '' && $ua === '' ? 'unknown' : $ip . '|' . $ua;
            $keys = [
                sprintf('rate:bot:%s:%s', $path, hash('sha256', $hashBase)),
            ];

            if ($ip !== '') {
                $keys[] = sprintf('rate:bot:%s:%s', $path, hash('sha256', $ip));
            }

            foreach ($keys as $storeKey) {
                $count = $this->persistentStore->increment($storeKey, $this->windowSeconds);
                if ($count > $this->persistentMaxRequests) {
                    return (new SlimResponse())->withStatus(429);
                }
            }
        }

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
