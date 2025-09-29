<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\RateLimiting\RateLimitStore;
use App\Application\RateLimiting\RateLimitStoreFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

class RateLimitMiddleware implements MiddlewareInterface
{
    private int $maxRequests;
    private int $windowSeconds;
    private RateLimitStore $store;

    private static ?RateLimitStore $defaultStore = null;

    public function __construct(int $maxRequests = 5, int $windowSeconds = 60, ?RateLimitStore $store = null)
    {
        $this->maxRequests = $maxRequests;
        $this->windowSeconds = $windowSeconds;
        $this->store = $store ?? self::getPersistentStore();
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        if (!isset($_SESSION)) {
            $_SESSION = [];
        }

        $key = 'rate:' . $request->getUri()->getPath();
        $entry = $_SESSION[$key] ?? ['count' => 0, 'start' => time()];

        if (time() - $entry['start'] > $this->windowSeconds) {
            $entry = ['count' => 0, 'start' => time()];
        }

        $entry['count']++;
        $_SESSION[$key] = $entry;

        if ($entry['count'] > $this->maxRequests) {
            return $this->tooManyRequestsResponse();
        }

        $persistentCount = $this->incrementPersistentCounter($request);
        if ($persistentCount > $this->maxRequests) {
            return $this->tooManyRequestsResponse();
        }

        return $handler->handle($request);
    }

    public static function setPersistentStore(RateLimitStore $store): void
    {
        self::$defaultStore = $store;
    }

    public static function resetPersistentStorage(): void
    {
        self::getPersistentStore()->reset();
    }

    private static function getPersistentStore(): RateLimitStore
    {
        if (self::$defaultStore === null) {
            self::$defaultStore = RateLimitStoreFactory::createDefault();
        }

        return self::$defaultStore;
    }

    private function tooManyRequestsResponse(): Response
    {
        $response = new SlimResponse(429);

        return $response->withHeader('Retry-After', (string) $this->windowSeconds);
    }

    private function incrementPersistentCounter(Request $request): int
    {
        $hash = $this->fingerprintRequest($request);

        return $this->store->increment($hash, $this->windowSeconds);
    }

    private function fingerprintRequest(Request $request): string
    {
        $serverParams = $request->getServerParams();
        $ip = (string) ($serverParams['REMOTE_ADDR'] ?? 'unknown');
        $ua = $request->getHeaderLine('User-Agent');
        if ($ua === '') {
            $ua = (string) ($serverParams['HTTP_USER_AGENT'] ?? '');
        }
        $ua = trim($ua);
        if ($ua === '') {
            $ua = 'no-ua';
        }

        $path = strtolower($request->getUri()->getPath());

        return hash('sha256', $path . '|' . $ip . '|' . $ua);
    }
}
